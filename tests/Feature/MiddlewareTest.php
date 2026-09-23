<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Facades\PasswordRotation;
use BBSLab\LaravelPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;
use Workbench\Database\Factories\AdminFactory;
use Workbench\Database\Factories\UserFactory;

beforeEach(function (): void {
    config([
        'laravel-password-rotation.enabled' => true,
        'laravel-password-rotation.days' => 90,
        'laravel-password-rotation.force_on_first_login' => true,
        'laravel-password-rotation.redirect_route' => 'password.rotate',
    ]);

    Route::middleware(['web', EnsurePasswordIsNotExpired::class])->group(function (): void {
        Route::get('/home', fn () => 'home');
        Route::get('/password/rotate', fn () => 'rotate here')->name('password.rotate');
        Route::get('/logout', fn () => 'bye')->name('logout');
    });
});

function expiredUser(): User
{
    return UserFactory::new()->create(['password_changed_at' => now()->subDays(100)]);
}

it('redirects an expired user to the configured route', function (): void {
    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertRedirect(route('password.rotate'));
});

it('lets a still-valid user through', function (): void {
    $this->actingAs(UserFactory::new()->create(['password_changed_at' => now()]))
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('ignores users that do not implement the interface', function (): void {
    $this->actingAs(AdminFactory::new()->create())
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('is inert when the feature is disabled', function (): void {
    config(['laravel-password-rotation.enabled' => false]);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('lets a self-declared expired user through when the feature is disabled', function (): void {
    config(['laravel-password-rotation.enabled' => false]);

    // A user that reports itself expired regardless of config; only the
    // middleware's own "disabled" short-circuit can let this request through.
    $user = new class extends User implements MustRotatePassword
    {
        public function passwordHasExpired(): bool
        {
            return true;
        }
    };

    $this->actingAs($user)
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('passes every request through when no redirect route is configured', function (): void {
    config(['laravel-password-rotation.redirect_route' => null]);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('passes every request through when the redirect route is an empty string', function (): void {
    config(['laravel-password-rotation.redirect_route' => '']);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('never traps the user on the redirect target route itself', function (): void {
    $this->actingAs(expiredUser())
        ->get('/password/rotate')
        ->assertOk()
        ->assertSee('rotate here');
});

it('lets an expired user reach an exempted route such as logout', function (): void {
    config(['laravel-password-rotation.except_routes' => ['logout']]);

    $this->actingAs(expiredUser())
        ->get('/logout')
        ->assertOk()
        ->assertSee('bye');
});

it('still traps an expired user on a non-exempted route', function (): void {
    config(['laravel-password-rotation.except_routes' => ['logout']]);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertRedirect(route('password.rotate'));
});

it('lets an expired user through when a bypass callback returns true', function (): void {
    PasswordRotation::bypass(fn (): bool => true);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('still redirects an expired user when the bypass callback returns false', function (): void {
    PasswordRotation::bypass(fn (): bool => false);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertRedirect(route('password.rotate'));
});

it('bypasses as soon as any registered callback returns true', function (): void {
    PasswordRotation::bypass(fn (): bool => false);
    PasswordRotation::bypass(fn (): bool => true);

    $this->actingAs(expiredUser())
        ->get('/home')
        ->assertOk()
        ->assertSee('home');
});

it('hands the request and the expired user to the bypass callback (SSO recipe)', function (): void {
    config(['session.driver' => 'array']);

    $received = null;

    PasswordRotation::bypass(function (Request $request, MustRotatePassword $user) use (&$received): bool {
        $received = [$request, $user];

        return $request->session()->get('sso') === true;
    });

    $this->actingAs(expiredUser())
        ->withSession(['sso' => true])
        ->get('/home')
        ->assertOk()
        ->assertSee('home');

    expect($received[0])->toBeInstanceOf(Request::class)
        ->and($received[1])->toBeInstanceOf(MustRotatePassword::class);
});
