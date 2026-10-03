<?php

namespace Database\Seeders;

use App\Domain\Access\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => config('lomas.admin.email')],
            [
                'name' => config('lomas.admin.name'),
                'password' => Hash::make(config('lomas.admin.password')),
                'email_verified_at' => now(),
            ],
        );

        $user->assignRole(Role::ADMINISTRATOR);
    }
}
