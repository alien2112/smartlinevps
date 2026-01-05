<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert travel recommended multiplier setting
        $exists = DB::table('business_settings')
            ->where('key_name', 'travel_recommended_multiplier')
            ->exists();

        if (!$exists) {
            DB::table('business_settings')->insert([
                'id' => Str::uuid()->toString(),
                'key_name' => 'travel_recommended_multiplier',
                'value' => json_encode(['status' => 1, 'value' => 1.2]), // 20% above min_price by default
                'settings_type' => 'trip_settings',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('business_settings')
            ->where('key_name', 'travel_recommended_multiplier')
            ->delete();
    }
};
