<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Enable query logging
\DB::enableQueryLog();

echo "=== STEP-BY-STEP QUERY DEBUG ===\n\n";

// Get a valid online driver
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::whereNotNull('user_id')
    ->whereIn('user_id', \Modules\UserManagement\Entities\User::pluck('id'))
    ->where('is_online', 1)
    ->where('availability_status', 'available')
    ->first();

if (!$driverDetail) {
    echo "ERROR: No online available driver found!\n";
    exit;
}

$user = \Modules\UserManagement\Entities\User::find($driverDetail->user_id);
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $user->id)->first();

if (!$user || !$location) {
    echo "ERROR: Driver has no user or location!\n";
    exit;
}

echo "Testing with Driver: {$user->first_name} {$user->last_name}\n";
echo "User ID: {$user->id}\n";
echo "Zone: {$location->zone_id}\n";
echo "Location: Lat {$location->latitude}, Lng {$location->longitude}\n\n";

$vehicleCategoryIds = $user->vehicle->category_id ?? [];
if (is_string($vehicleCategoryIds)) {
    $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
}
echo "Vehicle Categories: " . json_encode($vehicleCategoryIds) . "\n\n";

$searchRadius = 5000; // 5km

// Start building query step by step
$query = \Modules\TripManagement\Entities\TripRequest::query();

echo "STEP 1: All trip requests\n";
$count1 = $query->count();
echo "  Count: {$count1}\n\n";

echo "STEP 2: Filter by PENDING status\n";
$query = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending');
$count2 = $query->count();
echo "  Count: {$count2}\n\n";

echo "STEP 3: Filter by zone\n";
$query = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id);
$count3 = $query->count();
echo "  Count: {$count3}\n";
if ($count3 > 0) {
    $trips = $query->get();
    foreach ($trips as $trip) {
        echo "    - Trip {$trip->ref_id}: Vehicle Cat {$trip->vehicle_category_id}\n";
    }
}
echo "\n";

echo "STEP 4: Filter by vehicle category (whereIn)\n";
$query = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->where(fn($q) => $q->whereIn('vehicle_category_id', (array) $vehicleCategoryIds)
        ->orWhereNull('vehicle_category_id')
    );
$count4 = $query->count();
echo "  Count: {$count4}\n";
if ($count4 > 0) {
    $trips = $query->get();
    foreach ($trips as $trip) {
        echo "    - Trip {$trip->ref_id}: Vehicle Cat {$trip->vehicle_category_id}\n";
    }
}
echo "\n";

echo "STEP 5: Filter by coordinate distance (THIS IS THE CRITICAL ONE)\n";
$query = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->where('zone_id', $location->zone_id)
    ->where(fn($q) => $q->whereIn('vehicle_category_id', (array) $vehicleCategoryIds)
        ->orWhereNull('vehicle_category_id')
    )
    ->whereHas('coordinate', function($q) use ($location, $searchRadius) {
        $q->distanceSphere('pickup_coordinates', $location, $searchRadius);
    });

try {
    $count5 = $query->count();
    echo "  Count: {$count5}\n";
    if ($count5 > 0) {
        $trips = $query->get();
        foreach ($trips as $trip) {
            echo "    - Trip {$trip->ref_id}\n";
        }
    }
} catch (\Exception $e) {
    echo "  ERROR: {$e->getMessage()}\n";
    echo "\n  This is likely the SRID or spatial query issue!\n";
}

echo "\n=== QUERIES EXECUTED ===\n";
$queries = \DB::getQueryLog();
foreach ($queries as $i => $query) {
    echo "\nQuery " . ($i + 1) . ":\n";
    echo "  SQL: " . $query['query'] . "\n";
    echo "  Bindings: " . json_encode($query['bindings']) . "\n";
}

echo "\n\n=== TESTING RAW DISTANCE QUERY ===\n";
try {
    // Test the exact SQL that's being generated
    $result = \DB::select("
        SELECT COUNT(*) as count
        FROM trip_request_coordinates
        WHERE ST_Distance_Sphere(
            ST_SRID(pickup_coordinates, 4326),
            ST_SRID(POINT(?, ?), 4326)
        ) < ?
    ", [$location->longitude, $location->latitude, $searchRadius]);

    echo "Raw distance query result: " . $result[0]->count . " coordinates within range\n";
} catch (\Exception $e) {
    echo "ERROR in raw query: {$e->getMessage()}\n";
}
