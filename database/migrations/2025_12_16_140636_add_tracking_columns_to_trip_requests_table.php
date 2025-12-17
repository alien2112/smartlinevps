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
            // Last known location
            $table->decimal('last_latitude', 10, 8)->nullable();
            $table->decimal('last_longitude', 11, 8)->nullable();
            $table->bigInteger('last_location_timestamp')->nullable()->comment('Unix timestamp of last location update');

            // Current speed
            $table->decimal('current_speed', 5, 2)->default(0)->comment('Current speed in m/s');

            // Accumulated metrics
            $table->integer('total_distance')->default(0)->comment('Total distance in meters');
            $table->integer('total_duration')->default(0)->comment('Total duration in seconds');

            // Anomaly tracking
            $table->integer('anomaly_count')->default(0)->comment('Number of suspicious location updates');
            $table->timestamp('last_anomaly_at')->nullable();

            // Indexes for queries
            $table->index(['last_latitude', 'last_longitude']);
            $table->index('last_location_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropColumn([
                'last_latitude',
                'last_longitude',
                'last_location_timestamp',
                'current_speed',
                'total_distance',
                'total_duration',
                'anomaly_count',
                'last_anomaly_at'
            ]);
        });
    }
};
