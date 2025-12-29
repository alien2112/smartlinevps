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
        // Insert normal trip pricing settings into business_settings
        $settings = [
            [
                'key_name' => 'normal_price_per_km',
                'value' => ['status' => 0, 'value' => 5.0], // 5 per km (disabled by default - uses existing fare engine)
                'settings_type' => 'trip_settings',
            ],
            [
                'key_name' => 'normal_min_price',
                'value' => ['status' => 0, 'value' => 20.0], // Minimum 20 (disabled by default)
                'settings_type' => 'trip_settings',
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
            'normal_price_per_km',
            'normal_min_price',
        ])->delete();
    }
};
