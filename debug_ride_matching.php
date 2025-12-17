<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== PENDING RIDE MATCHING DEBUG ===\n\n";

// Get the pending trip
$trip = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->first();

if (!$trip) {
    echo "ERROR: No pending trips found!\n";
    exit;
}

echo "TRIP DETAILS:\n";
echo "  ID: {$trip->id}\n";
echo "  Ref: {$trip->ref_id}\n";
echo "  Zone ID: {$trip->zone_id}\n";
echo "  Vehicle Category: {$trip->vehicle_category_id}\n";
echo "  Status: {$trip->current_status}\n";

if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
    echo "  Pickup: Lat {$trip->coordinate->pickup_coordinates->latitude}, Lng {$trip->coordinate->pickup_coordinates->longitude}\n";
}

// Get online driver
$driver = \Modules\UserManagement\Entities\DriverDetail::where('is_online', 1)
    ->where('availability_status', 'available')
    ->first();

if (!$driver) {
    echo "\nERROR: No available online drivers found!\n";
    exit;
}

echo "\nDRIVER DETAILS:\n";
echo "  User ID: {$driver->user_id}\n";
echo "  Online: {$driver->is_online}\n";
echo "  Status: {$driver->availability_status}\n";

$user = \Modules\UserManagement\Entities\User::find($driver->user_id);

if (!$user) {
    echo "  ERROR: User not found!\n";
    exit;
}

// Check vehicle
if (!$user->vehicle) {
    echo "  ERROR: Driver has no vehicle!\n";
    exit;
}

echo "  Vehicle ID: {$user->vehicle->id}\n";
echo "  Vehicle Category IDs (raw): {$user->vehicle->category_id}\n";

$vehicleCategoryIds = $user->vehicle->category_id;
if (is_string($vehicleCategoryIds)) {
    $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
}
echo "  Vehicle Category IDs (decoded): " . json_encode($vehicleCategoryIds) . "\n";

// Check location
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->user_id)->first();

if (!$location) {
    echo "  ERROR: Driver has no location!\n";
    exit;
}

echo "  Location: Lat {$location->latitude}, Lng {$location->longitude}\n";
echo "  Zone ID: {$location->zone_id}\n";

echo "\n=== MATCHING CHECKS ===\n";

// Check 1: Zone match
echo "1. Zone Match: ";
if ($trip->zone_id == $location->zone_id) {
    echo "✓ PASS (both zone {$trip->zone_id})\n";
} else {
    echo "✗ FAIL (trip zone: {$trip->zone_id}, driver zone: {$location->zone_id})\n";
}

// Check 2: Vehicle category match
echo "2. Vehicle Category Match: ";
if (in_array($trip->vehicle_category_id, $vehicleCategoryIds) || is_null($trip->vehicle_category_id)) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL (trip needs: {$trip->vehicle_category_id}, driver has: " . json_encode($vehicleCategoryIds) . ")\n";
}

// Check 3: Distance
echo "3. Distance Check: ";
$searchRadius = 5000; // 5km in meters

if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
    $pickupLat = $trip->coordinate->pickup_coordinates->latitude;
    $pickupLng = $trip->coordinate->pickup_coordinates->longitude;
    $driverLat = $location->latitude;
    $driverLng = $location->longitude;

    // Calculate distance using Haversine formula
    $earthRadius = 6371000; // meters
    $dLat = deg2rad($pickupLat - $driverLat);
    $dLng = deg2rad($pickupLng - $driverLng);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($driverLat)) * cos(deg2rad($pickupLat)) * sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earthRadius * $c;

    echo number_format($distance, 2) . "m / {$searchRadius}m - ";
    if ($distance < $searchRadius) {
        echo "✓ PASS\n";
    } else {
        echo "✗ FAIL (too far)\n";
    }
} else {
    echo "✗ FAIL (no coordinates)\n";
}

// Check 4: Ignored requests
echo "4. Not Ignored: ";
$ignored = \Modules\TripManagement\Entities\RejectedDriverRequest::where('trip_request_id', $trip->id)
    ->where('user_id', $driver->user_id)
    ->exists();
if (!$ignored) {
    echo "✓ PASS\n";
} else {
    echo "✗ FAIL (driver has ignored this trip)\n";
}

echo "\n";
