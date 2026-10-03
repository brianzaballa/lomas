<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            NodeSeeder::class,
            SystemSettingsSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            LoanConfigurationSeeder::class,
        ]);
    }
}
