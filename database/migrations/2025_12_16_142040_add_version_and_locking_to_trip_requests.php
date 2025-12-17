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
            // Optimistic locking - version number increments on each update
            $table->unsignedInteger('version')->default(0)->after('id');

            // Track when trip was locked/assigned to driver
            $table->timestamp('locked_at')->nullable()->after('version');

            // Index for fast locking queries
            $table->index(['current_status', 'driver_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            $table->dropIndex(['current_status', 'driver_id', 'version']);
            $table->dropColumn(['version', 'locked_at']);
        });
    }
};
