<?php
/**
 * Export User Last Locations Script - Server Compatible Format
 * 
 * This script exports user_last_locations from the local database to a SQL file
 * that can be imported on any MySQL server (5.7+ or 8.0+).
 * 
 * Usage: php export_user_locations_for_server.php
 */

require __DIR__ . '/rateel/vendor/autoload.php';

$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Exporting User Last Locations for Server ===\n\n";

$outputFile = __DIR__ . '/user_locations_export_server.sql';

try {
    // Check if table exists
    $tableExists = DB::select("SHOW TABLES LIKE 'user_last_locations'");
    
    if (empty($tableExists)) {
        echo "Table user_last_locations does not exist.\n";
        exit(0);
    }

    // Get count
    $count = DB::table('user_last_locations')->count();
    echo "Found {$count} user location(s)\n\n";

    if ($count == 0) {
        echo "No user locations to export.\n";
        exit(0);
    }

    // Get all locations with coordinates as WKT
    $locations = DB::table('user_last_locations')
        ->select([
            'id',
            'user_id',
            'zone_id',
            'type',
            DB::raw('ST_AsText(coordinates) as wkt'),
            'created_at',
            'updated_at'
        ])
        ->get();

    // Start building the SQL file
    $sql = "-- ============================================================\n";
    $sql .= "-- SMARTLINE USER LAST LOCATIONS EXPORT - Server Compatible\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Total Records: {$count}\n";
    $sql .= "-- ============================================================\n\n";

    // Create table if not exists
    $sql .= "CREATE TABLE IF NOT EXISTS `user_last_locations` (\n";
    $sql .= "    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n";
    $sql .= "    `user_id` char(36) NOT NULL,\n";
    $sql .= "    `zone_id` char(36) DEFAULT NULL,\n";
    $sql .= "    `type` varchar(255) DEFAULT NULL,\n";
    $sql .= "    `coordinates` point DEFAULT NULL,\n";
    $sql .= "    `created_at` timestamp NULL DEFAULT NULL,\n";
    $sql .= "    `updated_at` timestamp NULL DEFAULT NULL,\n";
    $sql .= "    PRIMARY KEY (`id`),\n";
    $sql .= "    KEY `user_last_locations_user_id_index` (`user_id`),\n";
    $sql .= "    KEY `user_last_locations_zone_id_index` (`zone_id`)\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

    $sql .= "-- Clear existing data\n";
    $sql .= "TRUNCATE TABLE `user_last_locations`;\n\n";

    $sql .= "-- Insert locations\n";
    
    $batch = [];
    $batchSize = 100;
    $processed = 0;

    foreach ($locations as $loc) {
        if (!$loc->wkt) {
            continue;
        }

        $wkt = preg_replace('/\s+/', ' ', trim($loc->wkt));
        $zone_id = $loc->zone_id ? "'{$loc->zone_id}'" : 'NULL';
        $type = $loc->type ? "'" . addslashes($loc->type) . "'" : 'NULL';
        $created_at = $loc->created_at ? "'" . $loc->created_at . "'" : 'NOW()';
        $updated_at = $loc->updated_at ? "'" . $loc->updated_at . "'" : 'NOW()';

        $sql .= "INSERT INTO `user_last_locations` (`user_id`, `zone_id`, `type`, `coordinates`, `created_at`, `updated_at`) VALUES (";
        $sql .= "'{$loc->user_id}', {$zone_id}, {$type}, ST_GeomFromText('{$wkt}', 4326), {$created_at}, {$updated_at});\n";

        $processed++;
        if ($processed % 100 == 0) {
            echo "Processed {$processed} records...\n";
        }
    }

    $sql .= "\n-- Verify\n";
    $sql .= "SELECT COUNT(*) as total FROM user_last_locations;\n";

    // Write to file
    file_put_contents($outputFile, $sql);

    echo "\n=== Export Complete ===\n";
    echo "Output file: {$outputFile}\n";
    echo "Records exported: {$processed}\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
