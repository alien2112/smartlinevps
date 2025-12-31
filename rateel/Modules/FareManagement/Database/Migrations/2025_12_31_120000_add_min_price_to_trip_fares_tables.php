<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add min_price to trip_fares table (per-category minimum price)
        Schema::table('trip_fares', function (Blueprint $table) {
            $table->decimal('min_price', 16, 2)->default(0)->after('fixed_price_below_threshold');
        });

        // Add min_price to zone_wise_default_trip_fares table (default minimum price)
        Schema::table('zone_wise_default_trip_fares', function (Blueprint $table) {
            $table->decimal('min_price', 16, 2)->default(0)->after('fixed_price_below_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_fares', function (Blueprint $table) {
            $table->dropColumn('min_price');
        });

        Schema::table('zone_wise_default_trip_fares', function (Blueprint $table) {
            $table->dropColumn('min_price');
        });
    }
};
