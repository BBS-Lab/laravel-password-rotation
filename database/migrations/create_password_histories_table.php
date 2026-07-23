<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table): void {
            $table->id();

            match (config('laravel-password-rotation.morph_key_type')) {
                'uuid' => $table->uuidMorphs('authenticatable'),
                'ulid' => $table->ulidMorphs('authenticatable'),
                default => $table->morphs('authenticatable'),
            };

            $table->string('password');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};
