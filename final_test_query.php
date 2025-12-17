<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\DB::enableQueryLog();

echo "=== FINAL QUERY TEST WITH DRIVER +201208673028 ===\n\n";

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();

$vehicleCategoryIds = json_decode($driver->vehicle->category_id, true);
$searchRadius = 5000;

echo "Driver: {$driver->first_name} {$driver->last_name}\n";
echo "Vehicle Categories: " . json_encode($vehicleCategoryIds) . "\n";
echo "Zone: {$location->zone_id}\n";
echo "Search Radius: 5 km\n\n";

echo "Building query...\n";

try {
    $query = \Modules\TripManagement\Entities\TripRequest::query()
        ->with([
            'fare_biddings' => fn($q) => $q->where('driver_id', $driver->id),
            'coordinate' => fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius)
        ])
        ->whereHas('coordinate',
            fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius))
        ->whereDoesntHave('ignoredRequests', fn($q) => $q->where('user_id', $driver->id))
        ->where(fn($q) => $q->whereIn('vehicle_category_id', (array) $vehicleCategoryIds)
            ->orWhereNull('vehicle_category_id')
        )
        ->where(['zone_id' => $location->zone_id, 'current_status' => 'pending'])
        ->orderBy('created_at', 'desc');

    echo "Executing query...\n\n";
    $results = $query->get();

    echo "=== RESULTS ===\n";
    echo "Count: " . $results->count() . "\n\n";

    if ($results->count() > 0) {
        echo "✓✓✓ SUCCESS! Trips found:\n\n";
        foreach ($results as $trip) {
            echo "Trip #{$trip->ref_id}\n";
            echo "  ID: {$trip->id}\n";
            echo "  Zone: {$trip->zone_id}\n";
            echo "  Vehicle Cat: {$trip->vehicle_category_id}\n";
            echo "  Status: {$trip->current_status}\n\n";
        }
    } else {
        echo "✗ NO RESULTS - Query returned empty\n\n";

        echo "=== EXECUTED SQL QUERIES ===\n";
        $queries = \DB::getQueryLog();
        foreach ($queries as $i => $q) {
            if (str_contains($q['query'], 'trip_request')) {
                echo "\nQuery " . ($i + 1) . ":\n";
                echo $q['query'] . "\n";
                echo "Bindings: " . json_encode($q['bindings']) . "\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";

    if (str_contains($e->getMessage(), 'SRID') || str_contains($e->getMessage(), 'ST_Distance')) {
        echo ">>> SPATIAL QUERY ERROR - The SRID fix may not be working!\n\n";

        echo "Let me test the raw SQL:\n";
        try {
            $result = \DB::select("
                SELECT COUNT(*) as count
                FROM trip_request_coordinates
                WHERE ST_Distance_Sphere(
                    ST_SRID(pickup_coordinates, 4326),
                    ST_SRID(POINT(?, ?), 4326)
                ) < ?
            ", [$location->longitude, $location->latitude, $searchRadius]);

            echo "Raw distance query works! Coordinates within range: " . $result[0]->count . "\n";
        } catch (\Exception $e2) {
            echo "Raw SQL also fails: {$e2->getMessage()}\n";
        }
    }

    echo "\nFull stack trace:\n";
    echo $e->getTraceAsString();
}
