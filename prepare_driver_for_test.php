<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PREPARING DRIVER FOR PENDING LIST TEST ===\n\n";

$driverPhone = '+201208673028';
$driver = \Modules\UserManagement\Entities\User::where('phone', $driverPhone)->first();

if (!$driver) {
    echo "✗ Driver not found!\n";
    exit;
}

echo "Driver: {$driver->first_name} {$driver->last_name}\n";
echo "Phone: {$driver->phone}\n";
echo "ID: {$driver->id}\n\n";

$issues = [];
$fixes = [];

// Check 1: Driver Details
echo "CHECK 1: Driver Details\n";
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
if (!$driverDetail) {
    echo "  ✗ No driver_details record\n";
    $issues[] = "No driver_details record";
} else {
    echo "  ✓ Driver details exist\n";

    // Check if online
    if ($driverDetail->is_online != 1) {
        echo "  ✗ Driver is OFFLINE (is_online = {$driverDetail->is_online})\n";
        $issues[] = "Driver is offline";
        $fixes[] = "UPDATE driver_details SET is_online = 1 WHERE user_id = '{$driver->id}'";
    } else {
        echo "  ✓ Driver is ONLINE\n";
    }

    // Check availability
    if ($driverDetail->availability_status != 'available') {
        echo "  ✗ Driver is NOT AVAILABLE (status = {$driverDetail->availability_status})\n";
        $issues[] = "Driver not available";
        $fixes[] = "UPDATE driver_details SET availability_status = 'available' WHERE user_id = '{$driver->id}'";
    } else {
        echo "  ✓ Driver is AVAILABLE\n";
    }
}

// Check 2: Vehicle
echo "\nCHECK 2: Vehicle\n";
if (!$driver->vehicle) {
    echo "  ✗ No vehicle assigned\n";
    $issues[] = "No vehicle assigned";
} else {
    echo "  ✓ Vehicle exists (ID: {$driver->vehicle->id})\n";
    echo "    Category IDs: {$driver->vehicle->category_id}\n";

    if ($driver->vehicle->is_active != 1) {
        echo "  ✗ Vehicle is NOT ACTIVE (is_active = {$driver->vehicle->is_active})\n";
        $issues[] = "Vehicle not active";
        $fixes[] = "UPDATE vehicles SET is_active = 1 WHERE id = '{$driver->vehicle->id}'";
    } else {
        echo "  ✓ Vehicle is ACTIVE\n";
    }
}

// Check 3: Location
echo "\nCHECK 3: Driver Location\n";
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
if (!$location) {
    echo "  ✗ No location record\n";
    $issues[] = "No location record";
} else {
    echo "  ✓ Location exists\n";
    echo "    Latitude: {$location->latitude}\n";
    echo "    Longitude: {$location->longitude}\n";
    echo "    Zone ID: {$location->zone_id}\n";

    $zone = \Modules\ZoneManagement\Entities\Zone::find($location->zone_id);
    if ($zone) {
        echo "    Zone Name: {$zone->name}\n";
    }
}

// Check 4: Auth Token
echo "\nCHECK 4: Authentication Token\n";
$token = \DB::table('oauth_access_tokens')
    ->where('user_id', $driver->id)
    ->where('revoked', 0)
    ->where('expires_at', '>', now())
    ->orderBy('created_at', 'desc')
    ->first();

if (!$token) {
    echo "  ✗ No valid auth token - Driver needs to LOGIN first!\n";
    $issues[] = "No valid auth token";
} else {
    echo "  ✓ Valid token exists (expires: {$token->expires_at})\n";
}

// Check 5: Pending Trip
echo "\nCHECK 5: Pending Trip Available\n";
$pendingTrip = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->first();

if (!$pendingTrip) {
    echo "  ✗ No pending trips in system\n";
    $issues[] = "No pending trips";
} else {
    echo "  ✓ Pending trip exists (Ref: {$pendingTrip->ref_id})\n";
    echo "    Zone: {$pendingTrip->zone_id}\n";
    echo "    Vehicle Category: {$pendingTrip->vehicle_category_id}\n";

    if ($location && $pendingTrip->zone_id != $location->zone_id) {
        echo "  ✗ Zone MISMATCH (Driver: {$location->zone_id}, Trip: {$pendingTrip->zone_id})\n";
        $issues[] = "Zone mismatch";
    }
}

// Summary
echo "\n" . str_repeat("=", 60) . "\n";
if (count($issues) == 0) {
    echo "✓✓✓ ALL CHECKS PASSED! ✓✓✓\n\n";
    echo "You can now call the API:\n\n";
    echo "Endpoint: GET /api/driver/ride/pending-ride-list?limit=10&offset=1\n\n";
    echo "Required Headers:\n";
    echo "  Authorization: Bearer {$token->id}\n";
    echo "  zoneId: {$location->zone_id}\n";
    echo "  Content-Type: application/json\n";
    echo "  Accept: application/json\n\n";

    echo "Expected Result: 1 pending trip\n";
} else {
    echo "✗ ISSUES FOUND:\n\n";
    foreach ($issues as $i => $issue) {
        echo ($i + 1) . ". {$issue}\n";
    }

    if (count($fixes) > 0) {
        echo "\n=== FIXES TO APPLY ===\n\n";
        foreach ($fixes as $fix) {
            echo "{$fix};\n";
        }

        echo "\nDo you want me to apply these fixes? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));

        if (strtolower($line) == 'y' || strtolower($line) == 'yes') {
            echo "\nApplying fixes...\n";
            foreach ($fixes as $fix) {
                try {
                    \DB::statement($fix);
                    echo "✓ Applied: " . substr($fix, 0, 80) . "...\n";
                } catch (\Exception $e) {
                    echo "✗ Failed: {$e->getMessage()}\n";
                }
            }
            echo "\nFixes applied! Please run this script again to verify.\n";
        }
    }

    if (in_array("No valid auth token", $issues)) {
        echo "\n=== TO GET AUTH TOKEN ===\n";
        echo "Call the login API:\n";
        echo "POST /api/customer/auth/login\n";
        echo "Body: {\n";
        echo "  \"phone\": \"+201208673028\",\n";
        echo "  \"password\": \"password123\"\n";
        echo "}\n";
    }
}
