<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | When disabled, the package becomes completely inert: the middleware never
    | forces a password change and no expiry warnings are shown. Handy for
    | local development or staging environments.
    |
    */

    'enabled' => env('PASSWORD_ROTATION_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Password history morph key type
    |--------------------------------------------------------------------------
    |
    | The key type for the "password_histories.authenticatable_id" column. Leave
    | null to follow Laravel's global default (Schema::defaultMorphKeyType()),
    | which is a BIGINT unless you have switched it. Set to "uuid" or "ulid" if
    | your authenticatable uses string primary keys, so the migration provisions
    | a matching column instead of silently truncating string keys.
    |
    */

    'morph_key_type' => env('PASSWORD_ROTATION_MORPH_KEY_TYPE'),

    /*
    |--------------------------------------------------------------------------
    | Rotation period (days)
    |--------------------------------------------------------------------------
    |
    | How many days a password stays valid before the user is forced to change
    | it. Counted from the value stored in the "column" below.
    |
    */

    'days' => (int) env('PASSWORD_ROTATION_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Timestamp column
    |--------------------------------------------------------------------------
    |
    | The column, on any model implementing MustRotatePassword, that stores the
    | moment the password was last changed. Override it in the model via
    | passwordRotationColumn() if a single value is not enough.
    |
    */

    'column' => env('PASSWORD_ROTATION_COLUMN', 'password_changed_at'),

    /*
    |--------------------------------------------------------------------------
    | Force a change on first login
    |--------------------------------------------------------------------------
    |
    | When true, a null timestamp is treated as "expired", so admin-provisioned
    | accounts must set their own password before proceeding. The publishable
    | migration backfills existing rows to now() to avoid a mass lock-out.
    |
    */

    'force_on_first_login' => env('PASSWORD_ROTATION_FORCE_FIRST_LOGIN', true),

    /*
    |--------------------------------------------------------------------------
    | Require the current password
    |--------------------------------------------------------------------------
    |
    | Require users to confirm their current password on the change screen.
    | Recommended; disable only if you have a good reason to.
    |
    */

    'require_current_password' => env('PASSWORD_ROTATION_REQUIRE_CURRENT', true),

    /*
    |--------------------------------------------------------------------------
    | Password reuse prevention
    |--------------------------------------------------------------------------
    |
    | Number of previous passwords remembered (hashed) and rejected on the
    | change screen. Set to 0 to disable history entirely.
    |
    */

    'history_count' => (int) env('PASSWORD_ROTATION_HISTORY_COUNT', 3),

    /*
    |--------------------------------------------------------------------------
    | Expiry warning window (days)
    |--------------------------------------------------------------------------
    |
    | passwordIsExpiring() returns true this many days before the password
    | actually expires, so you can surface a warning. Set to 0 to disable.
    |
    */

    'warn_days' => (int) env('PASSWORD_ROTATION_WARN_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Rotatable models
    |--------------------------------------------------------------------------
    |
    | The authenticatable models the "password-rotation:report" command scans.
    | The middleware itself does not use this list: it works off the interface
    | on whatever user is authenticated, so any model is supported. Only classes
    | that exist and implement MustRotatePassword are actually scanned.
    |
    */

    'models' => [
        'App\\Models\\User',
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect route
    |--------------------------------------------------------------------------
    |
    | The named route EnsurePasswordIsNotExpired sends a user to when their
    | password has expired (your own password-change screen). Leave null to
    | disable the redirect entirely — the middleware then passes every request
    | through untouched.
    |
    */

    'redirect_route' => env('PASSWORD_ROTATION_REDIRECT_ROUTE'),

    /*
    |--------------------------------------------------------------------------
    | Exempt routes
    |--------------------------------------------------------------------------
    |
    | Route names (Route::is() patterns, wildcards allowed) that
    | EnsurePasswordIsNotExpired never redirects, even for an expired user. The
    | redirect_route above is always exempt automatically; add your logout route
    | here so a user with an expired password can still sign out. If the change
    | screen posts to a route other than redirect_route, exempt it too.
    |
    */

    'except_routes' => [
        // 'logout',
    ],

];
