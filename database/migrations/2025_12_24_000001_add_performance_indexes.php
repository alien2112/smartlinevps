<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add performance indexes for trip acceptance and OTP flows
 *
 * These indexes optimize the hot paths:
 * 1. Driver accepting trips (status + driver_id lookups)
 * 2. OTP matching (trip_request_id + driver_id lookups)
 * 3. Trip status updates
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            // Composite index for trip acceptance queries
            // WHERE current_status IN ('pending', 'searching') AND driver_id IS NULL
            if (!$this->indexExists('trip_requests', 'idx_trip_status_driver')) {
                $table->index(['current_status', 'driver_id'], 'idx_trip_status_driver');
            }

            // Index for OTP matching
            // WHERE id = ? AND driver_id = ?
            if (!$this->indexExists('trip_requests', 'idx_trip_driver_otp')) {
                $table->index(['id', 'driver_id', 'otp'], 'idx_trip_driver_otp');
            }

            // Index for locked_at timestamp (for cleanup queries)
            if (!$this->indexExists('trip_requests', 'idx_locked_at')) {
                $table->index('locked_at', 'idx_locked_at');
            }
        });

        Schema::table('trip_status', function (Blueprint $table) {
            // Index for trip status updates
            // WHERE trip_request_id = ?
            if (!$this->indexExists('trip_status', 'idx_trip_request_id')) {
                $table->index('trip_request_id', 'idx_trip_request_id');
            }
        });

        Schema::table('driver_details', function (Blueprint $table) {
            // Composite index for driver availability checks
            // WHERE user_id = ? AND availability_status IN (...)
            if (!$this->indexExists('driver_details', 'idx_driver_availability')) {
                $table->index(['user_id', 'availability_status', 'is_online'], 'idx_driver_availability');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trip_status_driver');
            $table->dropIndex('idx_trip_driver_otp');
            $table->dropIndex('idx_locked_at');
        });

        Schema::table('trip_status', function (Blueprint $table) {
            $table->dropIndex('idx_trip_request_id');
        });

        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex('idx_driver_availability');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return count($indexes) > 0;
    }
};
