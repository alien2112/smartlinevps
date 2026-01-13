<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Migration to fix swapped coordinates in trip_request_coordinates and recent_addresses tables.
 *
 * BACKGROUND:
 * The old code was creating Point objects with coordinates in the wrong order,
 * causing latitude and longitude values to be swapped in the database.
 *
 * This migration swaps X and Y values to fix the data.
 * Uses SRID 0 to avoid MySQL 8.0's geographic coordinate validation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tablesToFix = [
            'trip_request_coordinates' => [
                'pickup_coordinates',
                'destination_coordinates',
                'start_coordinates',
                'drop_coordinates',
                'driver_accept_coordinates',
                'customer_request_coordinates',
                'int_coordinate_1',
                'int_coordinate_2',
            ],
            'recent_addresses' => [
                'pickup_coordinates',
                'destination_coordinates',
            ],
        ];

        foreach ($tablesToFix as $table => $columns) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                Log::warning("Migration: Table {$table} does not exist, skipping.");
                continue;
            }

            foreach ($columns as $column) {
                if (!DB::getSchemaBuilder()->hasColumn($table, $column)) {
                    Log::warning("Migration: Column {$column} does not exist in {$table}, skipping.");
                    continue;
                }

                try {
                    $count = DB::table($table)->whereNotNull($column)->count();
                    
                    if ($count === 0) {
                        Log::info("Migration: No records to update in {$table}.{$column}");
                        continue;
                    }

                    Log::info("Migration: Fixing {$count} records in {$table}.{$column}");

                    // First ensure SRID is 0 to avoid validation issues
                    DB::statement("
                        UPDATE `{$table}` 
                        SET `{$column}` = ST_GeomFromText(ST_AsText(`{$column}`), 0)
                        WHERE `{$column}` IS NOT NULL 
                        AND ST_SRID(`{$column}`) != 0
                    ");

                    // Swap X and Y coordinates using SRID 0 (no validation)
                    // Current: X=lat, Y=lng (wrong)
                    // After: X=lng, Y=lat (correct for Eloquent Spatial)
                    DB::statement("
                        UPDATE `{$table}` AS t
                        INNER JOIN (
                            SELECT id, ST_X(`{$column}`) AS old_x, ST_Y(`{$column}`) AS old_y 
                            FROM `{$table}` 
                            WHERE `{$column}` IS NOT NULL
                        ) AS sub ON t.id = sub.id
                        SET t.`{$column}` = ST_GeomFromText(
                            CONCAT('POINT(', sub.old_y, ' ', sub.old_x, ')'),
                            0
                        )
                    ");

                    Log::info("Migration: Successfully fixed {$table}.{$column}");
                } catch (\Exception $e) {
                    Log::error("Migration: Failed to fix {$table}.{$column}: " . $e->getMessage());
                    throw $e;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->up();
    }
};
