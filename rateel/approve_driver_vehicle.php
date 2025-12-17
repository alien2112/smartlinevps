<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\UserManagement\Entities\User;

// Phone number might have formatting characters, so I'll try to match it loosely or strip them
// The user provided: +201274222385 (with possible invisible characters or spaces)
// I'll search by the suffix or try to sanitize.

$searchPhone = '+201274222385';

$driver = User::where('user_type', 'driver')
    ->where(function($q) use ($searchPhone) {
        $q->where('phone', $searchPhone)
          ->orWhere('phone', 'LIKE', '%' . substr($searchPhone, -9)); // Match last 9 digits
    })
    ->first();

if ($driver) {
    echo "Found Driver: " . $driver->first_name . " " . $driver->last_name . "\n";
    echo "Phone: " . $driver->phone . "\n";
    
    $vehicle = $driver->vehicle;
    if ($vehicle) {
        echo "Found Vehicle ID: " . $vehicle->id . "\n";
        echo "Current Status (is_active): " . $vehicle->is_active . "\n";
        
        // Update to active
        $vehicle->is_active = 1;
        $vehicle->save();
        
        echo "Updated Status (is_active) to: 1\n";
        echo "Vehicle successfully approved/activated.\n";
    } else {
        echo "No vehicle found for this driver.\n";
    }
} else {
    echo "Driver not found.\n";
}
