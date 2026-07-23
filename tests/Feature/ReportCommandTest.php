<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Workbench\App\Models\Admin;
use Workbench\App\Models\User;
use Workbench\Database\Factories\AdminFactory;
use Workbench\Database\Factories\UserFactory;

beforeEach(function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'laravel-password-rotation.days' => 90,
        'laravel-password-rotation.warn_days' => 7,
        'laravel-password-rotation.force_on_first_login' => true,
        // Includes a rotatable model, a non-implementing model and a bogus class.
        'laravel-password-rotation.models' => [User::class, Admin::class, 'App\\Does\\Not\\Exist'],
    ]);

    // A non-rotatable account that must never appear in the report.
    AdminFactory::new()->create();
});

function runReport(bool $all = false): string
{
    Artisan::call('password-rotation:report', $all ? ['--all' => true] : []);

    return Artisan::output();
}

it('reports expired and expiring accounts by default, hiding healthy ones', function (): void {
    UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]); // expired
    UserFactory::new()->create(['password_changed_at' => now()->subDays(84)]);  // expiring soon
    UserFactory::new()->create(['password_changed_at' => now()]);               // ok (hidden)

    $out = runReport();

    expect($out)->toContain('expired')
        ->toContain('expiring soon')
        ->not->toContain('ok');
});

it('lists every account (including healthy ones) with --all', function (): void {
    UserFactory::new()->create(['password_changed_at' => now()]); // ok

    expect(runReport(all: true))->toContain('ok');
});

it('skips models that do not implement the interface', function (): void {
    UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);

    expect(runReport(all: true))->not->toContain(Admin::class);
});

it('accepts a single model class name that is not wrapped in an array', function (): void {
    // The (array) cast must tolerate a scalar config value, not iterate a string.
    config(['laravel-password-rotation.models' => User::class]);

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);

    expect(runReport(all: true))->toContain(User::class.'#'.((string) $user->getKey()));
});

it('reports nothing when no configured model yields rows', function (): void {
    config(['laravel-password-rotation.models' => ['App\\Does\\Not\\Exist']]);

    expect(runReport(all: true))->toContain('No accounts to report.');
});

it('prints only the info line and no table when nothing matches', function (): void {
    config(['laravel-password-rotation.models' => ['App\\Does\\Not\\Exist']]);

    // The early return must skip the (empty) table entirely: no headers printed.
    expect(runReport(all: true))
        ->toContain('No accounts to report.')
        ->not->toContain('Identifier');
});

it('prints a precise row (identifier, timestamps, status) for an expired account', function (): void {
    $this->freezeTime();

    $changed = now()->subDays(100);
    $user = UserFactory::new()->create(['password_changed_at' => $changed]);

    $this->artisan('password-rotation:report')
        ->expectsTable(
            ['Identifier', 'Last changed', 'Expires at', 'Status'],
            [[
                User::class.'#'.((string) $user->getKey()),
                $changed->format('Y-m-d H:i'),
                $changed->copy()->addDays(90)->format('Y-m-d H:i'),
                'expired',
            ]],
        )
        ->assertSuccessful();
});

it('renders an em dash for both timestamps when no change was ever recorded', function (): void {
    // force_on_first_login is on, so a fresh account has a null timestamp yet is
    // reported as expired; both the last-changed and expires-at columns fall back.
    $user = UserFactory::new()->create();

    $this->artisan('password-rotation:report')
        ->expectsTable(
            ['Identifier', 'Last changed', 'Expires at', 'Status'],
            [[
                User::class.'#'.((string) $user->getKey()),
                '—',
                '—',
                'expired',
            ]],
        )
        ->assertSuccessful();
});

it('excludes healthy accounts from the default report but keeps the expiring one', function (): void {
    $this->freezeTime();

    $changed = now()->subDays(85);
    $expiring = UserFactory::new()->create(['password_changed_at' => $changed]); // expiring soon
    UserFactory::new()->create(['password_changed_at' => now()->subDays(10)]);   // ok (excluded)

    // Exactly one row: the ok account must not leak in (guards the && chain).
    $this->artisan('password-rotation:report')
        ->expectsTable(
            ['Identifier', 'Last changed', 'Expires at', 'Status'],
            [[
                User::class.'#'.((string) $expiring->getKey()),
                $changed->format('Y-m-d H:i'),
                $changed->copy()->addDays(90)->format('Y-m-d H:i'),
                'expiring soon',
            ]],
        )
        ->assertSuccessful();
});

it('marks an account expiring exactly at the warn-days boundary and not a day earlier', function (): void {
    $this->freezeTime();

    // Window opens at day (days - warn_days) = 90 - 7 = 83.
    $inside = UserFactory::new()->create(['password_changed_at' => now()->subDays(83)]); // boundary → expiring
    $before = UserFactory::new()->create(['password_changed_at' => now()->subDays(82)]); // 1 day before → ok

    $out = runReport();

    expect($out)->toContain(User::class.'#'.((string) $inside->getKey()))
        ->not->toContain(User::class.'#'.((string) $before->getKey()));
});

it('hides an account expiring exactly now when the warning window is disabled', function (): void {
    // Freeze on a whole second so the timestamp survives the DB round-trip
    // (datetime columns drop sub-seconds) and expiresAt lands back on exactly now.
    $this->freezeTime();
    $this->travelTo(now()->startOfSecond());
    config(['laravel-password-rotation.warn_days' => 0]);

    // On the expiry boundary (days = 90) but not yet past: with warn_days = 0
    // there is no expiring-soon window, so a default report must hide it. A
    // `>= 0` or `> -1` warn_days test would (wrongly) flag it as expiring soon.
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(90)]);

    expect(runReport())
        ->toContain('No accounts to report.')
        ->not->toContain(User::class.'#'.((string) $user->getKey()));
});

it('reports an account whose expiry is unknown as ok, without dereferencing a null date', function (): void {
    // force_on_first_login off + a null timestamp: not expired, yet no expiry
    // date. The && chain must short-circuit before touching the null date;
    // flipping the first && to || would evaluate now()->gte(null) and crash.
    config(['laravel-password-rotation.force_on_first_login' => false]);

    $user = UserFactory::new()->create();
    $user->forceFill(['password_changed_at' => null])->saveQuietly();

    expect(runReport(all: true))
        ->toContain(User::class.'#'.((string) $user->getKey()))
        ->toContain('ok');
});

it('honours a one-day warning window', function (): void {
    $this->freezeTime();
    config(['laravel-password-rotation.warn_days' => 1]);

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(89)]); // 1 day before expiry

    expect(runReport())
        ->toContain(User::class.'#'.((string) $user->getKey()))
        ->toContain('expiring soon');
});

it('keeps scanning models after skipping non-rotatable and missing ones (continue, not break)', function (): void {
    // A skipped model precedes the rotatable one, so a `break` would stop early.
    config(['laravel-password-rotation.models' => [Admin::class, 'App\\Does\\Not\\Exist', User::class]]);

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);

    expect(runReport(all: true))
        ->toContain(User::class.'#'.((string) $user->getKey()))
        ->toContain('expired');
});

it('keeps scanning accounts after skipping a healthy one (continue, not break)', function (): void {
    $this->freezeTime();

    UserFactory::new()->create(['password_changed_at' => now()->subDays(10)]);              // ok (skipped first)
    $expired = UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);  // expired (after)

    $out = runReport();

    expect($out)
        ->toContain(User::class.'#'.((string) $expired->getKey()))
        ->toContain('expired');
});
