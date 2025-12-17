<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Priority 2 indexes for coupons, driver_details, zones, and bids
     * Expected impact: Coupon lookups 500ms -> <5ms
     */
    public function up(): void
    {
        // Coupon/Promotion indexes
        Schema::table('coupon_setups', function (Blueprint $table) {
            // Coupon code lookups (apply coupon at checkout)
            $table->index('coupon_code', 'idx_coupons_code');

            // Active promotions filtering
            $table->index(['is_active', 'start_date', 'end_date'], 'idx_coupons_active');
        });

        // User promotions (if table exists)
        if (Schema::hasTable('user_promotions')) {
            Schema::table('user_promotions', function (Blueprint $table) {
                $table->index(['user_id', 'is_used'], 'idx_promotions_user');
            });
        }

        // Driver details indexes
        Schema::table('driver_details', function (Blueprint $table) {
            // Driver details lookups
            $table->index('user_id', 'idx_driver_details_user');

            // Driver ratings for filtering
            $table->index(['avg_rating', 'total_trips'], 'idx_driver_details_rating');
        });

        // Zone management indexes
        Schema::table('zones', function (Blueprint $table) {
            // Zone lookups by name
            $table->index(['name', 'is_active'], 'idx_zones_name_active');

            // Zone by city/region filtering
            if (Schema::hasColumn('zones', 'city')) {
                $table->index(['city', 'is_active'], 'idx_zones_city');
            }
        });

        // Trip bidding indexes (if table exists)
        if (Schema::hasTable('trip_bids')) {
            Schema::table('trip_bids', function (Blueprint $table) {
                // Bid lookups by trip
                $table->index(['trip_request_id', 'created_at'], 'idx_bids_trip');

                // Bid lookups by driver
                $table->index(['driver_id', 'status'], 'idx_bids_driver');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupon_setups', function (Blueprint $table) {
            $table->dropIndex('idx_coupons_code');
            $table->dropIndex('idx_coupons_active');
        });

        if (Schema::hasTable('user_promotions')) {
            Schema::table('user_promotions', function (Blueprint $table) {
                $table->dropIndex('idx_promotions_user');
            });
        }

        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex('idx_driver_details_user');
            $table->dropIndex('idx_driver_details_rating');
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->dropIndex('idx_zones_name_active');
            if (Schema::hasColumn('zones', 'city')) {
                $table->dropIndex('idx_zones_city');
            }
        });

        if (Schema::hasTable('trip_bids')) {
            Schema::table('trip_bids', function (Blueprint $table) {
                $table->dropIndex('idx_bids_trip');
                $table->dropIndex('idx_bids_driver');
            });
        }
    }
};
