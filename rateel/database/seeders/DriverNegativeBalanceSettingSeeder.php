<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Modules\BusinessManagement\Entities\BusinessSetting;

class DriverNegativeBalanceSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BusinessSetting::updateOrCreate(
            ['key_name' => 'default_driver_max_negative_balance'],
            [
                'value' => json_encode(200),
                'settings_type' => 'driver_settings',
            ]
        );
    }
}
