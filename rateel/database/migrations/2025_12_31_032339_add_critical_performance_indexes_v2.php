<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * CRITICAL Performance Indexes Migration
 *
 * This migration adds essential indexes to prevent system failure at scale:
 * - Spatial indexes for driver location lookups
 * - Composite indexes for trip queries
 * - Indexes for bidding system
 * - Indexes for high-frequency query patterns
 */
return new class extends Migration
{
    public function up(): void
    {
        // =============================================================
        // 1. USER_LAST_LOCATIONS - Critical for driver proximity queries
        // =============================================================
        Schema::table('user_last_locations', function (Blueprint $table) {
            // Bounding box pre-filter index (much faster than pure distance calc)
            if (!$this->hasIndex('user_last_locations', 'idx_location_bbox')) {
                $table->index(['zone_id', 'type', 'latitude', 'longitude'], 'idx_location_bbox');
            }

            // User lookup index
            if (!$this->hasIndex('user_last_locations', 'idx_location_user_zone')) {
                $table->index(['user_id', 'zone_id'], 'idx_location_user_zone');
            }
        });

        // Add spatial index if location_point column exists
        try {
            DB::statement('CREATE SPATIAL INDEX idx_location_point ON user_last_locations(location_point)');
        } catch (\Exception $e) {
            // Index may already exist or column doesn't exist
        }

        // =============================================================
        // 2. TRIP_REQUESTS - Most queried table
        // =============================================================
        Schema::table('trip_requests', function (Blueprint $table) {
            // Customer's active/pending trips (resume ride feature)
            if (!$this->hasIndex('trip_requests', 'idx_trip_customer_status_type')) {
                $table->index(['customer_id', 'current_status', 'type'], 'idx_trip_customer_status_type');
            }

            // Driver's active trips
            if (!$this->hasIndex('trip_requests', 'idx_trip_driver_status')) {
                $table->index(['driver_id', 'current_status'], 'idx_trip_driver_status');
            }

            // Zone-based queries with date filtering (admin reports)
            if (!$this->hasIndex('trip_requests', 'idx_trip_zone_status_created')) {
                $table->index(['zone_id', 'current_status', 'created_at'], 'idx_trip_zone_status_created');
            }

            // Payment status queries
            if (!$this->hasIndex('trip_requests', 'idx_trip_payment_status')) {
                $table->index(['payment_status', 'current_status'], 'idx_trip_payment_status');
            }

            // Date range queries for reports
            if (!$this->hasIndex('trip_requests', 'idx_trip_created_status')) {
                $table->index(['created_at', 'current_status'], 'idx_trip_created_status');
            }
        });

        // =============================================================
        // 3. FARE_BIDDINGS - High contention during ride acceptance
        // =============================================================
        Schema::table('fare_biddings', function (Blueprint $table) {
            // Composite index for bid lookups
            if (!$this->hasIndex('fare_biddings', 'idx_bidding_trip_driver')) {
                $table->index(['trip_request_id', 'driver_id'], 'idx_bidding_trip_driver');
            }

            // Active bids for a trip
            if (!$this->hasIndex('fare_biddings', 'idx_bidding_trip_ignored')) {
                $table->index(['trip_request_id', 'is_ignored', 'created_at'], 'idx_bidding_trip_ignored');
            }
        });

        // =============================================================
        // 4. DRIVER_DETAILS - Availability checks
        // =============================================================
        Schema::table('driver_details', function (Blueprint $table) {
            // Online drivers lookup
            if (!$this->hasIndex('driver_details', 'idx_driver_online_status')) {
                $table->index(['is_online', 'availability_status'], 'idx_driver_online_status');
            }

            // User ID lookup (foreign key should have index)
            if (!$this->hasIndex('driver_details', 'idx_driver_user_online')) {
                $table->index(['user_id', 'is_online'], 'idx_driver_user_online');
            }
        });

        // =============================================================
        // 5. VEHICLES - Category and status lookups
        // =============================================================
        Schema::table('vehicles', function (Blueprint $table) {
            // Active vehicles by category
            if (!$this->hasIndex('vehicles', 'idx_vehicle_category_active')) {
                $table->index(['category_id', 'is_active'], 'idx_vehicle_category_active');
            }

            // Driver's vehicle lookup (column is driver_id, not user_id)
            if (!$this->hasIndex('vehicles', 'idx_vehicle_driver_active')) {
                $table->index(['driver_id', 'is_active'], 'idx_vehicle_driver_active');
            }
        });

        // =============================================================
        // 6. USERS - Driver/Customer lookups
        // =============================================================
        Schema::table('users', function (Blueprint $table) {
            // Active users by type
            if (!$this->hasIndex('users', 'idx_user_type_active')) {
                $table->index(['user_type', 'is_active'], 'idx_user_type_active');
            }
        });

        // =============================================================
        // 7. TRANSACTIONS - Financial reports
        // =============================================================
        Schema::table('transactions', function (Blueprint $table) {
            // User's transaction history
            if (!$this->hasIndex('transactions', 'idx_transaction_user_created')) {
                $table->index(['user_id', 'created_at'], 'idx_transaction_user_created');
            }

            // Transaction type lookup
            if (!$this->hasIndex('transactions', 'idx_transaction_type_created')) {
                $table->index(['transaction_type', 'created_at'], 'idx_transaction_type_created');
            }
        });

        // =============================================================
        // 8. CHANNEL_CONVERSATIONS - Chat message retrieval
        // =============================================================
        if (Schema::hasTable('channel_conversations')) {
            Schema::table('channel_conversations', function (Blueprint $table) {
                // Message pagination
                if (!$this->hasIndex('channel_conversations', 'idx_conversation_channel_created')) {
                    $table->index(['channel_id', 'created_at'], 'idx_conversation_channel_created');
                }
            });
        }

        // =============================================================
        // 9. TEMP_TRIP_NOTIFICATIONS - Cleanup queries
        // =============================================================
        if (Schema::hasTable('temp_trip_notifications')) {
            Schema::table('temp_trip_notifications', function (Blueprint $table) {
                if (!$this->hasIndex('temp_trip_notifications', 'idx_temp_notif_trip')) {
                    $table->index(['trip_request_id'], 'idx_temp_notif_trip');
                }
            });
        }

        // =============================================================
        // 10. PARCEL_WEIGHTS - Range lookups
        // =============================================================
        if (Schema::hasTable('parcel_weights')) {
            Schema::table('parcel_weights', function (Blueprint $table) {
                if (!$this->hasIndex('parcel_weights', 'idx_parcel_weight_range')) {
                    $table->index(['min_weight', 'max_weight'], 'idx_parcel_weight_range');
                }
            });
        }
    }

    public function down(): void
    {
        // Drop all indexes created
        $indexes = [
            'user_last_locations' => ['idx_location_bbox', 'idx_location_user_zone', 'idx_location_point'],
            'trip_requests' => ['idx_trip_customer_status_type', 'idx_trip_driver_status', 'idx_trip_zone_status_created', 'idx_trip_payment_status', 'idx_trip_created_status'],
            'fare_biddings' => ['idx_bidding_trip_driver', 'idx_bidding_trip_ignored'],
            'driver_details' => ['idx_driver_online_status', 'idx_driver_user_online'],
            'vehicles' => ['idx_vehicle_category_active', 'idx_vehicle_driver_active'],
            'users' => ['idx_user_type_active'],
            'transactions' => ['idx_transaction_user_created', 'idx_transaction_type_created'],
            'channel_conversations' => ['idx_conversation_channel_created'],
            'temp_trip_notifications' => ['idx_temp_notif_trip'],
            'parcel_weights' => ['idx_parcel_weight_range'],
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
    private function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
