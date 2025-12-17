<?php

echo "=== CHECKING WHICH APP IS RUNNING ===\n\n";

// Check root folder
if (file_exists(__DIR__ . '/bootstrap/app.php')) {
    echo "Root app exists: YES\n";
    echo "Root path: " . __DIR__ . "\n\n";
}

// Check rateel folder
if (file_exists(__DIR__ . '/rateel/bootstrap/app.php')) {
    echo "Rateel app exists: YES\n";
    echo "Rateel path: " . __DIR__ . "/rateel\n\n";
}

// Try to determine which one is being served
if (file_exists(__DIR__ . '/public/.htaccess')) {
    echo "Root public/.htaccess exists\n";
    $htaccess = file_get_contents(__DIR__ . '/public/.htaccess');
    if (strpos($htaccess, 'rateel') !== false) {
        echo "  -> Redirects to rateel folder\n";
    }
}

if (file_exists(__DIR__ . '/.env')) {
    echo "\nRoot .env file exists\n";
    $env = file_get_contents(__DIR__ . '/.env');
    if (preg_match('/APP_NAME=(.+)/', $env, $matches)) {
        echo "  APP_NAME: " . trim($matches[1]) . "\n";
    }
}

if (file_exists(__DIR__ . '/rateel/.env')) {
    echo "\nRateel .env file exists\n";
    $env = file_get_contents(__DIR__ . '/rateel/.env');
    if (preg_match('/APP_NAME=(.+)/', $env, $matches)) {
        echo "  APP_NAME: " . trim($matches[1]) . "\n";
    }
}

echo "\n=== CHECKING ACTUAL API ENDPOINT ===\n";

// Load the actual app being served
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "Loaded app from: " . base_path() . "\n";
echo "Public path: " . public_path() . "\n";

// Check if route exists
$routes = \Route::getRoutes();
$found = false;

foreach ($routes->getRoutes() as $route) {
    if (strpos($route->uri(), 'pending-ride-list') !== false) {
        $found = true;
        echo "\nRoute found:\n";
        echo "  URI: " . $route->uri() . "\n";
        echo "  Method: " . implode('|', $route->methods()) . "\n";
        echo "  Action: " . $route->getActionName() . "\n";
        echo "  Middleware: " . implode(', ', $route->middleware()) . "\n";
    }
}

if (!$found) {
    echo "\nRoute NOT FOUND in current app!\n";
    echo "\nLet me check the rateel app...\n";
}
