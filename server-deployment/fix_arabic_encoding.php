<?php
/**
 * Fix Arabic Text Encoding Script
 * 
 * This script fixes double-encoded UTF-8 text in the database
 * Run: php fix_arabic_encoding.php
 */

// Database configuration - UPDATE THESE VALUES
$host = '127.0.0.1';
$dbname = 'smartline';
$username = 'smartline';
$password = 'YOUR_PASSWORD_HERE'; // <-- UPDATE THIS

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );
    
    echo "=== Arabic Text Encoding Fix ===\n\n";
    
    // Tables and columns that may contain Arabic text
    $tables_to_fix = [
        'users' => ['first_name', 'last_name'],
        'user_levels' => ['name'],
        'vehicle_brands' => ['name', 'description'],
        'vehicle_models' => ['name', 'description'],
        'vehicles' => ['licence_plate_number'],
        'zones' => ['name'],
        'parcel_categories' => ['name', 'description'],
        'vehicle_categories' => ['name', 'description'],
        'driver_details' => [],
        'addresses' => ['address'],
        'trip_request_coordinates' => ['pickup_address', 'destination_address'],
    ];
    
    foreach ($tables_to_fix as $table => $columns) {
        // Check if table exists
        $check = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($check->rowCount() == 0) {
            echo "Table '$table' not found, skipping...\n";
            continue;
        }
        
        if (empty($columns)) {
            // Get all VARCHAR/TEXT columns
            $cols = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Type LIKE '%varchar%' OR Type LIKE '%text%'");
            $columns = [];
            while ($col = $cols->fetch(PDO::FETCH_ASSOC)) {
                $columns[] = $col['Field'];
            }
        }
        
        if (empty($columns)) continue;
        
        echo "Fixing table: $table\n";
        
        foreach ($columns as $column) {
            // Check if column exists
            $check = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($check->rowCount() == 0) {
                continue;
            }
            
            echo "  - Column: $column ... ";
            
            // Method 1: Fix double-encoded UTF-8 (UTF-8 stored as Latin-1)
            // This converts: Ù…ØµØ± -> مصر
            $sql = "UPDATE `$table` 
                    SET `$column` = CONVERT(CAST(CONVERT(`$column` USING latin1) AS BINARY) USING utf8mb4)
                    WHERE `$column` IS NOT NULL 
                    AND `$column` != ''
                    AND `$column` REGEXP '[Ã¢Ã¡Ã©Ã­Ã³ÃºÙØ]'";
            
            try {
                $affected = $pdo->exec($sql);
                echo "Fixed $affected rows\n";
            } catch (Exception $e) {
                echo "Skipped (may not need fixing)\n";
            }
        }
    }
    
    echo "\n=== Verification ===\n";
    
    // Show sample data to verify
    $samples = [
        "SELECT name FROM zones LIMIT 3",
        "SELECT name FROM vehicle_brands LIMIT 3",
        "SELECT name FROM vehicle_models LIMIT 3",
        "SELECT first_name, last_name FROM users WHERE first_name REGEXP '[ا-ي]' LIMIT 3",
    ];
    
    foreach ($samples as $sql) {
        echo "\n$sql:\n";
        try {
            $result = $pdo->query($sql);
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                echo "  " . implode(" | ", $row) . "\n";
            }
        } catch (Exception $e) {
            echo "  Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Done! ===\n";
    echo "If Arabic still shows garbled, the data may need to be re-imported with proper encoding.\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
