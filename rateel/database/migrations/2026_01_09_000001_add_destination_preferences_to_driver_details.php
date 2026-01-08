<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // JSON array to store up to 3 destination preferences
            // Structure: [{"id": 1, "latitude": 24.7136, "longitude": 46.6753, "address": "...", "radius_km": 10.0, "created_at": "..."}]
            $table->json('destination_preferences')->nullable()
                ->after('travel_rejection_reason')
                ->comment('Array of up to 3 destination preferences with lat/lng/address/radius');

            // Boolean toggle to enable/disable destination filtering
            $table->boolean('destination_filter_enabled')->default(false)
                ->after('destination_preferences')
                ->comment('Enable/disable destination preference filtering');

            // Global radius setting (1-15 km)
            $table->decimal('destination_radius_km', 5, 2)->default(5.00)
                ->after('destination_filter_enabled')
                ->comment('Default radius in kilometers (1-15 km)');

            // Index for fast filtering lookup
            $table->index(['destination_filter_enabled'], 'idx_destination_filter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex('idx_destination_filter');
            $table->dropColumn([
                'destination_preferences',
                'destination_filter_enabled',
                'destination_radius_km',
            ]);
        });
    }
};
