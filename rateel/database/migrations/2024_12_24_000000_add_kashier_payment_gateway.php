<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if Kashier payment gateway already exists
        $exists = DB::table('settings')
            ->where('key_name', 'kashier')
            ->where('settings_type', 'payment_config')
            ->exists();

        if (!$exists) {
            // Insert Kashier payment gateway configuration
            // Matches the existing database structure from production
            DB::table('settings')->insert([
                'id' => \Ramsey\Uuid\Uuid::uuid4()->toString(),
                'key_name' => 'kashier',
                'live_values' => json_encode([
                    'gateway' => 'kashier',
                    'mode' => 'test',
                    'gateway_title' => 'Kashier Payment',
                    'status' => '0',
                    'callback_url' => '',
                    'secret_key' => '',
                    'merchant_id' => '',
                    'public_key' => '',
                    'supported_country' => 'egypt',
                ]),
                'test_values' => json_encode([
                    'gateway' => 'kashier',
                    'mode' => 'test',
                    'gateway_title' => 'Kashier Payment',
                    'status' => '0',
                    'callback_url' => '',
                    'secret_key' => '',
                    'merchant_id' => '',
                    'public_key' => '',
                    'supported_country' => 'egypt',
                ]),
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'is_active' => 0,
                'additional_data' => json_encode([
                    'gateway_title' => 'Kashier Payment',
                    'gateway_image' => 'kashier.png',
                ]),
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
        DB::table('settings')
            ->where('key_name', 'kashier')
            ->where('settings_type', 'payment_config')
            ->delete();
    }
};
