# bbs-lab/laravel-password-rotation

Forces any authenticatable model to rotate its password every N days, with reuse
prevention, a first-login gate, an expiry-warning helper and a redirect middleware.
No admin panel required — the Nova and Filament twins (`bbs-lab/nova-password-rotation`,
`bbs-lab/filament-password-rotation`) build their forced-change screens on top of this core.

## Key concepts

- **`MustRotatePassword`** (contract) + **`RotatesPassword`** (trait): opt any authenticatable model into rotation. Nothing is tied to `User`.
- **`PasswordHistory`**: polymorphic model storing the last N password hashes for reuse checks.
- **`EnsurePasswordIsNotExpired`**: middleware that redirects an expired user to your change screen.
- **`PasswordNotReused`**: validation rule rejecting the current password plus the last N.
- **expired vs expiring**: `passwordHasExpired()` is past the window; `passwordIsExpiring()` is inside the `warn_days` warning window.
- All config lives under the `laravel-password-rotation.*` namespace.

## Making a model rotatable

Implement the contract and use the trait. The trait stamps `password_changed_at` on every
password change, records each hash for reuse prevention, and dispatches `PasswordRotated`.

```php
use BBSLab\LaravelPasswordRotation\Concerns\RotatesPassword;
use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements MustRotatePassword
{
    use RotatesPassword;
}
```

Publish and run the migrations (the user-column migration is published, not auto-run — review
and rename it, since your rotatable model rarely lives on the `users` table):

```bash
php artisan vendor:publish --tag=laravel-password-rotation-migrations
php artisan vendor:publish --tag=laravel-password-rotation-user-migration
php artisan migrate
```

## Forcing expired users to a change screen

Point `redirect_route` at your own change screen and guard the authenticated area with the
middleware. **Exempt your logout route via `except_routes`**, or an expired user is trapped
with no way to sign out. If the change form POSTs to a route other than `redirect_route`,
give that route the same name or add it to `except_routes` too.

```php
// config/laravel-password-rotation.php
'redirect_route' => 'password.rotate',
'except_routes' => ['logout'],
```

```php
use BBSLab\LaravelPasswordRotation\Http\Middleware\EnsurePasswordIsNotExpired;

Route::middleware(['web', 'auth', EnsurePasswordIsNotExpired::class])->group(function () {
    // your protected app
});
```

## Preventing reuse

Add the rule to your change-password validation. It rejects the current password and the last
`history_count` hashes.

```php
use BBSLab\LaravelPasswordRotation\Rules\PasswordNotReused;

$request->validate([
    'password' => ['required', 'confirmed', new PasswordNotReused($request->user())],
]);
```

## Reporting

Scan the models listed under `models` in the config for expired / expiring accounts:

```bash
php artisan password-rotation:report        # only expired or expiring-soon
php artisan password-rotation:report --all  # every account
```

## Configuration

Key options in `config/laravel-password-rotation.php`:

| Key | Description |
|-----|-------------|
| `enabled` | Master switch; when false the middleware and warnings are inert |
| `days` | Days a password stays valid (default 90) |
| `column` | Timestamp column holding the last change (default `password_changed_at`) |
| `force_on_first_login` | Treat a null timestamp as expired (admin-provisioned accounts must set their own password) |
| `history_count` | Number of previous passwords remembered and rejected (0 disables history) |
| `warn_days` | How many days before expiry `passwordIsExpiring()` returns true (0 disables) |
| `require_current_password` | Whether the change screen should confirm the current password |
| `redirect_route` | Named route the middleware redirects expired users to (null disables the redirect) |
| `except_routes` | Route names the middleware never redirects — put your logout route here |
| `models` | Authenticatable classes the `password-rotation:report` command scans |
