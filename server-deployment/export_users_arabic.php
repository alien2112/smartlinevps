<?php
/**
 * Export User-Related Arabic Tables for Server
 * 
 * This exports users and addresses tables from local DB
 * with proper UTF-8 encoding.
 * 
 * Run: php export_users_arabic.php
 */

require __DIR__ . '/../rateel/vendor/autoload.php';

$app = require_once __DIR__ . '/../rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$outputFile = __DIR__ . '/users_arabic_export.sql';

echo "=== Exporting User-Related Arabic Tables ===\n\n";

$sql = "-- Users Arabic Tables Export\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Encoding: UTF-8\n";
$sql .= "SET NAMES utf8mb4;\n";
$sql .= "SET CHARACTER SET utf8mb4;\n";
$sql .= "SET SESSION collation_connection = 'utf8mb4_unicode_ci';\n";
$sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Get all users
echo "Exporting: users\n";
$users = DB::table('users')->get();
echo "  - Found " . $users->count() . " users\n";

$sql .= "\n-- Update users (only names, not replacing entire records)\n";
foreach ($users as $user) {
    $firstName = addslashes($user->first_name ?? '');
    $lastName = addslashes($user->last_name ?? '');
    
    $sql .= "UPDATE `users` SET `first_name` = '$firstName', `last_name` = '$lastName' WHERE `id` = '{$user->id}';\n";
}

// Get addresses if table exists
if (Schema::hasTable('addresses')) {
    echo "Exporting: addresses\n";
    $addresses = DB::table('addresses')->get();
    echo "  - Found " . $addresses->count() . " addresses\n";
    
    $sql .= "\n-- Update addresses\n";
    foreach ($addresses as $addr) {
        $address = addslashes($addr->address ?? '');
        $sql .= "UPDATE `addresses` SET `address` = '$address' WHERE `id` = '{$addr->id}';\n";
    }
}

// Get trip request coordinates if has data
if (Schema::hasTable('trip_request_coordinates')) {
    echo "Exporting: trip_request_coordinates\n";
    $coords = DB::table('trip_request_coordinates')
        ->whereNotNull('pickup_address')
        ->orWhereNotNull('destination_address')
        ->get();
    echo "  - Found " . $coords->count() . " coordinates with addresses\n";
    
    $sql .= "\n-- Update trip addresses\n";
    foreach ($coords as $coord) {
        $pickup = addslashes($coord->pickup_address ?? '');
        $destination = addslashes($coord->destination_address ?? '');
        
        $sql .= "UPDATE `trip_request_coordinates` SET `pickup_address` = '$pickup', `destination_address` = '$destination' WHERE `id` = '{$coord->id}';\n";
    }
}

// Driver details
if (Schema::hasTable('driver_details')) {
    echo "Exporting: driver_details\n";
    $drivers = DB::table('driver_details')->whereNotNull('service')->get();
    echo "  - Found " . $drivers->count() . " driver details\n";
    
    // Only update if there's data
    if ($drivers->count() > 0) {
        $sql .= "\n-- Update driver details\n";
        foreach ($drivers as $driver) {
            $service = addslashes($driver->service ?? '');
            if ($service) {
                $sql .= "UPDATE `driver_details` SET `service` = '$service' WHERE `user_id` = '{$driver->user_id}';\n";
            }
        }
    }
}

$sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";

// Save file
file_put_contents($outputFile, $sql);

echo "\n=== Export Complete ===\n";
echo "File: $outputFile\n";
echo "Size: " . round(filesize($outputFile) / 1024, 2) . " KB\n\n";
echo "Next steps:\n";
echo "1. Copy to server: scp server-deployment/users_arabic_export.sql root@72.62.29.3:/tmp/\n";
echo "2. Import on server: mysql -u smartline -p --default-character-set=utf8mb4 smartline < /tmp/users_arabic_export.sql\n";
