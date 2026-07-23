<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

/**
 * Run the package's create_password_histories migration in isolation, honouring
 * whatever morph_key_type is currently configured, on a freshly dropped table.
 */
function runHistoryMigration(): void
{
    $migration = require __DIR__.'/../../database/migrations/create_password_histories_table.php';

    Schema::dropIfExists('password_histories');
    $migration->up();
}

it('provisions integer morph keys by default', function (): void {
    config(['laravel-password-rotation.morph_key_type' => null]);

    runHistoryMigration();

    expect(Schema::hasColumns('password_histories', ['authenticatable_type', 'authenticatable_id', 'password', 'created_at']))->toBeTrue()
        ->and(Schema::getColumnType('password_histories', 'authenticatable_id'))->toBe('integer');
});

it('provisions string morph keys for uuid authenticatables', function (): void {
    config(['laravel-password-rotation.morph_key_type' => 'uuid']);

    runHistoryMigration();

    expect(Schema::hasColumn('password_histories', 'authenticatable_id'))->toBeTrue()
        ->and(Schema::getColumnType('password_histories', 'authenticatable_id'))->toBe('varchar');
});

it('provisions string morph keys for ulid authenticatables', function (): void {
    config(['laravel-password-rotation.morph_key_type' => 'ulid']);

    runHistoryMigration();

    expect(Schema::hasColumn('password_histories', 'authenticatable_id'))->toBeTrue()
        ->and(Schema::getColumnType('password_histories', 'authenticatable_id'))->toBe('varchar');
});

it('rolls the table back down again', function (): void {
    $migration = require __DIR__.'/../../database/migrations/create_password_histories_table.php';

    $migration->down();

    expect(Schema::hasTable('password_histories'))->toBeFalse();
});
