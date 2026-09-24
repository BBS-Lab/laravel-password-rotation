<?php

declare(strict_types=1);

use BBSLab\LaravelForceTwoFactor\Facades\ForceTwoFactor;
use Illuminate\Http\Request;
use Workbench\App\Models\User;

it('exempts a user who still owes a forced rotation from forced 2FA', function (): void {
    // A fresh user has a null password_changed_at, treated as expired via
    // force_on_first_login — the rotation middleware would redirect them, so the
    // 2FA gate must let them rotate first.
    expect(ForceTwoFactor::shouldBypass(Request::create('/'), new User))->toBeTrue();
});

it('does not exempt a user whose password is still valid', function (): void {
    $user = new User;
    $user->setAttribute('password_changed_at', now());

    expect(ForceTwoFactor::shouldBypass(Request::create('/'), $user))->toBeFalse();
});

it('does not exempt anyone when password rotation is disabled', function (): void {
    config(['laravel-password-rotation.enabled' => false]);

    expect(ForceTwoFactor::shouldBypass(Request::create('/'), new User))->toBeFalse();
});
