<?php
/**
 * Export Zones Script - Server Compatible Format
 * 
 * This script exports all zones from the local database to a SQL file
 * that can be imported on any MySQL server (5.7+ or 8.0+).
 * 
 * Usage: php export_zones_for_server.php
 * Output: zones_export.sql (copy this to server and run with mysql)
 */

require __DIR__ . '/rateel/vendor/autoload.php';

$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Exporting Zones for Server ===\n\n";

$outputFile = __DIR__ . '/zones_export_server.sql';

try {
    // Get all zones with coordinates as WKT (Well-Known Text)
    $zones = DB::table('zones')
        ->select([
            'id',
            'readable_id',
            'name',
            DB::raw('ST_AsText(coordinates) as wkt'),
            'is_active',
            'extra_fare_status',
            'extra_fare_fee',
            'extra_fare_reason',
            'created_at',
            'updated_at',
            'deleted_at'
        ])
        ->get();

    if ($zones->isEmpty()) {
        echo "No zones found in the database.\n";
        exit(1);
    }

    echo "Found " . $zones->count() . " zone(s)\n\n";

    // Start building the SQL file
    $sql = "-- ============================================================\n";
    $sql .= "-- SMARTLINE ZONES EXPORT - Server Compatible Format\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Total Zones: " . $zones->count() . "\n";
    $sql .= "-- ============================================================\n";
    $sql .= "-- \n";
    $sql .= "-- HOW TO USE:\n";
    $sql .= "-- 1. Copy this file to your server\n";
    $sql .= "-- 2. Run: mysql -u smartline -p smartline < zones_export_server.sql\n";
    $sql .= "-- ============================================================\n\n";

    // Create table if not exists
    $sql .= "-- Create zones table if not exists\n";
    $sql .= "CREATE TABLE IF NOT EXISTS `zones` (\n";
    $sql .= "    `id` char(36) NOT NULL,\n";
    $sql .= "    `readable_id` int(11) DEFAULT NULL,\n";
    $sql .= "    `name` varchar(255) NOT NULL,\n";
    $sql .= "    `coordinates` polygon NOT NULL,\n";
    $sql .= "    `is_active` tinyint(1) NOT NULL DEFAULT 1,\n";
    $sql .= "    `extra_fare_status` tinyint(1) NOT NULL DEFAULT 0,\n";
    $sql .= "    `extra_fare_fee` decimal(14,2) NOT NULL DEFAULT 0.00,\n";
    $sql .= "    `extra_fare_reason` text DEFAULT NULL,\n";
    $sql .= "    `created_at` timestamp NULL DEFAULT NULL,\n";
    $sql .= "    `updated_at` timestamp NULL DEFAULT NULL,\n";
    $sql .= "    `deleted_at` timestamp NULL DEFAULT NULL,\n";
    $sql .= "    PRIMARY KEY (`id`)\n";
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

    // Add spatial index (separate statement for compatibility)
    $sql .= "-- Add spatial index if not exists (ignore error if already exists)\n";
    $sql .= "-- Note: Run this manually if it fails: ALTER TABLE zones ADD SPATIAL INDEX(coordinates);\n\n";

    // Truncate existing zones (optional - commented out for safety)
    $sql .= "-- Uncomment the next line to delete all existing zones before insert:\n";
    $sql .= "-- TRUNCATE TABLE `zones`;\n\n";

    $sql .= "-- ============================================================\n";
    $sql .= "-- INSERT ZONES\n";
    $sql .= "-- ============================================================\n\n";

    foreach ($zones as $zone) {
        echo "Processing zone: {$zone->name}\n";

        // Clean up WKT - remove any extra spaces
        $wkt = preg_replace('/\s+/', ' ', trim($zone->wkt));

        // Escape strings
        $name = addslashes($zone->name);
        $extra_fare_reason = $zone->extra_fare_reason ? "'" . addslashes($zone->extra_fare_reason) . "'" : 'NULL';
        $deleted_at = $zone->deleted_at ? "'" . $zone->deleted_at . "'" : 'NULL';
        $created_at = $zone->created_at ? "'" . $zone->created_at . "'" : 'NOW()';
        $updated_at = $zone->updated_at ? "'" . $zone->updated_at . "'" : 'NOW()';

        // Delete existing zone with same ID first
        $sql .= "-- Zone: {$zone->name}\n";
        $sql .= "DELETE FROM `zones` WHERE `id` = '{$zone->id}';\n";

        // Insert zone using ST_GeomFromText (universally compatible)
        $sql .= "INSERT INTO `zones` (\n";
        $sql .= "    `id`, `readable_id`, `name`, `coordinates`, `is_active`,\n";
        $sql .= "    `extra_fare_status`, `extra_fare_fee`, `extra_fare_reason`,\n";
        $sql .= "    `created_at`, `updated_at`, `deleted_at`\n";
        $sql .= ") VALUES (\n";
        $sql .= "    '{$zone->id}',\n";
        $sql .= "    " . ($zone->readable_id ?? 'NULL') . ",\n";
        $sql .= "    '{$name}',\n";
        $sql .= "    ST_GeomFromText('{$wkt}', 4326),\n";
        $sql .= "    " . ($zone->is_active ? '1' : '0') . ",\n";
        $sql .= "    " . ($zone->extra_fare_status ? '1' : '0') . ",\n";
        $sql .= "    " . ($zone->extra_fare_fee ?? '0.00') . ",\n";
        $sql .= "    {$extra_fare_reason},\n";
        $sql .= "    {$created_at},\n";
        $sql .= "    {$updated_at},\n";
        $sql .= "    {$deleted_at}\n";
        $sql .= ");\n\n";
    }

    // Verification query
    $sql .= "-- ============================================================\n";
    $sql .= "-- VERIFY IMPORT\n";
    $sql .= "-- ============================================================\n";
    $sql .= "SELECT id, name, is_active, \n";
    $sql .= "       CONCAT('Coordinates: ', LEFT(ST_AsText(coordinates), 100), '...') as coords_preview\n";
    $sql .= "FROM zones WHERE deleted_at IS NULL;\n\n";

    $sql .= "SELECT CONCAT('Total active zones: ', COUNT(*)) as summary \n";
    $sql .= "FROM zones WHERE is_active = 1 AND deleted_at IS NULL;\n";

    // Write to file
    file_put_contents($outputFile, $sql);

    echo "\n=== Export Complete ===\n";
    echo "Output file: {$outputFile}\n";
    echo "Zones exported: " . $zones->count() . "\n\n";
    echo "Next steps:\n";
    echo "1. Copy the file to your server:\n";
    echo "   scp zones_export_server.sql root@your-server:/tmp/\n\n";
    echo "2. Run on the server:\n";
    echo "   mysql -u smartline -p smartline < /tmp/zones_export_server.sql\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
