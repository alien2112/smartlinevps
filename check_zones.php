<?php

require __DIR__ . '/rateel/vendor/autoload.php';

$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Checking Zones in Database ===\n\n";

try {
    // Count total zones
    $totalZones = DB::table('zones')->count();
    echo "Total zones: {$totalZones}\n\n";
    
    if ($totalZones > 0) {
        // Get all zones
        $zones = DB::table('zones')
            ->select('id', 'name', 'is_active', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get();
        
        echo "Zone Details:\n";
        echo str_repeat("-", 100) . "\n";
        echo sprintf("%-36s | %-30s | %-8s | %-19s\n", "ID", "Name", "Active", "Created At");
        echo str_repeat("-", 100) . "\n";
        
        foreach ($zones as $zone) {
            echo sprintf(
                "%-36s | %-30s | %-8s | %-19s\n",
                substr($zone->id, 0, 36),
                $zone->name,
                $zone->is_active ? 'Yes' : 'No',
                $zone->created_at
            );
        }
        echo str_repeat("-", 100) . "\n\n";
        
        // Check for Cairo Test Zone specifically
        $cairoZone = DB::table('zones')
            ->where('name', 'Cairo Test Zone')
            ->first();
        
        if ($cairoZone) {
            echo "✓ Cairo Test Zone found!\n";
            echo "  ID: {$cairoZone->id}\n";
            echo "  Active: " . ($cairoZone->is_active ? 'Yes' : 'No') . "\n";
            echo "  Created: {$cairoZone->created_at}\n";
        } else {
            echo "✗ Cairo Test Zone not found.\n";
        }
    } else {
        echo "No zones found in the database.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}


