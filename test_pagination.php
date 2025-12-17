<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TESTING PAGINATION ===\n\n";

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
$vehicle = $driver->vehicle;
$vehicleCats = json_decode($vehicle->category_id, true);

echo "Testing with different offset values:\n\n";

$repo = app(\Modules\TripManagement\Interfaces\TripRequestInterfaces::class);
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
$searchRadius = 3000;

// Mock auth
app()->singleton('auth', function () use ($driver) {
    return new class($driver) {
        private $driver;
        public function __construct($driver) { $this->driver = $driver; }
        public function guard($guard = null) { return $this; }
        public function user() { return $this->driver; }
        public function id() { return $this->driver->id; }
    };
});

$params = [
    'ride_count' => $driverDetail->ride_count,
    'parcel_count' => $driverDetail->parcel_count,
    'parcel_follow_status' => false,
    'max_parcel_request_accept_limit' => 2,
    'vehicle_category_id' => $vehicleCats,
    'driver_locations' => $location,
    'distance' => $searchRadius,
    'zone_id' => $location->zone_id,
    'limit' => 10
];

// Test with offset=0
echo "1. Testing with offset=0:\n";
try {
    $result0 = $repo->getPendingRides(array_merge($params, ['offset' => 0]));
    echo "   Results: {$result0->count()} trips\n";
    if ($result0->count() > 0) {
        foreach ($result0 as $trip) {
            echo "     - Trip {$trip->ref_id}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ERROR: {$e->getMessage()}\n";
}
echo "\n";

// Test with offset=1
echo "2. Testing with offset=1:\n";
try {
    $result1 = $repo->getPendingRides(array_merge($params, ['offset' => 1]));
    echo "   Results: {$result1->count()} trips\n";
    if ($result1->count() > 0) {
        foreach ($result1 as $trip) {
            echo "     - Trip {$trip->ref_id}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ERROR: {$e->getMessage()}\n";
}
echo "\n";

// Test with offset=2
echo "3. Testing with offset=2:\n";
try {
    $result2 = $repo->getPendingRides(array_merge($params, ['offset' => 2]));
    echo "   Results: {$result2->count()} trips\n";
    if ($result2->count() > 0) {
        foreach ($result2 as $trip) {
            echo "     - Trip {$trip->ref_id}\n";
        }
    }
} catch (\Exception $e) {
    echo "   ERROR: {$e->getMessage()}\n";
}
echo "\n";

echo "=== EXPLANATION ===\n";
echo "In Laravel's paginate() method:\n";
echo "- offset/page is 1-indexed (starts at 1)\n";
echo "- offset=0 is treated as page 1 (first page)\n";
echo "- offset=1 is page 1 (first 10 items)\n";
echo "- offset=2 is page 2 (items 11-20)\n\n";

echo "So YES, offset=1 is correct for getting the first page!\n";
