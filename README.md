# Laravel Password Rotation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/bbs-lab/laravel-password-rotation.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/laravel-password-rotation)
[![Tests](https://img.shields.io/github/actions/workflow/status/BBS-Lab/laravel-password-rotation/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/BBS-Lab/laravel-password-rotation/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/bbs-lab/laravel-password-rotation.svg?style=flat-square)](https://packagist.org/packages/bbs-lab/laravel-password-rotation)

Password rotation for any Laravel application — **no admin panel required**. Force any authenticatable
model to **rotate its password every N days**, with reuse prevention, a first-login gate, an
expiry-warning helper and a redirect middleware. No Nova, no Filament.

This is the shared core behind the two admin-panel twins:

- [`bbs-lab/nova-password-rotation`](https://github.com/BBS-Lab/nova-password-rotation) — Laravel Nova
- [`bbs-lab/filament-password-rotation`](https://github.com/BBS-Lab/filament-password-rotation) — Filament

The rotatable subject is **not** tied to the `User` model: everything keys off the
`MustRotatePassword` interface on whatever authenticatable you gate, and the password history is
**polymorphic**, so any model works.

## Requirements

- PHP `^8.2`
- Laravel `^11.0 || ^12.0 || ^13.0`

## Installation

```bash
composer require bbs-lab/laravel-password-rotation
```

The service provider auto-registers via Laravel package discovery.

The `password_histories` table migrates automatically. Publish the config and the
(review-before-run) user-column migration, then migrate:

```bash
# Config
php artisan vendor:publish --tag=laravel-password-rotation-config

# Adds the password_changed_at column to your authenticatable's table.
# Rename/edit it first — your rotatable model rarely lives on "users".
php artisan vendor:publish --tag=laravel-password-rotation-user-migration

# Optional — only to customise the shipped password_histories migration
# php artisan vendor:publish --tag=laravel-password-rotation-migrations

# Translations (en, fr) — only to customise the messages
php artisan vendor:publish --tag=laravel-password-rotation-translations

php artisan migrate
```

## Usage

### Make a model rotatable

Implement `MustRotatePassword` and use the `RotatesPassword` trait on any
authenticatable model. Nothing is tied to `User` — the trait keys off the
interface and the history table is polymorphic.

```php
use BBSLab\LaravelPasswordRotation\Concerns\RotatesPassword;
use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustRotatePassword
{
    use RotatesPassword;
}
```

The trait stamps `password_changed_at` whenever the password changes, records
each hash for reuse prevention, and dispatches `PasswordRotated`. It exposes:

- `passwordHasExpired(): bool` — past the rotation window (or never set, when `force_on_first_login` is on)
- `passwordIsExpiring(): bool` — inside the warning window (`warn_days`)
- `passwordExpiresAt(): ?CarbonInterface`

### Force expired users to a change screen

Add `EnsurePasswordIsNotExpired` to the middleware group that guards your app
and point `redirect_route` at your own password-change screen:

```php
// config/laravel-password-rotation.php
'redirect_route' => 'password.rotate',

// keep users able to sign out while their password is expired
'except_routes' => ['logout'],
```

```php
Route::middleware(['web', 'auth', EnsurePasswordIsNotExpired::class])->group(function () {
    // ...your app
});
```

An expired user is redirected to `redirect_route` on every request except that
route and anything in `except_routes`. **Exempt your logout route** (and, if the
change form POSTs to a different route, that route too) or the user is trapped
with no way out. The middleware reads the default guard's user; on a custom
guard, wire it accordingly.

### Prevent password reuse

Add the `PasswordNotReused` rule to your change-password validation:

```php
use BBSLab\LaravelPasswordRotation\Rules\PasswordNotReused;

$request->validate([
    'password' => ['required', 'confirmed', Password::defaults(), new PasswordNotReused($request->user())],
]);
```

It rejects the current password and the last `history_count` hashes.

### Report on rotation status

```bash
php artisan password-rotation:report        # expired / expiring-soon accounts
php artisan password-rotation:report --all  # every account
```

The command scans the classes listed under `models` in the config.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
