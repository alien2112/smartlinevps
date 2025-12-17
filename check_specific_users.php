<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== CHECKING SPECIFIC TEST USERS ===\n\n";

// Try different phone number formats
$driverPhones = [
    '+20 101 274 8258',
    '+201012748258',
    '01012748258',
    '201012748258',
];

echo "Looking for DRIVER with phone variations...\n";
$driver = null;
foreach ($driverPhones as $phone) {
    $driver = \Modules\UserManagement\Entities\User::where('phone', $phone)->first();
    if ($driver) {
        echo "Found driver with phone: {$phone}\n";
        break;
    }
}

if (!$driver) {
    echo "Driver not found! Tried: " . implode(', ', $driverPhones) . "\n";
    exit;
}

echo "\n=== DRIVER DETAILS ===\n";
echo "Name: {$driver->first_name} {$driver->last_name}\n";
echo "Phone: {$driver->phone}\n";
echo "User ID: {$driver->id}\n";
echo "Email: {$driver->email}\n";

$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
if ($driverDetail) {
    echo "\nDriver Status:\n";
    echo "  Online: " . ($driverDetail->is_online ? 'YES' : 'NO') . "\n";
    echo "  Availability: {$driverDetail->availability_status}\n";
} else {
    echo "\nERROR: No driver_details record found!\n";
}

if ($driver->vehicle) {
    echo "\nVehicle:\n";
    echo "  Vehicle ID: {$driver->vehicle->id}\n";
    echo "  Category IDs: {$driver->vehicle->category_id}\n";

    $cats = is_string($driver->vehicle->category_id)
        ? json_decode($driver->vehicle->category_id, true)
        : $driver->vehicle->category_id;
    echo "  Decoded: " . json_encode($cats) . "\n";
} else {
    echo "\nERROR: No vehicle assigned!\n";
}

$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
if ($location) {
    echo "\nLocation:\n";
    echo "  Latitude: {$location->latitude}\n";
    echo "  Longitude: {$location->longitude}\n";
    echo "  Zone ID: {$location->zone_id}\n";

    $zone = \Modules\ZoneManagement\Entities\Zone::find($location->zone_id);
    if ($zone) {
        echo "  Zone Name: {$zone->name}\n";
    }
} else {
    echo "\nERROR: No location record found!\n";
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

    echo "Vehicle Category Required: {$trip->vehicle_category_id}\n";
    echo "Type: {$trip->type}\n";
    echo "Status: {$trip->current_status}\n";

    if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
        echo "Pickup Location: Lat {$trip->coordinate->pickup_coordinates->latitude}, Lng {$trip->coordinate->pickup_coordinates->longitude}\n";
    }

    echo "\n=== COMPATIBILITY CHECK ===\n";

    // Zone match
    if ($location && $trip->zone_id == $location->zone_id) {
        echo "✓ Zones MATCH\n";
    } else {
        echo "✗ Zones DON'T MATCH (Driver: {$location->zone_id}, Trip: {$trip->zone_id})\n";
    }

    // Vehicle category match
    if ($driver->vehicle) {
        $cats = is_string($driver->vehicle->category_id)
            ? json_decode($driver->vehicle->category_id, true)
            : $driver->vehicle->category_id;

        if (in_array($trip->vehicle_category_id, $cats) || is_null($trip->vehicle_category_id)) {
            echo "✓ Vehicle Category MATCHES\n";
        } else {
            echo "✗ Vehicle Category DOESN'T MATCH\n";
            echo "  Trip needs: {$trip->vehicle_category_id}\n";
            echo "  Driver has: " . json_encode($cats) . "\n";
        }
    }

    // Distance check
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

        $searchRadius = 5000; // 5km
        echo "\nDistance: " . round($distance/1000, 2) . " km (max: 5 km)\n";

        if ($distance < $searchRadius) {
            echo "✓ Within search radius\n";
        } else {
            echo "✗ TOO FAR from pickup location\n";
        }
    }
} else {
    echo "No pending trips found!\n";
}
