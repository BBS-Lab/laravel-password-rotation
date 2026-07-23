<?php

declare(strict_types=1);

namespace BBSLab\LaravelPasswordRotation;

use BBSLab\LaravelPasswordRotation\Console\Commands\PasswordRotationReport;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPasswordRotationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // spatie derives its "short name" by stripping the "laravel-" prefix
        // (Str::after($name, 'laravel-')), which would register the config and
        // translations under "password-rotation". The package's public contract
        // is the full "laravel-password-rotation" namespace that every class
        // reads from, so pin the config file name explicitly and load the
        // translations under that namespace by hand (see packageBooted()).
        $package
            ->name('laravel-password-rotation')
            ->hasConfigFile('laravel-password-rotation')
            ->hasMigration('create_password_histories_table')
            ->runsMigrations()
            ->hasCommand(PasswordRotationReport::class);
    }

    public function packageBooted(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravel-password-rotation');

        $this->publishes([
            __DIR__.'/../resources/lang' => lang_path('vendor/laravel-password-rotation'),
        ], 'laravel-password-rotation-translations');

        // The rotatable model rarely lives on the default "users" table, and the
        // column name is configurable, so this migration is published (not run)
        // for the user to review and rename before applying.
        $this->publishes([
            __DIR__.'/../database/migrations/add_password_changed_at_to_users_table.php.stub' => database_path(
                'migrations/'.date('Y_m_d_His').'_add_password_changed_at_to_users_table.php'
            ),
        ], 'laravel-password-rotation-user-migration');
    }
}
