<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Optimization: Add database indexes for admin dashboard queries
 * 
 * This migration adds composite indexes to optimize:
 * - Zone statistics N+1 queries
 * - Fleet map marker queries  
 * - Dashboard aggregate queries
 * - Trip list filtering
 * - Customer/Driver show page stats
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Index for zone statistics - Most critical for N+1 fix
        Schema::table('trip_requests', function (Blueprint $table) {
            // Zone + status + date range queries (dashboard zone stats)
            $table->index(['zone_id', 'current_status', 'created_at'], 'idx_trip_zone_status_created');
            
            // Payment status queries (earnings aggregation)
            $table->index(['payment_status', 'created_at'], 'idx_trip_payment_created');
            
            // Customer trip list (customer show page)
            $table->index(['customer_id', 'current_status', 'created_at'], 'idx_trip_customer_status_created');
            
            // Driver trip list (driver show page)
            $table->index(['driver_id', 'current_status', 'created_at'], 'idx_trip_driver_status_created');
            
            // Trip type filtering (reports)
            $table->index(['type', 'payment_status', 'created_at'], 'idx_trip_type_payment_created');
        });

        // User indexes for fleet map and customer/driver lists
        Schema::table('users', function (Blueprint $table) {
            // Active users by type (fleet map, statistics)
            $table->index(['user_type', 'is_active'], 'idx_users_type_active');
        });

        // Transaction indexes for dashboard
        Schema::table('transactions', function (Blueprint $table) {
            // User transaction history
            $table->index(['user_id', 'created_at'], 'idx_transactions_user_created');
        });

        // Safety alerts for fleet map
        Schema::table('safety_alerts', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'idx_safety_alerts_status_created');
            $table->index(['trip_request_id', 'status'], 'idx_safety_alerts_trip_status');
        });

        // User last locations for fleet map markers
        Schema::table('user_last_locations', function (Blueprint $table) {
            $table->index(['zone_id', 'user_id'], 'idx_user_locations_zone_user');
        });

        // Trip request fees for earnings queries
        Schema::table('trip_request_fees', function (Blueprint $table) {
            $table->index(['cancelled_by'], 'idx_trip_fees_cancelled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex('idx_trip_zone_status_created');
            $table->dropIndex('idx_trip_payment_created');
            $table->dropIndex('idx_trip_customer_status_created');
            $table->dropIndex('idx_trip_driver_status_created');
            $table->dropIndex('idx_trip_type_payment_created');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_type_active');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_user_created');
        });

        Schema::table('safety_alerts', function (Blueprint $table) {
            $table->dropIndex('idx_safety_alerts_status_created');
            $table->dropIndex('idx_safety_alerts_trip_status');
        });

        Schema::table('user_last_locations', function (Blueprint $table) {
            $table->dropIndex('idx_user_locations_zone_user');
        });

        Schema::table('trip_request_fees', function (Blueprint $table) {
            $table->dropIndex('idx_trip_fees_cancelled_by');
        });
    }
};
