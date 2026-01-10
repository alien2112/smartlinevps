<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add honeycomb_enabled field to allow drivers to opt-in/out of 
     * honeycomb dispatch system for their account.
     */
    public function up(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            // Boolean toggle to enable/disable honeycomb dispatch for this driver
            $table->boolean('honeycomb_enabled')->default(true)
                ->after('destination_radius_km')
                ->comment('Driver preference: enable/disable honeycomb dispatch');

            // Index for fast filtering in dispatch queries
            $table->index(['honeycomb_enabled'], 'idx_honeycomb_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropIndex('idx_honeycomb_enabled');
            $table->dropColumn('honeycomb_enabled');
        });
    }
};
