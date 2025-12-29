<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            // New unified trip type field (replaces is_travel boolean with more flexibility)
            $table->enum('trip_type', ['normal', 'travel'])->default('normal')->after('type');

            // Scheduled ride time for travel mode
            $table->timestamp('scheduled_at')->nullable()->after('trip_type');

            // Seat management for carpooling/shuttle travel
            $table->integer('seats_requested')->default(1)->after('scheduled_at');
            $table->integer('seats_capacity')->nullable()->after('seats_requested'); // Total seats in vehicle
            $table->integer('seats_taken')->default(0)->after('seats_capacity'); // Seats already booked

            // Variable pricing with minimum floor
            $table->decimal('min_price', 16, 2)->nullable()->after('seats_taken');
            $table->decimal('offer_price', 16, 2)->nullable()->after('min_price');

            // Travel-specific radius for driver matching (can also be config-based)
            $table->decimal('travel_radius_km', 8, 2)->nullable()->after('offer_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn([
                'trip_type',
                'scheduled_at',
                'seats_requested',
                'seats_capacity',
                'seats_taken',
                'min_price',
                'offer_price',
                'travel_radius_km',
            ]);
        });
    }
};
