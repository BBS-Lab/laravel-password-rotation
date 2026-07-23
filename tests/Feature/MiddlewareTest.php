<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
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
