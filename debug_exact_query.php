<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\DB::listen(function($query) {
    if (str_contains($query->sql, 'trip_request') && str_contains($query->sql, 'ST_Distance')) {
        echo "=== FULL SQL QUERY ===\n";
        echo $query->sql . "\n\n";
        echo "Bindings:\n";
        print_r($query->bindings);
        echo "\n\n";
    }
});

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
$vehicleCats = json_decode($driver->vehicle->category_id, true);

echo "Driver location: Lat {$location->latitude}, Lng {$location->longitude}\n";
echo "Zone: {$location->zone_id}\n";
echo "Vehicle categories: " . json_encode($vehicleCats) . "\n\n";

$searchRadius = (double)(get_cache('search_radius') ?? 5) * 1000;
echo "Search radius from cache: {$searchRadius} meters\n\n";

// Mock auth
app()->instance('auth', new class($driver) {
    private $driver;
    public function __construct($driver) { $this->driver = $driver; }
    public function guard($guard = null) { return $this; }
    public function user() { return $this->driver; }
    public function id() { return $this->driver->id; }
});

$repo = app(\Modules\TripManagement\Interfaces\TripRequestInterfaces::class);

echo "Calling getPendingRides...\n\n";

$result = $repo->getPendingRides([
    'ride_count' => $driverDetail->ride_count,
    'parcel_count' => $driverDetail->parcel_count,
    'parcel_follow_status' => $driverDetail->parcel_follow_status,
    'max_parcel_request_accept_limit' => 2,
    'vehicle_category_id' => $vehicleCats,
    'driver_locations' => $location,
    'distance' => $searchRadius,
    'zone_id' => $location->zone_id,
    'limit' => 10,
    'offset' => 1
]);

echo "Results: {$result->count()} trips\n\n";

if ($result->count() == 0) {
    echo "Testing each condition separately...\n\n";

    // Test 1: Just pending
    $test1 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')->count();
    echo "1. Pending trips: {$test1}\n";

    // Test 2: Pending + zone
    $test2 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
        ->where('zone_id', $location->zone_id)
        ->count();
    echo "2. Pending + zone: {$test2}\n";

    // Test 3: Pending + zone + vehicle
    $test3 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
        ->where('zone_id', $location->zone_id)
        ->where(fn($q) => $q->whereIn('vehicle_category_id', $vehicleCats)->orWhereNull('vehicle_category_id'))
        ->count();
    echo "3. Pending + zone + vehicle: {$test3}\n";

    // Test 4: With distance
    $test4 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
        ->where('zone_id', $location->zone_id)
        ->where(fn($q) => $q->whereIn('vehicle_category_id', $vehicleCats)->orWhereNull('vehicle_category_id'))
        ->whereHas('coordinate', fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius))
        ->count();
    echo "4. Pending + zone + vehicle + distance: {$test4}\n";

    // Test 5: With type filter
    $test5 = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
        ->where('zone_id', $location->zone_id)
        ->where(fn($q) => $q->whereIn('vehicle_category_id', $vehicleCats)->orWhereNull('vehicle_category_id'))
        ->whereHas('coordinate', fn($q) => $q->distanceSphere('pickup_coordinates', $location, $searchRadius))
        ->where(function ($query) {
            $query->where('type', 'ride_request')->orWhere('type', 'parcel');
        })
        ->count();
    echo "5. Pending + zone + vehicle + distance + type: {$test5}\n";
}
