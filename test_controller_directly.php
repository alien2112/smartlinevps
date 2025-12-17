<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== TESTING CONTROLLER METHOD DIRECTLY ===\n\n";

$driverPhone = '+201208673028';
$driver = \Modules\UserManagement\Entities\User::where('phone', $driverPhone)->first();

echo "Driver: {$driver->first_name} {$driver->last_name}\n";
echo "ID: {$driver->id}\n\n";

// Create request
$request = \Illuminate\Http\Request::create(
    '/api/driver/ride/pending-ride-list?limit=10&offset=1',
    'GET',
    ['limit' => 10, 'offset' => 1]
);

// Set zoneId header
$request->headers->set('zoneId', '778d28d6-1193-436d-9d2b-6c2c31185c8a');

// Mock the auth user
app()->instance('auth', new class($driver) {
    private $driver;
    public function __construct($driver) {
        $this->driver = $driver;
    }
    public function guard($guard = null) {
        return $this;
    }
    public function user() {
        return $this->driver;
    }
    public function id() {
        return $this->driver->id;
    }
});

echo "Step 1: Calling pendingRideList...\n\n";

try {
    $controller = new \Modules\TripManagement\Http\Controllers\Api\Driver\TripRequestController(
        app(\Modules\TripManagement\Interfaces\TripRequestInterfaces::class),
        app(\Modules\TripManagement\Interfaces\FareBiddingInterface::class),
        app(\Modules\TripManagement\Interfaces\FareBiddingLogInterface::class),
        app(\Modules\UserManagement\Interfaces\UserLastLocationInterface::class),
        app(\Modules\UserManagement\Interfaces\DriverDetailsInterface::class),
        app(\Modules\TripManagement\Interfaces\RejectedDriverRequestInterface::class),
        app(\Modules\TripManagement\Interfaces\TempTripNotificationInterface::class),
        app(\Modules\ReviewModule\Interfaces\ReviewInterface::class),
        app(\Modules\TripManagement\Interfaces\TripRequestTimeInterface::class),
    );

    $response = $controller->pendingRideList($request);

    echo "=== RESPONSE ===\n";
    echo "Status: {$response->getStatusCode()}\n\n";

    $data = json_decode($response->getContent(), true);

    echo "Response Data:\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

    if (isset($data['content'])) {
        $count = is_array($data['content']) ? count($data['content']) : 0;
        echo "Trips Count: {$count}\n";

        if ($count > 0) {
            echo "\n✓✓✓ SUCCESS! Trips found!\n";
        } else {
            echo "\n✗ EMPTY - No trips returned\n";
            echo "\nChecking why...\n\n";

            // Let's check if the driver's details are correct
            $driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
            $location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();

            echo "Driver online: " . ($driverDetail->is_online ? 'YES' : 'NO') . "\n";
            echo "Driver available: " . ($driverDetail->availability_status) . "\n";
            echo "Zone ID from header: " . $request->header('zoneId') . "\n";
            echo "Driver zone: " . ($location ? $location->zone_id : 'NULL') . "\n";
            echo "Vehicle: " . ($driver->vehicle ? 'YES' : 'NO') . "\n";

            if (!$location) {
                echo "\n>>> PROBLEM: No location found for driver!\n";
            }

            if (!$driver->vehicle) {
                echo "\n>>> PROBLEM: No vehicle found for driver!\n";
            }
        }
    }

} catch (\Exception $e) {
    echo "\n✗ ERROR:\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";

    // Provide specific guidance based on error
    if (str_contains($e->getMessage(), 'SRID')) {
        echo ">>> SRID/Spatial error - Check the distanceSphere scope\n";
    } elseif (str_contains($e->getMessage(), 'count()')) {
        echo ">>> Array type error - Check whereIn usage\n";
    } elseif (str_contains($e->getMessage(), 'Unauthenticated')) {
        echo ">>> Auth error\n";
    } elseif (str_contains($e->getMessage(), 'zoneId')) {
        echo ">>> Zone header missing\n";
    }

    echo "\nStack Trace:\n";
    echo $e->getTraceAsString();
}
