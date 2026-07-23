<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Models\PasswordHistory;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

it('records the current hash and prunes to the configured count (+ current)', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create(); // create() already recorded one entry

    foreach (range(1, 5) as $i) {
        PasswordHistory::recordFor($user);
    }

    // count previous + the current password are retained.
    expect(PasswordHistory::query()->count())->toBe(4);
});

it('prunes down to exactly one previous password plus the current when the window is one', function (): void {
    config(['laravel-password-rotation.history_count' => 1]);

    $user = UserFactory::new()->create(); // records one entry on create

    foreach (range(1, 4) as $i) {
        PasswordHistory::recordFor($user);
    }

    // Exactly count + 1 = 2 rows survive; never all five, never one.
    expect(PasswordHistory::query()->count())->toBe(2);
});

it('keeps every entry when the count is zero (no pruning), even across many records', function (): void {
    config(['laravel-password-rotation.history_count' => 0]);

    $user = UserFactory::new()->create();

    // create() recorded nothing (history disabled), so start from zero and add
    // several rows; none may be pruned while the window is zero.
    foreach (range(1, 3) as $i) {
        PasswordHistory::recordFor($user);
    }

    expect(PasswordHistory::query()->count())->toBe(3);
});

it('detects a reused password among the recent hashes', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create(); // password === 'password'

    expect(PasswordHistory::isReused($user, 'password'))->toBeTrue()
        ->and(PasswordHistory::isReused($user, 'never-used-before'))->toBeFalse();
});

it('never reports reuse when the count is zero or negative', function (): void {
    $user = UserFactory::new()->create();

    expect(PasswordHistory::isReused($user, 'password', 0))->toBeFalse()
        ->and(PasswordHistory::isReused($user, 'password', -3))->toBeFalse();
});

it('falls back to the configured history count when none is given', function (): void {
    config(['laravel-password-rotation.history_count' => 0]);

    $user = UserFactory::new()->create();
    PasswordHistory::recordFor($user); // force a row in even though history is off

    // With the config default of 0 and no explicit count, reuse is never reported.
    expect(PasswordHistory::isReused($user, 'password'))->toBeFalse();
});

it('rejects the current password even with a window of exactly one', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create(); // records the current hash of 'password'

    // A window of one still covers the current password (count + 1 = 2 hashes).
    expect(PasswordHistory::isReused($user, 'password', 1))->toBeTrue();
});

it('rejects exactly the current password plus the previous $count, accepting anything older', function (): void {
    // A large configured count disables pruning so the whole history survives;
    // the window under test is then the explicit $count passed to isReused().
    config(['laravel-password-rotation.history_count' => 100]);

    $user = UserFactory::new()->create(); // current: 'password'

    // Rotate through four distinct strong passwords (oldest to newest).
    foreach (['Aa1!aaaaaa', 'Bb2!bbbbbb', 'Cc3!cccccc', 'Dd4!dddddd'] as $password) {
        $fresh = User::query()->findOrFail($user->getKey());
        $fresh->password = $password;
        $fresh->save();
    }
    // History (oldest to newest): password, Aa, Bb, Cc, Dd — current is Dd.

    // With $count = 2 the window is the current password plus the two previous.
    expect(PasswordHistory::isReused($user, 'Dd4!dddddd', 2))->toBeTrue()  // current
        ->and(PasswordHistory::isReused($user, 'Cc3!cccccc', 2))->toBeTrue()  // 1 change ago
        ->and(PasswordHistory::isReused($user, 'Bb2!bbbbbb', 2))->toBeTrue()  // 2 changes ago (edge of window)
        ->and(PasswordHistory::isReused($user, 'Aa1!aaaaaa', 2))->toBeFalse() // 3 changes ago (just outside)
        ->and(PasswordHistory::isReused($user, 'password', 2))->toBeFalse();  // 4 changes ago
});

it('scopes reuse detection to the given authenticatable', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $one = UserFactory::new()->create();
    $two = UserFactory::new()->create();

    // Rotate $two to a distinct password so its history differs.
    $two->password = 'a-secret-for-two';
    $two->save();

    expect(PasswordHistory::isReused($one, 'a-secret-for-two'))->toBeFalse()
        ->and(PasswordHistory::isReused($two, 'a-secret-for-two'))->toBeTrue();
});

it('exposes the polymorphic authenticatable relation', function (): void {
    $user = UserFactory::new()->create();

    $history = PasswordHistory::query()->first();

    expect($history->authenticatable)->toBeInstanceOf(User::class)
        ->and($history->authenticatable->is($user))->toBeTrue();
});
