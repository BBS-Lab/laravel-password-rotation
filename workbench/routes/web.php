<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;
use BBSLab\LaravelPasswordRotation\Rules\PasswordNotReused;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Workbench\App\Models\User;

/*
 * A minimal Blade front-end for `composer serve`, so the package can be driven
 * end to end without any admin panel. Auth is hand-rolled on purpose — the demo
 * is about password rotation, not auth scaffolding. Seeded logins (password
 * "password"): expired@example.com (past the window) and fresh@example.com.
 */

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => view('demo::login'))->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    });
});

// The whole authenticated area is guarded. An expired user is bounced to
// password.rotate on every request except that screen (auto-exempt as the
// redirect target) and logout (listed in except_routes) — so they can always
// get out.
Route::middleware(['auth', EnsurePasswordIsNotExpired::class])->group(function (): void {
    Route::get('/dashboard', fn (Request $request) => view('demo::dashboard', [
        'user' => $request->user(),
    ]))->name('dashboard');

    Route::get('/password/rotate', fn () => view('demo::rotate'))->name('password.rotate');

    Route::post('/password/rotate', function (Request $request) {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8', new PasswordNotReused($user)],
        ]);

        $user->password = $request->string('password')->value();
        $user->save();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Password updated — you are no longer locked out.');
    })->name('password.rotate'); // same name as the GET form → auto-exempt from the middleware

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
