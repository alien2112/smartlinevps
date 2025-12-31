<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration for High Priority Performance Indexes (Issues #12, #13)
 *
 * From Architecture Audit Report31:
 * - Issue #12: Missing indexes on trip_requests table
 * - Issue #13: Parcel weight range lookup optimization
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Issue #12: Critical indexes for trip_requests table
        // These indexes support common query patterns identified in the audit

        // Check and add composite index for customer + status queries
        if (!$this->indexExists('trip_requests', 'idx_trip_customer_status')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['customer_id', 'current_status'], 'idx_trip_customer_status');
            });
        }

        // Index for driver + status queries
        if (!$this->indexExists('trip_requests', 'idx_trip_driver_status')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['driver_id', 'current_status'], 'idx_trip_driver_status');
            });
        }

        // Index for zone + created_at queries (reports, analytics)
        if (!$this->indexExists('trip_requests', 'idx_trip_zone_created')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['zone_id', 'created_at'], 'idx_trip_zone_created');
            });
        }

        // Index for status + created_at queries (listings, reports)
        if (!$this->indexExists('trip_requests', 'idx_trip_status_created')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['current_status', 'created_at'], 'idx_trip_status_created');
            });
        }

        // Index for customer + type + status (resume ride queries)
        if (!$this->indexExists('trip_requests', 'idx_trip_customer_type_status')) {
            Schema::table('trip_requests', function (Blueprint $table) {
                $table->index(['customer_id', 'type', 'current_status'], 'idx_trip_customer_type_status');
            });
        }

        // Issue #13: Index for parcel weight range lookups
        if (Schema::hasTable('parcel_weights') && !$this->indexExists('parcel_weights', 'idx_parcel_weight_range')) {
            Schema::table('parcel_weights', function (Blueprint $table) {
                $table->index(['min_weight', 'max_weight'], 'idx_parcel_weight_range');
            });
        }

        // Index for fare_biddings table (Issue #6 from critical section)
        if (Schema::hasTable('fare_biddings')) {
            if (!$this->indexExists('fare_biddings', 'idx_bidding_trip_ignored')) {
                Schema::table('fare_biddings', function (Blueprint $table) {
                    $table->index(['trip_request_id', 'is_ignored', 'created_at'], 'idx_bidding_trip_ignored');
                });
            }

            if (!$this->indexExists('fare_biddings', 'idx_bidding_driver_trip')) {
                Schema::table('fare_biddings', function (Blueprint $table) {
                    $table->index(['driver_id', 'trip_request_id'], 'idx_bidding_driver_trip');
                });
            }
        }

        // Index for user_last_locations (spatial query optimization)
        if (Schema::hasTable('user_last_locations')) {
            if (!$this->indexExists('user_last_locations', 'idx_location_zone_lat_lng')) {
                Schema::table('user_last_locations', function (Blueprint $table) {
                    $table->index(['zone_id', 'latitude', 'longitude'], 'idx_location_zone_lat_lng');
                });
            }
        }

        // Index for channel_conversations (Issue #20: Chat pagination)
        if (Schema::hasTable('channel_conversations')) {
            if (!$this->indexExists('channel_conversations', 'idx_conversation_channel_created')) {
                Schema::table('channel_conversations', function (Blueprint $table) {
                    $table->index(['channel_id', 'created_at', 'id'], 'idx_conversation_channel_created');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        $indexes = [
            'trip_requests' => [
                'idx_trip_customer_status',
                'idx_trip_driver_status',
                'idx_trip_zone_created',
                'idx_trip_status_created',
                'idx_trip_customer_type_status',
            ],
            'parcel_weights' => ['idx_parcel_weight_range'],
            'fare_biddings' => ['idx_bidding_trip_ignored', 'idx_bidding_driver_trip'],
            'user_last_locations' => ['idx_location_zone_lat_lng'],
            'channel_conversations' => ['idx_conversation_channel_created'],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($tableIndexes) {
                    foreach ($tableIndexes as $index) {
                        try {
                            $table->dropIndex($index);
                        } catch (\Exception $e) {
                            // Index may not exist
                        }
                    }
                });
            }
        }
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
