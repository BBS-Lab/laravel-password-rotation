# Changelog

All notable changes to `laravel-password-rotation` will be documented in this file.

## v1.1.0 - 2026-07-23

The `password_histories` table now migrates automatically — no manual publish + migrate step. Backward compatible.

### ✨ Changed

- **Auto-run migration** — the `password_histories` table is created automatically on `php artisan migrate`; consumers (and the Nova/Filament twins) no longer need to publish it first. The migration guards against a double-create, so publishing it to customise the table still works.

### 📚 Documentation

- README and the Boost guideline updated to reflect the auto-run migration.

## v1.0.0 - 2026-07-23

First release of the admin-panel-agnostic core behind the Nova and Filament password-rotation twins. Force any Laravel authenticatable to rotate its password on a schedule — no admin panel required.

### ✨ Features

- **Rotation on any model** — implement `MustRotatePassword` and use the `RotatesPassword` trait on any authenticatable; nothing is tied to `User`. The trait stamps the change timestamp, records history and dispatches `PasswordRotated`.
- **Reuse prevention** — the `PasswordNotReused` validation rule rejects the current password and the last N hashes (polymorphic `password_histories` table).
- **First-login gate** — optionally treat admin-provisioned accounts (null timestamp) as expired until they set their own password.
- **Expiry warnings** — `passwordIsExpiring()` opens a configurable warning window before the password actually expires.
- **Redirect middleware** — `EnsurePasswordIsNotExpired` bounces expired users to your change screen, with a configurable `redirect_route` and `except_routes` (so logout always works, even while expired).
- **Reporting** — the `password-rotation:report` command lists expired / expiring-soon accounts.
- **Lightweight Blade demo** — `composer serve` boots a real Laravel app (session auth + forced-change flow) with no admin panel.
- **Laravel Boost guideline** — ships an AI guideline so consumers' coding agents wire the package correctly out of the box.

### 📦 Requirements

- PHP `^8.2`
- Laravel `^11.0 || ^12.0 || ^13.0`

### 🧪 Quality

- 100% line coverage, mutation score ~89%, PHPStan level 8, Pint (strict types).
- CI matrix: Laravel 11/12/13 × PHP 8.3/8.4.

### 🔗 Admin-panel twins

- [`bbs-lab/nova-password-rotation`](https://github.com/BBS-Lab/nova-password-rotation) — Laravel Nova
- [`bbs-lab/filament-password-rotation`](https://github.com/BBS-Lab/filament-password-rotation) — Filament
