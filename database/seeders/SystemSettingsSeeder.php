<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Seed the single row of system wide configuration.
     */
    public function run(): void
    {
        SystemSetting::query()->firstOrCreate(
            ['id' => SystemSetting::SINGLETON_ID],
            SystemSetting::DEFAULTS,
        );
    }
}
