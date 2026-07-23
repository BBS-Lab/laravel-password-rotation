<?php

declare(strict_types=1);

use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

// End-to-end coverage of the `composer serve` demo (workbench/routes/web.php +
// WorkbenchServiceProvider): it drives the middleware, the reuse rule and the
// trait through real routes, and guards the demo against wiring regressions.

function demoExpiredUser(): User
{
    return UserFactory::new()->create(['password_changed_at' => now()->subDays(120)]);
}

it('bounces an expired user from the app to the rotate screen', function (): void {
    $this->actingAs(demoExpiredUser())
        ->get('/dashboard')
        ->assertRedirect(route('password.rotate'));
});

it('lets an expired user rotate and then reach the app', function (): void {
    $user = demoExpiredUser();

    $this->actingAs($user)
        ->from(route('password.rotate'))
        ->post('/password/rotate', [
            'current_password' => 'password',
            'password' => 'N3wpass!23',
            'password_confirmation' => 'N3wpass!23',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('status');

    expect($user->fresh()->passwordHasExpired())->toBeFalse();

    $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
});

it('rejects reusing the current password on the rotate screen', function (): void {
    $this->actingAs(demoExpiredUser())
        ->from(route('password.rotate'))
        ->post('/password/rotate', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertRedirect(route('password.rotate'))
        ->assertSessionHasErrors('password');
});

it('lets an expired user sign out (except_routes escape hatch)', function (): void {
    $this->actingAs(demoExpiredUser())
        ->post('/logout')
        ->assertRedirect(route('login'));

    expect(auth()->check())->toBeFalse();
});
