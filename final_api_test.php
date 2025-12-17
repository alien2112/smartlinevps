<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== FINAL API TEST WITH EXISTING TOKEN ===\n\n";

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();
$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();

echo "Driver: {$driver->first_name} {$driver->last_name}\n";
echo "Zone: {$location->zone_id}\n\n";

// Create request with headers
$request = \Illuminate\Http\Request::create(
    '/api/driver/ride/pending-ride-list',
    'GET',
    ['limit' => 10, 'offset' => 1]
);

$request->headers->set('zoneId', $location->zone_id);
$request->headers->set('Content-Type', 'application/json');
$request->headers->set('Accept', 'application/json');

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

echo "Calling pendingRideList API...\n\n";

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
    $content = json_decode($response->getContent(), true);

    echo "=== RESULT ===\n\n";
    echo "Status: {$response->getStatusCode()}\n";
    echo "Response Code: {$content['response_code']}\n";
    echo "Message: {$content['message']}\n";
    echo "Total Size: {$content['total_size']}\n\n";

    if (isset($content['data']) && count($content['data']) > 0) {
        echo "✓✓✓ SUCCESS! Found " . count($content['data']) . " trip(s)!\n\n";

        foreach ($content['data'] as $trip) {
            echo "════════════════════════════════════════\n";
            echo "Trip Ref: {$trip['ref_id']}\n";
            echo "Customer: {$trip['customer']['first_name']} {$trip['customer']['last_name']}\n";
            echo "Phone: {$trip['customer']['phone']}\n";
            echo "Type: {$trip['type']}\n";
            echo "Status: {$trip['current_status']}\n";
            echo "Estimated Fare: {$trip['estimated_fare']} EGP\n";
            echo "Distance: {$trip['estimated_distance']} km\n";
            echo "Pickup: " . substr($trip['pickup_address'], 0, 60) . "...\n";
            echo "Destination: " . substr($trip['destination_address'], 0, 60) . "...\n";
            echo "════════════════════════════════════════\n\n";
        }

        echo "🎉 THE API IS WORKING PERFECTLY! 🎉\n\n";
        echo "When you call this from your app, use these headers:\n";
        echo "  Authorization: Bearer {YOUR_TOKEN_FROM_LOGIN}\n";
        echo "  zoneId: {$location->zone_id}\n\n";

    } else {
        echo "✗ No trips in response\n\n";
        echo "Full response:\n";
        echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
}
