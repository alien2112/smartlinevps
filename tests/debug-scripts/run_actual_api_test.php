<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== RUNNING ACTUAL API TEST ===\n\n";

$driverPhone = '+201208673028';
$driver = \Modules\UserManagement\Entities\User::where('phone', $driverPhone)->first();

echo "Driver: {$driver->first_name} {$driver->last_name}\n";
echo "Phone: {$driver->phone}\n\n";

// Get the auth token
$token = \DB::table('oauth_access_tokens')
    ->where('user_id', $driver->id)
    ->where('revoked', 0)
    ->where('expires_at', '>', now())
    ->orderBy('created_at', 'desc')
    ->first();

$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();

echo "Creating API Request...\n";
echo "  Endpoint: GET /api/driver/ride/pending-ride-list?limit=10&offset=1\n";
echo "  Headers:\n";
echo "    Authorization: Bearer {$token->id}\n";
echo "    zoneId: {$location->zone_id}\n\n";

// Create the request exactly as it would come from the API
$request = \Illuminate\Http\Request::create(
    '/api/driver/ride/pending-ride-list',
    'GET',
    [
        'limit' => 10,
        'offset' => 1,
    ]
);

// Set headers
$request->headers->set('Authorization', 'Bearer ' . $token->id);
$request->headers->set('zoneId', $location->zone_id);
$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');

// Set the authenticated user
$request->setUserResolver(function () use ($driver) {
    return $driver;
});

// Mock the auth guard
app()->singleton('auth', function () use ($driver) {
    return new class($driver) {
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
    };
});

echo "Calling Controller...\n\n";

try {
    // Instantiate the controller with all dependencies
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

    // Call the method
    $response = $controller->pendingRideList($request);

    echo "=== API RESPONSE ===\n\n";
    echo "Status Code: {$response->getStatusCode()}\n\n";

    $content = json_decode($response->getContent(), true);

    // Pretty print the response
    echo "Response:\n";
    echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "\n\n";

    // Summary
    if (isset($content['data']) && is_array($content['data'])) {
        $count = count($content['data']);
        echo str_repeat("=", 60) . "\n";
        if ($count > 0) {
            echo "✓✓✓ SUCCESS! Found {$count} pending trip(s)!\n\n";
            foreach ($content['data'] as $trip) {
                echo "Trip Ref: {$trip['ref_id']}\n";
                echo "  Customer: {$trip['customer']['first_name']} {$trip['customer']['last_name']}\n";
                echo "  Phone: {$trip['customer']['phone']}\n";
                echo "  Type: {$trip['type']}\n";
                echo "  Status: {$trip['current_status']}\n";
                echo "  Estimated Fare: {$trip['estimated_fare']}\n";
                echo "  Pickup: {$trip['pickup_address']}\n";
                echo "  Destination: {$trip['destination_address']}\n\n";
            }
        } else {
            echo "✗ EMPTY - No trips returned\n\n";
            echo "This shouldn't happen based on all our tests!\n";
            echo "Check the response_code: {$content['response_code']}\n";
            echo "Message: {$content['message']}\n";
        }
    }

} catch (\Exception $e) {
    echo "✗ ERROR OCCURRED:\n\n";
    echo "Message: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";
    echo "Stack Trace:\n";
    echo $e->getTraceAsString();
}
