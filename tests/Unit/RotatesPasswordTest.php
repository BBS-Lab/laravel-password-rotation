<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Events\PasswordRotated;
use BBSLab\LaravelPasswordRotation\Models\PasswordHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Event;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

it('exposes the configured rotation column', function (): void {
    config(['laravel-password-rotation.column' => 'password_changed_at']);

    expect((new User)->passwordRotationColumn())->toBe('password_changed_at');
});

it('casts the rotation column to a Carbon instance', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()]);

    expect($user->passwordLastChangedAt())->toBeInstanceOf(CarbonInterface::class);
});

it('returns null for last-changed when the column is empty', function (): void {
    expect((new User)->passwordLastChangedAt())->toBeNull()
        ->and((new User)->passwordExpiresAt())->toBeNull();
});

it('returns an empty string when no rotation column is configured', function (): void {
    config(['laravel-password-rotation.column' => null]);

    // The (string) cast keeps the contract even when the config value is unset.
    expect((new User)->passwordRotationColumn())->toBe('');
});

it('returns null from last-changed when the column holds a non-date value', function (): void {
    $user = UserFactory::new()->create(['name' => 'Alice']);

    // Point the rotation column at a plain (uncast) string column: the
    // instanceof guard must reject the non-Carbon value and return null.
    config(['laravel-password-rotation.column' => 'name']);

    expect($user->passwordLastChangedAt())->toBeNull();
});

it('treats a null rotation period as zero days', function (): void {
    config(['laravel-password-rotation.days' => null]);

    $user = new User;
    $user->setAttribute('password_changed_at', now()->subDays(5));

    // The (int) cast turns the unset period into 0, so expiry == last change.
    expect($user->passwordExpiresAt()->toDateString())
        ->toBe(now()->subDays(5)->toDateString());
});

it('computes expiry from the last change plus the configured days', function (): void {
    config(['laravel-password-rotation.days' => 90]);

    $user = new User;
    $user->setAttribute('password_changed_at', now()->subDays(10));

    expect($user->passwordExpiresAt()->toDateString())
        ->toBe(now()->subDays(10)->addDays(90)->toDateString());
});

describe('passwordHasExpired matrix', function (): void {
    it('is never expired when the feature is disabled', function (): void {
        config(['laravel-password-rotation.enabled' => false]);

        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(999));

        expect($user->passwordHasExpired())->toBeFalse();
    });

    it('treats a null timestamp as expired when force_on_first_login is on', function (): void {
        config(['laravel-password-rotation.enabled' => true, 'laravel-password-rotation.force_on_first_login' => true]);

        expect((new User)->passwordHasExpired())->toBeTrue();
    });

    it('treats a null timestamp as valid when force_on_first_login is off', function (): void {
        config(['laravel-password-rotation.enabled' => true, 'laravel-password-rotation.force_on_first_login' => false]);

        expect((new User)->passwordHasExpired())->toBeFalse();
    });

    it('treats a null first-login setting as not forcing rotation', function (): void {
        config(['laravel-password-rotation.enabled' => true, 'laravel-password-rotation.force_on_first_login' => null]);

        // Null timestamp + unset force flag: the (bool) cast yields false (valid).
        expect((new User)->passwordHasExpired())->toBeFalse();
    });

    it('is expired once the expiry date is in the past', function (): void {
        config(['laravel-password-rotation.enabled' => true, 'laravel-password-rotation.days' => 90]);

        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(100));

        expect($user->passwordHasExpired())->toBeTrue();
    });

    it('is not expired while inside the rotation window', function (): void {
        config(['laravel-password-rotation.enabled' => true, 'laravel-password-rotation.days' => 90]);

        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(1));

        expect($user->passwordHasExpired())->toBeFalse();
    });
});

