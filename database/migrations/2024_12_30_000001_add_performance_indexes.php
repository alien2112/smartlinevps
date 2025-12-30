<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Performance optimization indexes for production
 * 
 * These indexes optimize the most common queries:
 * - Pending ride list (driver app polling)
 * - Trip lookups by driver/customer
 * - Zone-based filtering
 */
return new class extends Migration
{
    public function up(): void
    {
        // Index for pending ride list query (critical for driver app performance)
        // Query: WHERE zone_id = ? AND current_status = 'pending' AND driver_id IS NULL
        if (!$this->indexExists('trip_requests', 'idx_trips_pending_lookup')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['zone_id', 'current_status', 'driver_id'], 'idx_trips_pending_lookup');
            });
        }

        // Index for driver's trips lookup
        // Query: WHERE driver_id = ? AND current_status IN (...)
        if (!$this->indexExists('trip_requests', 'idx_trips_driver_status')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['driver_id', 'current_status'], 'idx_trips_driver_status');
            });
        }

        // Index for customer's trips lookup
        // Query: WHERE customer_id = ? AND current_status IN (...)
        if (!$this->indexExists('trip_requests', 'idx_trips_customer_status')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['customer_id', 'current_status'], 'idx_trips_customer_status');
            });
        }

        // Index for rejected driver requests lookup (used in pending ride list)
        if (!$this->indexExists('rejected_driver_requests', 'idx_rejected_trip_user')) {
            Schema::table('rejected_driver_requests', function (Blueprint $table) {
                $table->index(['trip_request_id', 'user_id'], 'idx_rejected_trip_user');
            });
        }

        // Index for user last locations (driver location lookup)
        if (!$this->indexExists('user_last_locations', 'idx_locations_user_zone')) {
            Schema::table('user_last_locations', function (Blueprint $table) {
                $table->index(['user_id', 'zone_id'], 'idx_locations_user_zone');
            });
        }

        // Index for fare biddings lookup
        if (!$this->indexExists('fare_biddings', 'idx_biddings_trip_driver')) {
            Schema::table('fare_biddings', function (Blueprint $table) {
                $table->index(['trip_request_id', 'driver_id'], 'idx_biddings_trip_driver');
            });
        }

        // Index for trip request coordinates (spatial queries)
        if (!$this->indexExists('trip_request_coordinates', 'idx_coordinates_trip')) {
            Schema::table('trip_request_coordinates', function (Blueprint $table) {
                $table->index(['trip_request_id'], 'idx_coordinates_trip');
            });
        }
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trips_pending_lookup');
            $table->dropIndex('idx_trips_driver_status');
            $table->dropIndex('idx_trips_customer_status');
        });

        Schema::table('rejected_driver_requests', function (Blueprint $table) {
            $table->dropIndex('idx_rejected_trip_user');
        });

        Schema::table('user_last_locations', function (Blueprint $table) {
            $table->dropIndex('idx_locations_user_zone');
        });

        Schema::table('fare_biddings', function (Blueprint $table) {
            $table->dropIndex('idx_biddings_trip_driver');
        });

        Schema::table('trip_request_coordinates', function (Blueprint $table) {
            $table->dropIndex('idx_coordinates_trip');
        });
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'mysql') {
            $result = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($result) > 0;
        }

        if ($driver === 'pgsql') {
            $result = DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]);
            return count($result) > 0;
        }

        // For SQLite or other drivers, assume index doesn't exist
        return false;
    }
};
