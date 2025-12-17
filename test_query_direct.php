<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DIRECT QUERY TEST ===\n\n";

// Get the driver
$driver = \Modules\UserManagement\Entities\DriverDetail::whereNotNull('user_id')
    ->whereIn('user_id', \Modules\UserManagement\Entities\User::pluck('id'))
    ->where('is_online', 1)
    ->where('availability_status', 'available')
    ->first();

if (!$driver) {
    echo "ERROR: No online driver found\n";
    exit;
}

$user = \Modules\UserManagement\Entities\User::find($driver->user_id);
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $user->id)->first();

echo "Testing with Driver: {$user->first_name} {$user->last_name}\n";
echo "Driver Zone: {$location->zone_id}\n";
echo "Driver Location: Lat {$location->latitude}, Lng {$location->longitude}\n\n";

$vehicleCategoryIds = $user->vehicle->category_id ?? [];
if (is_string($vehicleCategoryIds)) {
    $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
}

$searchRadius = 5000; // 5km in meters

echo "Search radius: 5 km\n";
echo "Vehicle categories: " . json_encode($vehicleCategoryIds) . "\n\n";

// Build the query manually without authentication
$query = \Modules\TripManagement\Entities\TripRequest::query()
    ->with([
        'fare_biddings' => fn($query) => $query->where('driver_id', $user->id),
        'coordinate' => fn($query) => $query->distanceSphere('pickup_coordinates', $location, $searchRadius)
    ])
    ->whereHas('coordinate',
        fn($query) => $query->distanceSphere('pickup_coordinates', $location, $searchRadius))
    ->whereDoesntHave('ignoredRequests', fn($query) => $query->where('user_id', $user->id))
    ->where(fn($query) => $query->whereIn('vehicle_category_id', (array) $vehicleCategoryIds)
        ->orWhereNull('vehicle_category_id')
    )
    ->where(['zone_id' => $location->zone_id, 'current_status' => 'pending'])
    ->orderBy('created_at', 'desc');

echo "=== EXECUTING QUERY ===\n";

try {
    $pendingTrips = $query->get();

    echo "Total pending trips found: " . $pendingTrips->count() . "\n\n";

    if ($pendingTrips->count() > 0) {
        echo "✓ SUCCESS! Pending trips are now visible!\n\n";
        foreach ($pendingTrips as $trip) {
            echo "Trip #{$trip->ref_id}:\n";
            echo "  ID: {$trip->id}\n";
            echo "  Zone: {$trip->zone_id}\n";
            echo "  Vehicle Category: {$trip->vehicle_category_id}\n";
            echo "  Type: {$trip->type}\n";
            echo "  Status: {$trip->current_status}\n";
            if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
                echo "  Pickup: Lat {$trip->coordinate->pickup_coordinates->latitude}, Lng {$trip->coordinate->pickup_coordinates->longitude}\n";
            }
            echo "\n";
        }

        echo "The API endpoint should now return these trips!\n";
    } else {
        echo "✗ Still no trips found. Debugging...\n\n";

        // Step-by-step debug
        echo "STEP 1: All pending trips\n";
        $step1 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')->count();
        echo "  Count: {$step1}\n\n";

        echo "STEP 2: Pending trips in driver's zone\n";
        $step2 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
            ->where('zone_id', $location->zone_id)
            ->count();
        echo "  Count: {$step2}\n\n";

        if ($step2 > 0) {
            $zoneTrips = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
                ->where('zone_id', $location->zone_id)
                ->get();

            foreach ($zoneTrips as $trip) {
                echo "Trip {$trip->ref_id}:\n";
                echo "  Vehicle Cat: {$trip->vehicle_category_id}\n";
                echo "  Driver has: " . json_encode($vehicleCategoryIds) . "\n";

                $vehicleMatch = in_array($trip->vehicle_category_id, $vehicleCategoryIds) || is_null($trip->vehicle_category_id);
                echo "  Vehicle match: " . ($vehicleMatch ? 'YES ✓' : 'NO ✗') . "\n";

                if (!$vehicleMatch) {
                    echo "  >>> PROBLEM: Vehicle category mismatch!\n";
                }

                if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
                    try {
                        $pickupLat = $trip->coordinate->pickup_coordinates->latitude;
                        $pickupLng = $trip->coordinate->pickup_coordinates->longitude;

                        $earthRadius = 6371000;
                        $dLat = deg2rad($pickupLat - $location->latitude);
                        $dLng = deg2rad($pickupLng - $location->longitude);
                        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($location->latitude)) * cos(deg2rad($pickupLat)) * sin($dLng/2) * sin($dLng/2);
                        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                        $distance = $earthRadius * $c;

                        echo "  Distance: " . round($distance/1000, 2) . " km\n";
                        $withinRange = $distance < $searchRadius;
                        echo "  Within range: " . ($withinRange ? 'YES ✓' : 'NO ✗') . "\n";

                        if (!$withinRange) {
                            echo "  >>> PROBLEM: Driver too far from pickup location!\n";
                        }
                    } catch (\Exception $e) {
                        echo "  ERROR calculating distance: {$e->getMessage()}\n";
                    }
                } else {
                    echo "  >>> PROBLEM: No coordinates for trip!\n";
                }
                echo "\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";

    if (str_contains($e->getMessage(), 'SRID') || str_contains($e->getMessage(), 'ST_Distance')) {
        echo "This is a spatial/SRID error. The distance query is still failing.\n";
    }
}
