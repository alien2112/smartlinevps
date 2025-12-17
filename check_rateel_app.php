<?php

echo "=== CHECKING RATEEL APP ===\n\n";

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "Loaded rateel app from: " . base_path() . "\n\n";

// Check if route exists
$routes = \Route::getRoutes();
$found = false;

echo "Looking for pending-ride-list route...\n\n";

foreach ($routes->getRoutes() as $route) {
    if (strpos($route->uri(), 'pending-ride-list') !== false) {
        $found = true;
        echo "✓ Route FOUND:\n";
        echo "  URI: " . $route->uri() . "\n";
        echo "  Method: " . implode('|', $route->methods()) . "\n";
        echo "  Action: " . $route->getActionName() . "\n";
        echo "  Middleware: " . implode(', ', $route->middleware()) . "\n\n";
    }
}

if (!$found) {
    echo "✗ Route still NOT FOUND!\n";
} else {
    echo "=== TESTING WITH RATEEL APP ===\n\n";

    $driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();

    if ($driver) {
        echo "Driver found: {$driver->first_name} {$driver->last_name}\n";
        echo "Driver ID: {$driver->id}\n\n";

        // Check which repository is being used
        $repo = app(\Modules\TripManagement\Interfaces\TripRequestInterfaces::class);
        echo "Repository class: " . get_class($repo) . "\n\n";

        // Check the TripRequestCoordinate model
        $coordModel = new \Modules\TripManagement\Entities\TripRequestCoordinate();
        echo "Coordinate model table: " . $coordModel->getTable() . "\n\n";

        // Test the distanceSphere scope
        echo "Testing distanceSphere scope...\n";

        $location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->id)->first();

        if ($location) {
            echo "Driver location: {$location->latitude}, {$location->longitude}\n";
            echo "Driver zone: {$location->zone_id}\n\n";

            try {
                $result = \Modules\TripManagement\Entities\TripRequestCoordinate::query()
                    ->distanceSphere('pickup_coordinates', $location, 5000)
                    ->count();

                echo "✓ distanceSphere works! Found {$result} coordinates within 5km\n";
            } catch (\Exception $e) {
                echo "✗ distanceSphere ERROR: {$e->getMessage()}\n";
            }
        }
    }
}
