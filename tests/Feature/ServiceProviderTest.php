<?php

declare(strict_types=1);

use BBSLab\LaravelPasswordRotation\Console\Commands\PasswordRotationReport;
use BBSLab\LaravelPasswordRotation\LaravelPasswordRotationServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('registers the service provider', function (): void {
    expect(app()->getProviders(LaravelPasswordRotationServiceProvider::class))->not->toBeEmpty();
});

it('merges the package configuration', function (): void {
    expect(config('laravel-password-rotation.days'))->toBe(90)
        ->and(config('laravel-password-rotation.column'))->toBe('password_changed_at');
});

it('registers the report command', function (): void {
    expect(Artisan::all())->toHaveKey('password-rotation:report')
        ->and(Artisan::all()['password-rotation:report'])->toBeInstanceOf(PasswordRotationReport::class);
});

it('loads the package translations under the package namespace', function (): void {
    expect(trans('laravel-password-rotation::validation.reused'))
        ->not->toBe('laravel-password-rotation::validation.reused')
        ->toContain('already been used');
});

it('loads the French translations', function (): void {
    app()->setLocale('fr');

    expect(trans('laravel-password-rotation::validation.reused'))
        ->not->toBe('laravel-password-rotation::validation.reused')
        ->not->toBe(trans('laravel-password-rotation::validation.reused', [], 'en'));
});

it('runs the password_histories migration for the suite', function (): void {
    expect(Schema::hasTable('password_histories'))->toBeTrue()
        ->and(Schema::hasColumns('password_histories', [
            'id',
            'authenticatable_type',
            'authenticatable_id',
            'password',
            'created_at',
        ]))->toBeTrue();
});

it('publishes the first-login backfill migration under its own tag', function (): void {
    $paths = ServiceProvider::pathsToPublish(
        LaravelPasswordRotationServiceProvider::class,
        'laravel-password-rotation-user-migration',
    );

    expect($paths)->not->toBeEmpty();

    $source = (string) array_key_first($paths);
    $target = (string) $paths[$source];

    expect($source)->toEndWith('database/migrations/add_password_changed_at_to_users_table.php.stub')
        ->and(is_file($source))->toBeTrue() // absolute path (built off __DIR__) to a real stub
        ->and($target)->toEndWith('_add_password_changed_at_to_users_table.php')
        ->and($target)->toContain('database'.DIRECTORY_SEPARATOR.'migrations');
});
