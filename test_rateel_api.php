<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

\DB::enableQueryLog();

echo "=== TESTING RATEEL API ENDPOINT ===\n\n";

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();

echo "Driver: {$driver->first_name} {$driver->last_name}\n";
echo "ID: {$driver->id}\n\n";

// Create request exactly as the API would receive it
$request = \Illuminate\Http\Request::create(
    'http://localhost/api/driver/ride/pending-ride-list?limit=10&offset=1',
    'GET',
    ['limit' => '10', 'offset' => '1']
);

// Set the zoneId header
$request->headers->set('zoneId', '778d28d6-1193-436d-9d2b-6c2c31185c8a');

// Mock auth
app()->instance('auth', new class($driver) {
    private $driver;
    public function __construct($driver) { $this->driver = $driver; }
    public function guard($guard = null) { return $this; }
    public function user() { return $this->driver; }
    public function id() { return $this->driver->id; }
});

echo "Calling API endpoint...\n\n";

try {
    $tripRepo = app(\Modules\TripManagement\Interfaces\TripRequestInterfaces::class);
    echo "Using repository: " . get_class($tripRepo) . "\n\n";

    $controller = new \Modules\TripManagement\Http\Controllers\Api\Driver\TripRequestController(
        $tripRepo,
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

    if (isset($data['data']) && is_array($data['data'])) {
        echo "Trips returned: " . count($data['data']) . "\n\n";

        if (count($data['data']) > 0) {
            echo "✓✓✓ SUCCESS!\n\n";
            foreach ($data['data'] as $trip) {
                echo "Trip: " . $trip['ref_id'] . "\n";
                echo "  Customer: " . $trip['customer']['first_name'] . "\n";
                echo "  Status: " . $trip['current_status'] . "\n\n";
            }
        } else {
            echo "✗ EMPTY - No trips returned\n\n";

            echo "SQL Queries executed:\n";
            $queries = \DB::getQueryLog();
            foreach ($queries as $i => $q) {
                if (str_contains($q['query'], 'trip_request') || str_contains($q['query'], 'ST_Distance')) {
                    echo "\nQuery " . ($i + 1) . ":\n";
                    echo substr($q['query'], 0, 500) . (strlen($q['query']) > 500 ? '...' : '') . "\n";
                }
            }
        }
    } else {
        echo "Response:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

} catch (\Exception $e) {
    echo "✗ ERROR: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n\n";

    if (str_contains($e->getMessage(), 'SRID') || str_contains($e->getMessage(), 'ST_Distance')) {
        echo ">>> SPATIAL ERROR - The SRID fix might not be applied in rateel folder!\n";
    } elseif (str_contains($e->getMessage(), 'count()')) {
        echo ">>> ARRAY TYPE ERROR - The whereIn fix might not be applied!\n";
    }

    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
