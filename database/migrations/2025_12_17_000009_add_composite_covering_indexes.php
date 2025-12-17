<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Composite covering indexes for advanced query optimization
     * These provide additional performance for common multi-filter queries
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            // Trip queries with common filters (covering index)
            // Optimizes queries that filter by customer + status and sort by date
            $table->index(['customer_id', 'current_status', 'created_at'], 'idx_trips_customer_status_created');

            // Trip queries by driver with status
            // Optimizes queries that filter by driver + status and sort by date
            $table->index(['driver_id', 'current_status', 'created_at'], 'idx_trips_driver_status_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trips_customer_status_created');
            $table->dropIndex('idx_trips_driver_status_created');
        });
    }
};
