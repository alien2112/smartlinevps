<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\ZoneManagement\Entities\Zone;

echo "Searching for zones with 'Alex' in the name...\n";

$zones = Zone::where('name', 'LIKE', '%Alex%')
             ->orWhere('name', 'LIKE', '%إسكندرية%')
             ->orWhere('name', 'LIKE', '%الاسكندرية%')
             ->get();

if ($zones->count() > 0) {
    foreach ($zones as $zone) {
        echo "ID: " . $zone->id . "\n";
        echo "Name: " . $zone->name . "\n";
        echo "Status: " . ($zone->is_active ? 'Active' : 'Inactive') . "\n";
        echo "--------------------------\n";
    }
} else {
    echo "No zones found matching 'Alex'. Listing all zones to help you find it:\n";
    $allZones = Zone::all();
    foreach ($allZones as $zone) {
        echo "ID: " . $zone->id . " | Name: " . $zone->name . "\n";
    }
}
