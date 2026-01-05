<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert travel pricing settings into business_settings with UUIDs
        $settings = [
            [
                'key_name' => 'travel_price_per_km',
                'value' => ['status' => 1, 'value' => 10.0], // 10 per km (adjust based on your currency)
                'settings_type' => 'trip_settings',
            ],
            [
                'key_name' => 'travel_price_multiplier',
                'value' => ['status' => 0, 'value' => 1.0], // Disabled by default
                'settings_type' => 'trip_settings',
            ],
            [
                'key_name' => 'travel_search_radius',
                'value' => ['status' => 1, 'value' => 50], // 50 km radius for VIP drivers
                'settings_type' => 'driver_settings',
            ],
        ];

        foreach ($settings as $setting) {
            // Check if setting already exists
            $exists = DB::table('business_settings')
                ->where('key_name', $setting['key_name'])
                ->exists();

            if (!$exists) {
                DB::table('business_settings')->insert([
                    'id' => Str::uuid()->toString(),
                    'key_name' => $setting['key_name'],
                    'value' => json_encode($setting['value']),
                    'settings_type' => $setting['settings_type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('business_settings')->whereIn('key_name', [
            'travel_price_per_km',
            'travel_price_multiplier',
            'travel_search_radius',
        ])->delete();
    }
};
