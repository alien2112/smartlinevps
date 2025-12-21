<?php
/**
 * Export Arabic Tables for Server
 * 
 * This exports tables with Arabic content from local DB
 * in a server-compatible format with proper UTF-8 encoding.
 * 
 * Run: php export_arabic_tables.php
 */

require __DIR__ . '/../rateel/vendor/autoload.php';

$app = require_once __DIR__ . '/../rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$outputFile = __DIR__ . '/arabic_data_export.sql';

echo "=== Exporting Arabic Tables ===\n\n";

$sql = "-- Arabic Tables Export\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Encoding: UTF-8\n";
$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET CHARACTER SET utf8mb4;\n";
$sql .= "SET SESSION collation_connection = 'utf8mb4_unicode_ci';\n\n";

// Tables to export
$tables = [
    'vehicle_brands' => ['id', 'name', 'description', 'image', 'is_active'],
    'vehicle_models' => ['id', 'name', 'brand_id', 'seat_capacity', 'maximum_weight', 'hatch_bag_capacity', 'engine', 'description', 'image', 'is_active'],
    'user_levels' => ['id', 'sequence', 'name', 'reward_type', 'reward_amount', 'image', 'targeted_ride', 'targeted_ride_point', 'targeted_amount', 'targeted_amount_point', 'targeted_cancel', 'targeted_cancel_point', 'targeted_review', 'targeted_review_point', 'user_type', 'is_active'],
    'parcel_categories' => ['id', 'name', 'description', 'image', 'is_active'],
    'vehicle_categories' => ['id', 'name', 'description', 'image', 'type', 'is_active'],
];

foreach ($tables as $table => $columns) {
    echo "Exporting: $table\n";
    
    try {
        $data = DB::table($table)->get();
        
        if ($data->isEmpty()) {
            echo "  - No data found\n";
            continue;
        }
        
        $sql .= "\n-- Table: $table\n";
        $sql .= "-- Records: " . $data->count() . "\n";
        
        // Truncate existing data
        $sql .= "TRUNCATE TABLE `$table`;\n";
        
        foreach ($data as $row) {
            $values = [];
            $insertColumns = [];
            
            foreach ($columns as $col) {
                if (!property_exists($row, $col)) continue;
                
                $insertColumns[] = "`$col`";
                $val = $row->$col;
                
                if (is_null($val)) {
                    $values[] = "NULL";
                } elseif (is_numeric($val)) {
                    $values[] = $val;
                } else {
                    // Escape and quote strings
                    $escaped = addslashes($val);
                    $values[] = "'" . $escaped . "'";
                }
            }
            
            $sql .= "INSERT INTO `$table` (" . implode(", ", $insertColumns) . ") VALUES (" . implode(", ", $values) . ");\n";
        }
        
        echo "  - Exported " . $data->count() . " records\n";
        
    } catch (Exception $e) {
        echo "  - Error: " . $e->getMessage() . "\n";
    }
}

// Save file with UTF-8 BOM for proper encoding
file_put_contents($outputFile, $sql);

echo "\n=== Export Complete ===\n";
echo "File: $outputFile\n";
echo "Size: " . round(filesize($outputFile) / 1024, 2) . " KB\n\n";
echo "Next steps:\n";
echo "1. Copy file to server: scp server-deployment/arabic_data_export.sql root@72.62.29.3:/tmp/\n";
echo "2. Import on server: mysql -u smartline -p --default-character-set=utf8mb4 smartline < /tmp/arabic_data_export.sql\n";
