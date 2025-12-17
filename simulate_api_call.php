<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== SIMULATING FULL API CALL ===\n\n";

// Step 1: Get the driver
$driverPhone = '+201208673028';
$driver = \Modules\UserManagement\Entities\User::where('phone', $driverPhone)->first();

if (!$driver) {
    echo "✗ Driver not found with phone: {$driverPhone}\n";
    exit;
}

echo "Step 1: Driver Found\n";
echo "  Name: {$driver->first_name} {$driver->last_name}\n";
echo "  ID: {$driver->id}\n\n";

// Step 2: Check if driver has a token
echo "Step 2: Checking Authentication Token\n";
$token = \DB::table('oauth_access_tokens')
    ->where('user_id', $driver->id)
    ->where('revoked', 0)
    ->where('expires_at', '>', now())
    ->orderBy('created_at', 'desc')
    ->first();

if ($token) {
    echo "  ✓ Valid token found (expires: {$token->expires_at})\n\n";
} else {
    echo "  ✗ No valid token found - Driver needs to login first!\n\n";
}

// Step 3: Simulate the controller call
echo "Step 3: Simulating Controller Call\n";
echo "  Endpoint: GET /api/driver/ride/pending-ride-list?limit=10&offset=1\n\n";

try {
    // Create a request
    $request = new \Illuminate\Http\Request();
    $request->merge([
        'limit' => 10,
        'offset' => 1,
    ]);
    $request->headers->set('zoneId', '778d28d6-1193-436d-9d2b-6c2c31185c8a');

    // Authenticate the driver
    \Auth::guard('api')->setUser($driver);

    echo "  Authenticated as: {$driver->first_name} {$driver->last_name}\n\n";

    // Call the controller
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

    echo "Step 4: Calling pendingRideList method...\n\n";

    $response = $controller->pendingRideList($request);

    echo "=== API RESPONSE ===\n";
    echo "Status Code: {$response->getStatusCode()}\n\n";

    $content = json_decode($response->getContent(), true);

    if (isset($content['content'])) {
        $trips = $content['content'];
        echo "Trips Returned: " . (is_array($trips) ? count($trips) : 'N/A') . "\n\n";

        if (is_array($trips) && count($trips) > 0) {
            echo "✓✓✓ SUCCESS! Trips found:\n\n";
            foreach ($trips as $trip) {
                if (is_array($trip)) {
                    echo "Trip: " . ($trip['ref_id'] ?? 'N/A') . "\n";
                    echo "  ID: " . ($trip['id'] ?? 'N/A') . "\n";
                    echo "  Type: " . ($trip['type'] ?? 'N/A') . "\n";
                    echo "  Status: " . ($trip['current_status'] ?? 'N/A') . "\n\n";
                }
            }
        } else {
            echo "✗ Empty trips array returned\n\n";
            echo "Full Response:\n";
            echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo "Unexpected response format:\n";
        echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (\Exception $e) {
    echo "\n✗ ERROR OCCURRED:\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";

    if (str_contains($e->getMessage(), 'SRID') || str_contains($e->getMessage(), 'ST_Distance')) {
        echo ">>> This is a SPATIAL/SRID error in the query execution!\n";
    } elseif (str_contains($e->getMessage(), 'Unauthenticated')) {
        echo ">>> This is an AUTHENTICATION error!\n";
    } elseif (str_contains($e->getMessage(), 'count()')) {
        echo ">>> This is the array/string type error in whereIn!\n";
    }

    echo "\nStack Trace:\n";
    echo $e->getTraceAsString();
}

echo "\n\n=== DEBUGGING INFO ===\n";
echo "Driver Details:\n";
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();
if ($driverDetail) {
    echo "  Online: " . ($driverDetail->is_online ? 'YES' : 'NO') . "\n";
    echo "  Available: " . ($driverDetail->availability_status == 'available' ? 'YES' : 'NO') . "\n";
}

$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();
if ($location) {
    echo "  Zone: {$location->zone_id}\n";
    echo "  Location: {$location->latitude}, {$location->longitude}\n";
}

if ($driver->vehicle) {
    echo "  Vehicle: " . ($driver->vehicle->id ?? 'N/A') . "\n";
    echo "  Categories: {$driver->vehicle->category_id}\n";
}
