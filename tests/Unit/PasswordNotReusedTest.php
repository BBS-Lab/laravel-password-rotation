<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Rules\PasswordNotReused;
use Illuminate\Translation\PotentiallyTranslatedString;
use Workbench\Database\Factories\UserFactory;

/**
 * @return array{0: bool, 1: ?string}
 */
function runReuseRule(PasswordNotReused $rule, mixed $value): array
{
    $message = null;

    $rule->validate('password', $value, function (string $msg) use (&$message): PotentiallyTranslatedString {
        $message = $msg;

        return new PotentiallyTranslatedString($msg, app('translator'));
    });

    return [$message !== null, $message];
}

it('fails when the password was used before', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create(); // password === 'password'

    [$failed, $message] = runReuseRule(new PasswordNotReused($user), 'password');

    expect($failed)->toBeTrue()
        ->and($message)->toBe('laravel-password-rotation::validation.reused');
});

it('passes when the password is new', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create();

    [$failed] = runReuseRule(new PasswordNotReused($user), 'a-genuinely-new-secret');

    expect($failed)->toBeFalse();
});

it('passes when the value is not a string', function (): void {
    config(['laravel-password-rotation.history_count' => 3]);

    $user = UserFactory::new()->create();

    [$failed] = runReuseRule(new PasswordNotReused($user), 12345);

    expect($failed)->toBeFalse();
});
