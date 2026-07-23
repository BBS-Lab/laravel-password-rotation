<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\User;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    /**
     * Two logins for the `composer serve` demo (both password "password"):
     * one whose password is past the rotation window so the forced-change
     * screen shows immediately, and one still valid. The trait leaves the
     * explicit password_changed_at we pass untouched at create time.
     */
    public function run(): void
    {
        User::query()->delete();

        UserFactory::new()->create([
            'name' => 'Expired User',
            'email' => 'expired@example.com',
            'password_changed_at' => now()->subDays(120),
        ]);

        UserFactory::new()->create([
            'name' => 'Fresh User',
            'email' => 'fresh@example.com',
            'password_changed_at' => now(),
        ]);
    }
}