describe('passwordIsExpiring matrix', function (): void {
    beforeEach(function (): void {
        config([
            'laravel-password-rotation.enabled' => true,
            'laravel-password-rotation.days' => 90,
            'laravel-password-rotation.warn_days' => 7,
            'laravel-password-rotation.force_on_first_login' => true,
        ]);
        $this->freezeTime();
        $this->travelTo(now()->startOfSecond());
    });

    it('is not expiring when the feature is disabled', function (): void {
        config(['laravel-password-rotation.enabled' => false]);

        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(85));

        expect($user->passwordIsExpiring())->toBeFalse();
    });

    it('is not expiring once the password has already expired', function (): void {
        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(100));

        expect($user->passwordIsExpiring())->toBeFalse();
    });

    it('is not expiring when the expiry date cannot be determined', function (): void {
        config(['laravel-password-rotation.force_on_first_login' => false]);

        // Null timestamp, not forced: neither expired nor expiring.
        expect((new User)->passwordIsExpiring())->toBeFalse();
    });

    it('is not expiring when the warning window is disabled', function (): void {
        config(['laravel-password-rotation.warn_days' => 0]);

        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(85));

        expect($user->passwordIsExpiring())->toBeFalse();
    });

    it('is not expiring when the warning window is negative', function (): void {
        config(['laravel-password-rotation.warn_days' => -5]);

        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(85));

        expect($user->passwordIsExpiring())->toBeFalse();
    });

    it('is expiring at the exact moment the warning window opens', function (): void {
        // days=90, warn_days=7 → window opens at day 83 (expiresAt - warn_days == now).
        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(83));

        expect($user->passwordIsExpiring())->toBeTrue();
    });

    it('is not expiring the day before the warning window opens', function (): void {
        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(82));

        expect($user->passwordIsExpiring())->toBeFalse();
    });

    it('is expiring inside the warning window', function (): void {
        $user = new User;
        $user->setAttribute('password_changed_at', now()->subDays(88));

        expect($user->passwordIsExpiring())->toBeTrue();
    });
});

it('stamps the rotation column on creation when empty and first-login rotation is off', function (): void {
    config(['laravel-password-rotation.force_on_first_login' => false]);

    $user = UserFactory::new()->create();

    expect($user->password_changed_at)->not->toBeNull();
});

it('leaves the rotation column null on creation when first-login rotation is on', function (): void {
    config(['laravel-password-rotation.force_on_first_login' => true]);

    $user = UserFactory::new()->create();

    // Left null so the freshly provisioned account is forced to rotate.
    expect($user->password_changed_at)->toBeNull()
        ->and($user->passwordHasExpired())->toBeTrue();
});

it('does not overwrite a rotation column provided on creation', function (): void {
    $when = now()->subDays(30)->startOfSecond();

    $user = UserFactory::new()->create(['password_changed_at' => $when]);

    expect($user->password_changed_at->equalTo($when))->toBeTrue();
});

it('re-stamps the rotation column when the password changes', function (): void {
    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(30)]);

    $fresh = User::query()->find($user->getKey());
    $fresh->password = 'a-brand-new-secret';
    $fresh->save();

    expect($fresh->password_changed_at->isToday())->toBeTrue();
});

it('does not touch the column or fire the event on an unrelated update', function (): void {
    Event::fake([PasswordRotated::class]);

    $user = UserFactory::new()->create(['password_changed_at' => now()->subDays(30)]);
    $before = PasswordHistory::query()->count();

    $fresh = User::query()->find($user->getKey());
    $fresh->name = 'Renamed';
    $fresh->save();

    expect(PasswordHistory::query()->count())->toBe($before);
    Event::assertNotDispatched(PasswordRotated::class);
});

it('records history but does not dispatch the event on creation', function (): void {
    Event::fake([PasswordRotated::class]);

    UserFactory::new()->create();

    expect(PasswordHistory::query()->count())->toBe(1);
    Event::assertNotDispatched(PasswordRotated::class);
});

it('records history and dispatches the event when a password is rotated', function (): void {
    Event::fake([PasswordRotated::class]);

    $user = UserFactory::new()->create();

    $fresh = User::query()->find($user->getKey());
    $fresh->password = 'the-next-secret';
    $fresh->save();

    expect(PasswordHistory::query()->count())->toBe(2);
    Event::assertDispatched(PasswordRotated::class, fn (PasswordRotated $e) => $e->authenticatable->is($fresh));
});

it('does not record any history when the window is disabled', function (): void {
    config(['laravel-password-rotation.history_count' => 0]);

    $user = UserFactory::new()->create();

    $fresh = User::query()->find($user->getKey());
    $fresh->password = 'a-brand-new-secret';
    $fresh->save();

    expect(PasswordHistory::query()->count())->toBe(0);
});

it('records history when the window is exactly one', function (): void {
    config(['laravel-password-rotation.history_count' => 1]);

    $user = UserFactory::new()->create(); // records the initial password

    $fresh = User::query()->find($user->getKey());
    $fresh->password = 'a-brand-new-secret';
    $fresh->save(); // records the new password, prunes back to count + 1

    expect(PasswordHistory::query()->count())->toBe(2);
});
