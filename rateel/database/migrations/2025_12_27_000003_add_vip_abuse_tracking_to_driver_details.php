<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds VIP abuse prevention tracking fields
     */
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->integer('low_category_trips_today')->default(0)->after('parcel_count')
                ->comment('Count of trips below driver category level taken today');
            $table->date('low_category_trips_date')->nullable()->after('low_category_trips_today')
                ->comment('Date of the low_category_trips_today counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn(['low_category_trips_today', 'low_category_trips_date']);
        });
    }
};
