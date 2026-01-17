<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migration to fix swapped coordinates in trip_request_coordinates table.
 *
 * ISSUE:
 * Coordinates are stored with latitude and longitude swapped.
 * Current: POINT(lat, lng) - e.g., POINT(31.x, 29.x) for Egypt
 * Correct: POINT(lng, lat) - e.g., POINT(29.x, 31.x) for Egypt
 *
 * For Egypt: latitude ~30-31°N, longitude ~29-31°E
 * So if X value > 30, it's likely the latitude stored as longitude (swapped)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columnsToFix = [
            'pickup_coordinates',
            'destination_coordinates',
            'start_coordinates',
            'drop_coordinates',
            'driver_accept_coordinates',
            'customer_request_coordinates',
            'int_coordinate_1',
            'int_coordinate_2',
        ];

        foreach ($columnsToFix as $column) {
            try {
                // Count records that appear to be swapped (X > 30 suggests latitude is in X position)
                $count = DB::selectOne("
                    SELECT COUNT(*) as cnt
                    FROM trip_request_coordinates 
                    WHERE `{$column}` IS NOT NULL
                    AND ST_X(`{$column}`) > 30
                ");

                if ($count->cnt == 0) {
                    Log::info("Migration: No swapped records found in trip_request_coordinates.{$column}");
                    continue;
                }

                Log::info("Migration: Fixing {$count->cnt} swapped records in trip_request_coordinates.{$column}");

                // Swap X and Y coordinates for records where X > 30 (indicating swap)
                // Using SRID 0 to avoid geographic validation issues
                DB::statement("
                    UPDATE trip_request_coordinates
                    SET `{$column}` = ST_GeomFromText(
                        CONCAT('POINT(', ST_Y(`{$column}`), ' ', ST_X(`{$column}`), ')'),
                        0
                    )
                    WHERE `{$column}` IS NOT NULL
                    AND ST_X(`{$column}`) > 30
                ");

                Log::info("Migration: Successfully fixed trip_request_coordinates.{$column}");
            } catch (\Exception $e) {
                Log::error("Migration: Failed to fix trip_request_coordinates.{$column}: " . $e->getMessage());
                throw $e;
            }
        }

        // Also fix recent_addresses if needed
        $addressColumns = ['pickup_coordinates', 'destination_coordinates'];
        
        foreach ($addressColumns as $column) {
            try {
                if (!DB::getSchemaBuilder()->hasColumn('recent_addresses', $column)) {
                    continue;
                }

                $count = DB::selectOne("
                    SELECT COUNT(*) as cnt
                    FROM recent_addresses 
                    WHERE `{$column}` IS NOT NULL
                    AND ST_X(`{$column}`) > 30
                ");

                if ($count->cnt == 0) {
                    Log::info("Migration: No swapped records found in recent_addresses.{$column}");
                    continue;
                }

                Log::info("Migration: Fixing {$count->cnt} swapped records in recent_addresses.{$column}");

                DB::statement("
                    UPDATE recent_addresses
                    SET `{$column}` = ST_GeomFromText(
                        CONCAT('POINT(', ST_Y(`{$column}`), ' ', ST_X(`{$column}`), ')'),
                        0
                    )
                    WHERE `{$column}` IS NOT NULL
                    AND ST_X(`{$column}`) > 30
                ");

                Log::info("Migration: Successfully fixed recent_addresses.{$column}");
            } catch (\Exception $e) {
                Log::error("Migration: Failed to fix recent_addresses.{$column}: " . $e->getMessage());
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Running up() again would swap back (same operation)
        $this->up();
    }
};
