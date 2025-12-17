<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "=== STEP 1: LOGIN DRIVER ===\n\n";

$phone = '+201208673028';
$password = 'password123';

// Create login request
$loginRequest = \Illuminate\Http\Request::create(
    '/api/customer/auth/login',
    'POST',
    [
        'phone' => $phone,
        'password' => $password,
    ]
);

try {
    // Call the login controller
    $authController = new \Modules\AuthManagement\Http\Controllers\Api\New\AuthController(
        app(\Modules\UserManagement\Interfaces\UserInterface::class),
        app(\Modules\UserManagement\Interfaces\UserLevelInterface::class),
        app(\Modules\UserManagement\Interfaces\UserAccountInterface::class),
        app(\Modules\UserManagement\Interfaces\UserLastLocationInterface::class),
    );

    $loginResponse = $authController->login($loginRequest);
    $loginData = json_decode($loginResponse->getContent(), true);

    if ($loginResponse->getStatusCode() == 200 && isset($loginData['data']['token'])) {
        $token = $loginData['data']['token'];
        echo "✓ Login successful!\n";
        echo "Token: " . substr($token, 0, 50) . "...\n\n";

        $driver = \Modules\UserManagement\Entities\User::where('phone', $phone)->first();
        $location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();

        echo "=== STEP 2: CALL PENDING LIST API ===\n\n";
        echo "Endpoint: GET /api/driver/ride/pending-ride-list?limit=10&offset=1\n";
        echo "Headers:\n";
        echo "  Authorization: Bearer {$token}\n";
        echo "  zoneId: {$location->zone_id}\n\n";

        // Create the API request
        $request = \Illuminate\Http\Request::create(
            '/api/driver/ride/pending-ride-list',
            'GET',
            ['limit' => 10, 'offset' => 1]
        );

        $request->headers->set('Authorization', 'Bearer ' . $token);
        $request->headers->set('zoneId', $location->zone_id);
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('Accept', 'application/json');

        // Set authenticated user
        $request->setUserResolver(function () use ($driver) {
            return $driver;
        });

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

        echo "Calling API...\n\n";

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

        echo "=== API RESPONSE ===\n\n";
        echo "Status: {$response->getStatusCode()}\n\n";

        $content = json_decode($response->getContent(), true);
        echo json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo "\n\n";

        if (isset($content['data']) && is_array($content['data'])) {
            $count = count($content['data']);
            echo str_repeat("=", 60) . "\n";
            if ($count > 0) {
                echo "✓✓✓ SUCCESS! Found {$count} pending trip(s)!\n\n";
                foreach ($content['data'] as $trip) {
                    echo "Trip #{$trip['ref_id']}: {$trip['customer']['first_name']} - {$trip['type']}\n";
                }
            } else {
                echo "✗ No trips returned (but API call succeeded)\n";
            }
        }

    } else {
        echo "✗ Login failed!\n";
        echo "Response: " . json_encode($loginData, JSON_PRETTY_PRINT) . "\n";
    }

} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";
    echo $e->getTraceAsString();
}
