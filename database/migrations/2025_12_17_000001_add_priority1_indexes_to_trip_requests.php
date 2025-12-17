<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Priority 1 indexes for trip_requests table
     * Expected impact: Trip queries 5-10s -> <50ms
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            // Trip status queries (most common filter)
            // Used in: trip listings, driver pending rides, customer trip history
            $table->index(['current_status', 'created_at'], 'idx_trips_status_created');

            // Driver pending rides by zone (real-time critical)
            // Used in: GET /rides/pending endpoint
            $table->index(['zone_id', 'current_status'], 'idx_trips_zone_status');

            // Customer trip queries
            // Used in: customer trip history, active trips
            $table->index(['customer_id', 'current_status'], 'idx_trips_customer');

            // Driver trip queries
            // Used in: driver trip history, active trips
            $table->index(['driver_id', 'current_status'], 'idx_trips_driver');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trips_status_created');
            $table->dropIndex('idx_trips_zone_status');
            $table->dropIndex('idx_trips_customer');
            $table->dropIndex('idx_trips_driver');
        });
    }
};
