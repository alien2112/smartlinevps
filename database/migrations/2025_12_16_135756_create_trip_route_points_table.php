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
        Schema::create('trip_route_points', function (Blueprint $table) {
            $table->id();
            $table->uuid('trip_request_id');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('speed', 5, 2)->default(0)->comment('Speed in m/s');
            $table->decimal('heading', 5, 2)->nullable()->comment('Direction in degrees');
            $table->decimal('accuracy', 8, 2)->nullable()->comment('GPS accuracy in meters');
            $table->bigInteger('timestamp')->comment('Unix timestamp from device');
            $table->enum('event_type', ['START', 'PICKUP', 'DROPOFF', 'SOS', 'IDLE', 'NORMAL'])->default('NORMAL');
            $table->timestamps();

            // Indexes for performance
            $table->index('trip_request_id');
            $table->index('timestamp');
            $table->index(['trip_request_id', 'timestamp']);

            // Foreign key (if trip_requests table exists)
            // Uncomment if you want to enforce referential integrity
            // $table->foreign('trip_request_id')->references('id')->on('trip_requests')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_route_points');
    }
};
