<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Performance Optimization Migration
 * 
 * Adds critical indexes for high-traffic queries:
 * - Driver availability lookups
 * - Vehicle category searches
 * - Fare bidding queries
 * - Business settings lookups
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Driver details indexes for availability checks
        $this->safeAddIndex('driver_details', ['user_id', 'is_online', 'availability_status'], 'idx_driver_availability');
        
        // Vehicles indexes for category matching
        $this->safeAddIndex('vehicles', ['driver_id', 'is_active', 'category_id'], 'idx_vehicle_driver_category');
        
        // Fare bidding indexes
        $this->safeAddIndex('fare_biddings', ['trip_request_id', 'driver_id', 'is_ignored'], 'idx_bidding_trip_driver');
        
        // Rejected/ignored requests indexes
        $this->safeAddIndex('rejected_driver_requests', ['trip_request_id', 'user_id'], 'idx_rejected_trip_user');
        
        // Business settings index for config lookups
        $this->safeAddIndex('business_settings', ['key_name', 'settings_type'], 'idx_settings_key');
        
        // Users active status index
        $this->safeAddIndex('users', ['id', 'is_active', 'user_type'], 'idx_users_active_type');
        
        // Trip request coordinates - ensure spatial index exists
        $this->ensureSpatialIndex('trip_request_coordinates', 'pickup_coordinates', 'idx_coord_pickup_spatial');
        
        Log::info('Performance indexes migration completed successfully');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropIndex('driver_details', 'idx_driver_availability');
        $this->safeDropIndex('vehicles', 'idx_vehicle_driver_category');
        $this->safeDropIndex('fare_biddings', 'idx_bidding_trip_driver');
        $this->safeDropIndex('rejected_driver_requests', 'idx_rejected_trip_user');
        $this->safeDropIndex('business_settings', 'idx_settings_key');
        $this->safeDropIndex('users', 'idx_users_active_type');
    }

    /**
     * Safely add an index if it doesn't exist
     */
    private function safeAddIndex(string $table, array $columns, string $indexName): void
    {
        try {
            if (!Schema::hasTable($table)) {
                Log::warning("Performance migration: Table {$table} does not exist, skipping index");
                return;
            }

            // Check if index already exists
            $indexExists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            
            if (empty($indexExists)) {
                Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
                    $blueprint->index($columns, $indexName);
                });
                Log::info("Performance migration: Added index {$indexName} on {$table}");
            } else {
                Log::debug("Performance migration: Index {$indexName} already exists on {$table}");
            }
        } catch (\Exception $e) {
            Log::warning("Performance migration: Failed to add index {$indexName} on {$table}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Safely drop an index if it exists
     */
    private function safeDropIndex(string $table, string $indexName): void
    {
        try {
            if (!Schema::hasTable($table)) {
                return;
            }

            $indexExists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            
            if (!empty($indexExists)) {
                Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                    $blueprint->dropIndex($indexName);
                });
            }
        } catch (\Exception $e) {
            Log::warning("Performance migration: Failed to drop index {$indexName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Ensure spatial index exists on a POINT column
     */
    private function ensureSpatialIndex(string $table, string $column, string $indexName): void
    {
        try {
            if (!Schema::hasTable($table)) {
                Log::warning("Performance migration: Table {$table} does not exist, skipping spatial index");
                return;
            }

            // Check if spatial index already exists
            $indexExists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            
            if (empty($indexExists)) {
                // Check if column exists and is a spatial type
                $columnInfo = DB::select("SHOW COLUMNS FROM {$table} WHERE Field = ?", [$column]);
                
                if (!empty($columnInfo)) {
                    DB::statement("ALTER TABLE {$table} ADD SPATIAL INDEX {$indexName} ({$column})");
                    Log::info("Performance migration: Added spatial index {$indexName} on {$table}.{$column}");
                }
            }
        } catch (\Exception $e) {
            Log::warning("Performance migration: Failed to add spatial index {$indexName}", [
                'error' => $e->getMessage()
            ]);
        }
    }
};
