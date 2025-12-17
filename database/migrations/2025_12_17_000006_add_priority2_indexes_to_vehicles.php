<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Priority 2 indexes for vehicles table
     * Expected impact: Vehicle lookup queries significantly faster
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Vehicle availability by driver
            $table->index(['driver_id', 'is_active'], 'idx_vehicles_driver_active');

            // Vehicle category filtering
            $table->index(['vehicle_category_id', 'is_active'], 'idx_vehicles_category');

            // Vehicle approval status (for admin queries)
            $table->index(['is_approved', 'created_at'], 'idx_vehicles_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex('idx_vehicles_driver_active');
            $table->dropIndex('idx_vehicles_category');
            $table->dropIndex('idx_vehicles_approval');
        });
    }
};
