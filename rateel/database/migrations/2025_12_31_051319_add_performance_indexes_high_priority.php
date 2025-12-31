<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Issue #12: Add critical performance indexes for scaling
 *
 * These indexes address the following scaling bottlenecks:
 * - Trip lookup by customer/driver with status filtering
 * - Zone-based queries with date ranges
 * - Active trips quick lookup
 * - Bidding system queries
 * - Parcel weight range queries
 * - Chat message pagination
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Issue #12: Trip Requests indexes
        Schema::table('trip_requests', function (Blueprint $table) {
            // Customer active trips lookup
            if (!$this->indexExists('trip_requests', 'idx_trip_customer_status')) {
                $table->index(['customer_id', 'current_status'], 'idx_trip_customer_status');
            }

            // Driver active trips lookup
            if (!$this->indexExists('trip_requests', 'idx_trip_driver_status')) {
                $table->index(['driver_id', 'current_status'], 'idx_trip_driver_status');
            }

            // Zone reporting queries
            if (!$this->indexExists('trip_requests', 'idx_trip_zone_created')) {
                $table->index(['zone_id', 'created_at'], 'idx_trip_zone_created');
            }

            // Status filtering with date
            if (!$this->indexExists('trip_requests', 'idx_trip_status_created')) {
                $table->index(['current_status', 'created_at'], 'idx_trip_status_created');
            }

            // Resume ride queries
            if (!$this->indexExists('trip_requests', 'idx_trip_customer_type_status')) {
                $table->index(['customer_id', 'type', 'current_status'], 'idx_trip_customer_type_status');
            }
        });

        // Issue #6: Bidding system indexes
        if (Schema::hasTable('fare_biddings')) {
            Schema::table('fare_biddings', function (Blueprint $table) {
                if (!$this->indexExists('fare_biddings', 'idx_bidding_trip_ignored')) {
                    $table->index(['trip_request_id', 'is_ignored', 'created_at'], 'idx_bidding_trip_ignored');
                }

                if (!$this->indexExists('fare_biddings', 'idx_bidding_driver')) {
                    $table->index(['driver_id', 'trip_request_id'], 'idx_bidding_driver');
                }
            });
        }

        // Issue #13: Parcel weight range query optimization
        if (Schema::hasTable('parcel_weights')) {
            Schema::table('parcel_weights', function (Blueprint $table) {
                if (!$this->indexExists('parcel_weights', 'idx_parcel_weight_range')) {
                    $table->index(['min_weight', 'max_weight'], 'idx_parcel_weight_range');
                }
            });
        }

        // Issue #20: Chat message pagination
        if (Schema::hasTable('channel_conversations')) {
            Schema::table('channel_conversations', function (Blueprint $table) {
                if (!$this->indexExists('channel_conversations', 'idx_conversation_channel_created')) {
                    $table->index(['channel_id', 'created_at', 'id'], 'idx_conversation_channel_created');
                }
            });
        }

        // User location indexes for driver search
        if (Schema::hasTable('user_last_locations')) {
            Schema::table('user_last_locations', function (Blueprint $table) {
                if (!$this->indexExists('user_last_locations', 'idx_location_zone')) {
                    $table->index(['zone_id', 'user_id'], 'idx_location_zone');
                }
            });
        }

        // Transaction indexes for reporting
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!$this->indexExists('transactions', 'idx_transaction_user_created')) {
                    $table->index(['user_id', 'created_at'], 'idx_transaction_user_created');
                }

                if (!$this->indexExists('transactions', 'idx_transaction_type_created')) {
                    $table->index(['trx_type', 'created_at'], 'idx_transaction_type_created');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trip_customer_status');
            $table->dropIndex('idx_trip_driver_status');
            $table->dropIndex('idx_trip_zone_created');
            $table->dropIndex('idx_trip_status_created');
            $table->dropIndex('idx_trip_customer_type_status');
        });

        if (Schema::hasTable('fare_biddings')) {
            Schema::table('fare_biddings', function (Blueprint $table) {
                $table->dropIndex('idx_bidding_trip_ignored');
                $table->dropIndex('idx_bidding_driver');
            });
        }

        if (Schema::hasTable('parcel_weights')) {
            Schema::table('parcel_weights', function (Blueprint $table) {
                $table->dropIndex('idx_parcel_weight_range');
            });
        }

        if (Schema::hasTable('channel_conversations')) {
            Schema::table('channel_conversations', function (Blueprint $table) {
                $table->dropIndex('idx_conversation_channel_created');
            });
        }

        if (Schema::hasTable('user_last_locations')) {
            Schema::table('user_last_locations', function (Blueprint $table) {
                $table->dropIndex('idx_location_zone');
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('idx_transaction_user_created');
                $table->dropIndex('idx_transaction_type_created');
            });
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
