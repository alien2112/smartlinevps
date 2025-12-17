<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING DRIVER: +201208673028 ===\n\n";

// Try different phone number formats
$driverPhones = [
    '+201208673028',
    '201208673028',
    '01208673028',
    '+20 120 867 3028',
];

$driver = null;
foreach ($driverPhones as $phone) {
    $driver = \Modules\UserManagement\Entities\User::where('phone', $phone)->first();
    if ($driver) {
        echo "✓ Found driver with phone: {$phone}\n\n";
        break;
    }
}

if (!$driver) {
    echo "✗ Driver not found! Tried: " . implode(', ', $driverPhones) . "\n";
    echo "\nSearching in database for similar phones...\n";
    $similar = \Modules\UserManagement\Entities\User::where('phone', 'LIKE', '%120867302%')->get();
    if ($similar->count() > 0) {
        echo "Found similar:\n";
        foreach ($similar as $s) {
            echo "  - {$s->phone} ({$s->first_name} {$s->last_name})\n";
        }
    }
    exit;
}

echo "=== DRIVER DETAILS ===\n";
echo "Name: {$driver->first_name} {$driver->last_name}\n";
echo "Phone: {$driver->phone}\n";
echo "User ID: {$driver->id}\n";
echo "Email: {$driver->email}\n";

$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
if ($driverDetail) {
    echo "\n=== DRIVER STATUS ===\n";
    echo "Online: " . ($driverDetail->is_online ? 'YES ✓' : 'NO ✗') . "\n";
    echo "Availability: {$driverDetail->availability_status}\n";
    echo "Ride Count: {$driverDetail->ride_count}\n";
    echo "Parcel Count: {$driverDetail->parcel_count}\n";
} else {
    echo "\n✗ ERROR: No driver_details record found!\n";
    exit;
}

if ($driver->vehicle) {
    echo "\n=== VEHICLE ===\n";
    echo "Vehicle ID: {$driver->vehicle->id}\n";
    echo "Category IDs (raw): {$driver->vehicle->category_id}\n";

    $cats = is_string($driver->vehicle->category_id)
        ? json_decode($driver->vehicle->category_id, true)
        : $driver->vehicle->category_id;
    echo "Category IDs (parsed): " . json_encode($cats) . "\n";
} else {
    echo "\n✗ ERROR: No vehicle assigned!\n";
}

$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
if ($location) {
    echo "\n=== LOCATION ===\n";
    echo "Latitude: {$location->latitude}\n";
    echo "Longitude: {$location->longitude}\n";
    echo "Zone ID: {$location->zone_id}\n";

    $zone = \Modules\ZoneManagement\Entities\Zone::find($location->zone_id);
    if ($zone) {
        echo "Zone Name: {$zone->name}\n";
    }
} else {
    echo "\n✗ ERROR: No location record found!\n";
}

// Check latest pending trip
echo "\n\n=== LATEST PENDING TRIP ===\n";
$trip = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->first();

if ($trip) {
    echo "Trip Ref: {$trip->ref_id}\n";
    echo "Trip ID: {$trip->id}\n";
    echo "Zone ID: {$trip->zone_id}\n";

    $tripZone = \Modules\ZoneManagement\Entities\Zone::find($trip->zone_id);
    if ($tripZone) {
        echo "Zone Name: {$tripZone->name}\n";
    }

    echo "Vehicle Category Required: " . ($trip->vehicle_category_id ?? 'NULL (any)') . "\n";
    echo "Type: {$trip->type}\n";

    if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
        echo "Pickup: Lat {$trip->coordinate->pickup_coordinates->latitude}, Lng {$trip->coordinate->pickup_coordinates->longitude}\n";
    }

    echo "\n=== MATCHING CHECK ===\n";

    $issues = [];

    // Check 1: Driver online and available
    if (!$driverDetail || !$driverDetail->is_online || $driverDetail->availability_status != 'available') {
        echo "✗ Driver is NOT online/available\n";
        $issues[] = "Driver must be online and available";
    } else {
        echo "✓ Driver is online and available\n";
    }

    // Check 2: Zone match
    if (!$location) {
        echo "✗ No driver location\n";
        $issues[] = "Driver has no location set";
    } elseif ($trip->zone_id == $location->zone_id) {
        echo "✓ Zones MATCH ({$tripZone->name})\n";
    } else {
        echo "✗ Zones DON'T MATCH\n";
        echo "  Driver zone: {$location->zone_id}\n";
        echo "  Trip zone: {$trip->zone_id}\n";
        $issues[] = "Zone mismatch";
    }

    // Check 3: Vehicle category
    if (!$driver->vehicle) {
        echo "✗ No vehicle assigned\n";
        $issues[] = "Driver has no vehicle";
    } else {
        $cats = is_string($driver->vehicle->category_id)
            ? json_decode($driver->vehicle->category_id, true)
            : $driver->vehicle->category_id;

        if (is_null($trip->vehicle_category_id) || in_array($trip->vehicle_category_id, $cats ?? [])) {
            echo "✓ Vehicle category MATCHES\n";
        } else {
            echo "✗ Vehicle category DOESN'T MATCH\n";
            echo "  Trip needs: {$trip->vehicle_category_id}\n";
            echo "  Driver has: " . json_encode($cats) . "\n";
            $issues[] = "Vehicle category mismatch";
        }
    }

    // Check 4: Distance
    if ($location && $trip->coordinate && $trip->coordinate->pickup_coordinates) {
        $pickupLat = $trip->coordinate->pickup_coordinates->latitude;
        $pickupLng = $trip->coordinate->pickup_coordinates->longitude;
        $driverLat = $location->latitude;
        $driverLng = $location->longitude;

        $earthRadius = 6371000;
        $dLat = deg2rad($pickupLat - $driverLat);
        $dLng = deg2rad($pickupLng - $driverLng);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($driverLat)) * cos(deg2rad($pickupLat)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c;

        $searchRadius = 5000;
        echo "\nDistance from pickup: " . round($distance/1000, 2) . " km (max: 5 km)\n";

        if ($distance < $searchRadius) {
            echo "✓ Within search radius\n";
        } else {
            echo "✗ TOO FAR from pickup\n";
            $issues[] = "Driver is " . round($distance/1000, 2) . " km away (max 5 km)";
        }
    }

    if (count($issues) > 0) {
        echo "\n\n=== ISSUES PREVENTING MATCH ===\n";
        foreach ($issues as $i => $issue) {
            echo ($i + 1) . ". {$issue}\n";
        }
    } else {
        echo "\n\n✓✓✓ ALL CHECKS PASSED! Trip should be visible! ✓✓✓\n";
    }
} else {
    echo "No pending trips found!\n";
}
