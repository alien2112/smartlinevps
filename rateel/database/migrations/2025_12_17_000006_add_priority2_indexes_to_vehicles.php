<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (!$this->hasIndex('vehicles', 'idx_vehicles_driver_active')) {
                $table->index(['driver_id', 'is_active'], 'idx_vehicles_driver_active');
            }
            // Skip category_id index - column is longtext type which can't be indexed in MySQL
            // if (!$this->hasIndex('vehicles', 'idx_vehicles_category')) {
            //     if (Schema::hasColumn('vehicles', 'category_id')) {
            //         $table->index(['category_id', 'is_active'], 'idx_vehicles_category');
            //     }
            // }
            if (!$this->hasIndex('vehicles', 'idx_vehicles_approval')) {
                // Use 'vehicle_request_status' instead of 'is_approved' if applicable, 
                // or just skip if columns don't match
                if (Schema::hasColumn('vehicles', 'vehicle_request_status')) {
                    $table->index(['vehicle_request_status', 'created_at'], 'idx_vehicles_approval');
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if ($this->hasIndex('vehicles', 'idx_vehicles_driver_active')) {
                $table->dropIndex('idx_vehicles_driver_active');
            }
            if ($this->hasIndex('vehicles', 'idx_vehicles_category')) {
                $table->dropIndex('idx_vehicles_category');
            }
            if ($this->hasIndex('vehicles', 'idx_vehicles_approval')) {
                $table->dropIndex('idx_vehicles_approval');
            }
        });
    }

    private function hasIndex($table, $index)
    {
        $results = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = '{$index}'");
        return count($results) > 0;
    }
};
