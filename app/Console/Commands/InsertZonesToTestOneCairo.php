<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InsertZonesToTestOneCairo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zones:insert-test-one-cairo {--list : List all zones in the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert zones into test_one_cairo database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $databaseName = 'test_one_cairo';
        
        // Get database connection config
        $config = config('database.connections.mysql');
        
        // First, connect to information_schema to check/create database
        $configInfoSchema = $config;
        $configInfoSchema['database'] = 'information_schema';
        config(['database.connections.temp_connection' => $configInfoSchema]);
        
        // Check if database exists and create if needed
        $databaseExists = DB::connection('temp_connection')
            ->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$databaseName]);
        
        if (empty($databaseExists)) {
            $this->info("Database '{$databaseName}' does not exist. Creating it...");
            DB::connection('temp_connection')->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->info("Database created successfully!");
        }

        // Now create connection to the target database
        $config['database'] = $databaseName;
        config(['database.connections.test_one_cairo' => $config]);
        DB::purge('test_one_cairo');

        // If --list option is used, just list zones and return
        if ($this->option('list')) {
            return $this->listZones($databaseName);
        }
        
        $this->info("Inserting zones into database: {$databaseName}");

        try {

            // Check if zones table exists
            $tableExists = DB::connection('test_one_cairo')
                ->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'zones'", [$databaseName]);
            
            if (empty($tableExists)) {
                $this->info("Table 'zones' does not exist. Creating zones table...");
                
                // Create zones table with all columns from migrations
                DB::connection('test_one_cairo')->statement("
                    CREATE TABLE IF NOT EXISTS `zones` (
                        `id` CHAR(36) NOT NULL PRIMARY KEY,
                        `name` VARCHAR(255) NOT NULL UNIQUE,
                        `readable_id` INT NULL,
                        `coordinates` POLYGON NULL,
                        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                        `extra_fare_status` TINYINT(1) NOT NULL DEFAULT 0,
                        `extra_fare_fee` DOUBLE NOT NULL DEFAULT 0,
                        `extra_fare_reason` VARCHAR(255) NULL,
                        `deleted_at` TIMESTAMP NULL,
                        `created_at` TIMESTAMP NULL,
                        `updated_at` TIMESTAMP NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                
                $this->info("Zones table created successfully!");
            }

            // Check if zone already exists
            $existingZone = DB::connection('test_one_cairo')
                ->table('zones')
                ->where('name', 'Cairo Test Zone')
                ->first();

            if ($existingZone) {
                $this->warn("Zone 'Cairo Test Zone' already exists. Skipping insertion.");
                return 0;
            }

            // Insert the zone
            $zoneId = (string) Str::uuid();
            
            DB::connection('test_one_cairo')->statement(
                "INSERT INTO zones (id, name, coordinates, is_active, created_at, updated_at) VALUES (?, ?, ST_GeomFromText(?, 4326), ?, NOW(), NOW())",
                [
                    $zoneId,
                    'Cairo Test Zone',
                    'POLYGON((31.1 30.1, 31.4 30.1, 31.4 29.9, 31.1 29.9, 31.1 30.1))',
                    1
                ]
            );

            $this->info("Zone 'Cairo Test Zone' inserted successfully!");

            // Verify the zone
            $zone = DB::connection('test_one_cairo')
                ->select("SELECT id, name, is_active, ST_AsText(coordinates) as coordinates_wkt FROM zones WHERE name = ?", ['Cairo Test Zone']);

            if (!empty($zone)) {
                $this->info("\nZone Details:");
                $this->table(
                    ['ID', 'Name', 'Is Active', 'Coordinates (WKT)'],
                    [[$zone[0]->id, $zone[0]->name, $zone[0]->is_active ? 'Yes' : 'No', $zone[0]->coordinates_wkt]]
                );
            }

            // Test if test coordinates fall within the zone
            $containsTest = DB::connection('test_one_cairo')
                ->select("SELECT ST_Contains(coordinates, ST_GeomFromText('POINT(31.2357 30.0444)', 4326)) as contains_test_point FROM zones WHERE name = ?", ['Cairo Test Zone']);

            if (!empty($containsTest)) {
                $contains = $containsTest[0]->contains_test_point;
                $this->info("\nTest Point (lat=30.0444, lng=31.2357) is " . ($contains ? "INSIDE" : "OUTSIDE") . " the zone.");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * List all zones in the database
     *
     * @param string $databaseName
     * @return int
     */
    private function listZones($databaseName)
    {
        try {
            // Check if zones table exists
            $tableExists = DB::connection('test_one_cairo')
                ->select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'zones'", [$databaseName]);
            
            if (empty($tableExists)) {
                $this->warn("Table 'zones' does not exist in database '{$databaseName}'.");
                return 0;
            }

            // Get all zones
            $zones = DB::connection('test_one_cairo')
                ->select("SELECT id, name, readable_id, is_active, ST_AsText(coordinates) as coordinates_wkt, created_at, updated_at FROM zones WHERE deleted_at IS NULL ORDER BY name");

            if (empty($zones)) {
                $this->info("No zones found in database '{$databaseName}'.");
                return 0;
            }

            $this->info("\nZones in database '{$databaseName}':");
            $this->info("Total zones: " . count($zones) . "\n");

            $tableData = [];
            foreach ($zones as $zone) {
                $tableData[] = [
                    $zone->id,
                    $zone->name,
                    $zone->readable_id ?? 'N/A',
                    $zone->is_active ? 'Yes' : 'No',
                    $zone->coordinates_wkt ?? 'N/A',
                    $zone->created_at,
                ];
            }

            $this->table(
                ['ID', 'Name', 'Readable ID', 'Is Active', 'Coordinates (WKT)', 'Created At'],
                $tableData
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Error listing zones: " . $e->getMessage());
            return 1;
        }
    }
}

