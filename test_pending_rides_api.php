<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING PENDING RIDES API ===\n\n";

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

// Simulate the getPendingRides query
$vehicleCategoryIds = $user->vehicle->category_id ?? [];
if (is_string($vehicleCategoryIds)) {
    $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
}

$searchRadius = (double)(get_cache('search_radius') ?? 5) * 1000; // Convert km to meters

echo "Search radius: " . ($searchRadius / 1000) . " km\n";
echo "Vehicle categories: " . json_encode($vehicleCategoryIds) . "\n\n";

// Authenticate as this driver
\Auth::guard('api')->loginUsingId($user->id);

// Now call the repository method
$tripRepo = app(\Modules\TripManagement\Interfaces\TripRequestInterfaces::class);

try {
    $pendingTrips = $tripRepo->getPendingRides([
        'ride_count' => $driver->ride_count ?? 0,
        'parcel_count' => $driver->parcel_count ?? 0,
        'parcel_follow_status' => false,
        'max_parcel_request_accept_limit' => 2,
        'vehicle_category_id' => $vehicleCategoryIds,
        'driver_locations' => $location,
        'service' => $driver->service ?? null,
        'parcel_weight_capacity' => $user->vehicle->parcel_weight_capacity ?? null,
        'distance' => $searchRadius,
        'zone_id' => $location->zone_id,
        'relations' => ['driver.driverDetails', 'customer', 'ignoredRequests', 'time', 'fee', 'fare_biddings', 'parcel', 'parcelRefund'],
        'withAvgRelation' => 'customerReceivedReviews',
        'withAvgColumn' => 'rating',
        'limit' => 10,
        'offset' => 1
    ]);

    echo "=== RESULT ===\n";
    echo "Total pending trips found: " . $pendingTrips->count() . "\n\n";

    if ($pendingTrips->count() > 0) {
        echo "SUCCESS! ✓ Pending trips are now visible\n\n";
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
    } else {
        echo "FAIL! ✗ Still no pending trips found\n\n";
        echo "Let me check why...\n\n";

        // Debug: Check each filter step
        $allPending = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')->count();
        echo "Total pending trips in system: {$allPending}\n";

        $sameZone = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
            ->where('zone_id', $location->zone_id)
            ->count();
        echo "Pending trips in driver's zone: {$sameZone}\n";

        if ($sameZone > 0) {
            $trips = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
                ->where('zone_id', $location->zone_id)
                ->get();

            foreach ($trips as $trip) {
                echo "\nTrip {$trip->ref_id}:\n";
                echo "  Vehicle Category: {$trip->vehicle_category_id}\n";
                echo "  Driver has: " . json_encode($vehicleCategoryIds) . "\n";
                echo "  Match: " . (in_array($trip->vehicle_category_id, $vehicleCategoryIds) ? 'YES' : 'NO') . "\n";

                if ($trip->coordinate && $trip->coordinate->pickup_coordinates) {
                    $pickupLat = $trip->coordinate->pickup_coordinates->latitude;
                    $pickupLng = $trip->coordinate->pickup_coordinates->longitude;

                    // Calculate distance
                    $earthRadius = 6371000; // meters
                    $dLat = deg2rad($pickupLat - $location->latitude);
                    $dLng = deg2rad($pickupLng - $location->longitude);
                    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($location->latitude)) * cos(deg2rad($pickupLat)) * sin($dLng/2) * sin($dLng/2);
                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                    $distance = $earthRadius * $c;

                    echo "  Distance: " . round($distance/1000, 2) . " km (max: " . ($searchRadius/1000) . " km)\n";
                    echo "  Within range: " . ($distance < $searchRadius ? 'YES' : 'NO') . "\n";
                }
            }
        }
    }

} catch (\Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
