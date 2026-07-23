<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $column = (string) config('laravel-password-rotation.column', 'password_changed_at');

        Schema::table('users', function (Blueprint $table) use ($column): void {
            $table->timestamp($column)->nullable()->after('password');
        });
    }

    public function down(): void
    {
        $column = (string) config('laravel-password-rotation.column', 'password_changed_at');

        Schema::table('users', function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }
};
