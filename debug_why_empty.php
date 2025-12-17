<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUGGING WHY EMPTY RESPONSE ===\n\n";

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();

if (!$driver) {
    echo "✗ Driver not found!\n";
    exit;
}

echo "Driver: {$driver->first_name} {$driver->last_name} ({$driver->phone})\n";
echo "User ID: {$driver->id}\n\n";

// Check current status
echo "=== CURRENT STATUS ===\n";
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
echo "1. Online: " . ($driverDetail->is_online ? 'YES ✓' : 'NO ✗') . "\n";
echo "2. Available: " . ($driverDetail->availability_status == 'available' ? 'YES ✓' : 'NO ✗') . "\n";

$vehicle = $driver->vehicle;
if (!$vehicle) {
    echo "3. Vehicle: ✗ NOT FOUND!\n";
    exit;
}
echo "3. Vehicle: ✓ EXISTS\n";
echo "4. Vehicle Active: " . ($vehicle->is_active ? 'YES ✓' : 'NO ✗') . "\n";

$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
if (!$location) {
    echo "5. Location: ✗ NOT FOUND!\n";
    exit;
}
echo "5. Location: ✓ EXISTS (Zone: {$location->zone_id})\n\n";

// Check pending trips
echo "=== PENDING TRIPS CHECK ===\n";
$allPending = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')->count();
echo "Total pending trips in system: {$allPending}\n";

if ($allPending == 0) {
    echo "✗ NO PENDING TRIPS IN DATABASE!\n";
    echo "Please create a new trip request from customer app first.\n";
    exit;
}

$pendingInZone = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->count();
echo "Pending trips in driver's zone ({$location->zone_id}): {$pendingInZone}\n\n";

if ($pendingInZone == 0) {
    echo "✗ No trips in driver's zone!\n";
    $tripsInOtherZones = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
        ->where('zone_id', '!=', $location->zone_id)
        ->get();

    echo "\nPending trips in OTHER zones:\n";
    foreach ($tripsInOtherZones as $trip) {
        $zone = \Modules\ZoneManagement\Entities\Zone::find($trip->zone_id);
        $zoneName = $zone ? $zone->name : $trip->zone_id;
        echo "  - Trip {$trip->ref_id}: Zone {$zoneName}\n";
    }
    exit;
}

// Show trips in zone
$tripsInZone = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->get();

echo "Trips in driver's zone:\n";
foreach ($tripsInZone as $trip) {
    echo "  - Trip {$trip->ref_id}: Category {$trip->vehicle_category_id}, Type: {$trip->type}\n";
}

// Now test the actual API call
echo "\n=== SIMULATING API CALL ===\n\n";

$vehicleCats = json_decode($vehicle->category_id, true);
$cacheRadius = get_cache('search_radius');
$searchRadius = (double)($cacheRadius ? $cacheRadius : 5) * 1000;

echo "Parameters being used:\n";
echo "  Zone ID: {$location->zone_id}\n";
echo "  Vehicle Categories: " . json_encode($vehicleCats) . "\n";
echo "  Search Radius: " . ($searchRadius/1000) . " km\n";
echo "  Driver Location: Lat {$location->latitude}, Lng {$location->longitude}\n";
echo "  Ride Count: {$driverDetail->ride_count}\n";
echo "  Parcel Count: {$driverDetail->parcel_count}\n\n";

// Test step by step
echo "Testing filters step by step:\n\n";

// Step 1
$step1 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->count();
echo "1. Status=pending + Zone match: {$step1} trips\n";

// Step 2
$step2 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->where(fn($q) => $q->whereIn('vehicle_category_id', (array)$vehicleCats)->orWhereNull('vehicle_category_id'))
    ->count();
echo "2. + Vehicle category: {$step2} trips\n";

// Step 3 - Distance
$step3 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->where(fn($q) => $q->whereIn('vehicle_category_id', (array)$vehicleCats)->orWhereNull('vehicle_category_id'))
    ->whereHas('coordinate', fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius))
    ->count();
echo "3. + Distance check: {$step3} trips\n";

// Step 4 - Ignored requests
$step4 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->where(fn($q) => $q->whereIn('vehicle_category_id', (array)$vehicleCats)->orWhereNull('vehicle_category_id'))
    ->whereHas('coordinate', fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius))
    ->whereDoesntHave('ignoredRequests', fn($q) => $q->where('user_id', $driver->id))
    ->count();
echo "4. + Not ignored: {$step4} trips\n";

// Step 5 - Type filter
$step5 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->where(fn($q) => $q->whereIn('vehicle_category_id', (array)$vehicleCats)->orWhereNull('vehicle_category_id'))
    ->whereHas('coordinate', fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius))
    ->whereDoesntHave('ignoredRequests', fn($q) => $q->where('user_id', $driver->id))
    ->where(function ($query) use ($driverDetail) {
        if ($driverDetail->ride_count < 1) {
            $query->where('type', 'ride_request');
        }
        $query->orWhere(function ($query) {
            $query->where('type', 'parcel');
        });
    })
    ->count();
echo "5. + Type filter: {$step5} trips\n\n";

if ($step5 == 0) {
    echo "✗ PROBLEM: The query returns 0 after all filters\n\n";

    if ($step3 > 0 && $step4 == 0) {
        echo ">>> ISSUE: Driver has IGNORED these trips!\n";
        $ignored = \Modules\TripManagement\Entities\RejectedDriverRequest::where('user_id', $driver->id)->get();
        echo "Ignored trip IDs:\n";
        foreach ($ignored as $ign) {
            echo "  - {$ign->trip_request_id}\n";
        }
        echo "\nTo clear ignored trips:\n";
        echo "DELETE FROM rejected_driver_requests WHERE user_id = '{$driver->id}';\n";
    }

    if ($step2 > 0 && $step3 == 0) {
        echo ">>> ISSUE: Distance is too far!\n";
        echo "Current search radius: " . ($searchRadius/1000) . " km\n";
        echo "Try increasing it or moving driver closer to pickup.\n";
    }
} else {
    echo "✓ Query should return {$step5} trip(s)!\n\n";
    echo "If API still returns empty, the issue is with:\n";
    echo "- Missing or wrong zoneId header\n";
    echo "- Wrong base URL\n";
    echo "- Authentication issue\n";
}
