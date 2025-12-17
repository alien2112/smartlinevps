<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Database Tables ===\n\n";

// Check what table User model uses
$userModel = new \Modules\UserManagement\Entities\User();
echo "User Model Table: " . $userModel->getTable() . "\n";

// Check if there are any users
$userCount = \Modules\UserManagement\Entities\User::count();
echo "Total users: {$userCount}\n";

// Check driver details
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('is_online', 1)
    ->where('availability_status', 'available')
    ->first();

if ($driverDetail) {
    echo "\nDriver Detail found:\n";
    echo "  User ID: {$driverDetail->user_id}\n";
    echo "  ID: {$driverDetail->id}\n";

    // Try to find user by different methods
    echo "\nTrying to find user...\n";

    // Method 1: Direct find
    $user1 = \Modules\UserManagement\Entities\User::find($driverDetail->user_id);
    echo "  Find by ID: " . ($user1 ? "Found (ID: {$user1->id})" : "Not found") . "\n";

    // Method 2: Where id
    $user2 = \Modules\UserManagement\Entities\User::where('id', $driverDetail->user_id)->first();
    echo "  Where id: " . ($user2 ? "Found (ID: {$user2->id})" : "Not found") . "\n";

    // Check if there's a relationship
    if (method_exists($driverDetail, 'user')) {
        $user3 = $driverDetail->user;
        echo "  Via relationship: " . ($user3 ? "Found (ID: {$user3->id})" : "Not found") . "\n";

        if ($user3) {
            echo "\nUser Details:\n";
            echo "  Name: {$user3->first_name} {$user3->last_name}\n";
            echo "  Email: {$user3->email}\n";

            if ($user3->vehicle) {
                echo "\nVehicle Details:\n";
                echo "  ID: {$user3->vehicle->id}\n";
                echo "  Category IDs: {$user3->vehicle->category_id}\n";
            } else {
                echo "\nNo vehicle found for user\n";
            }
        }
    }
}

// Check all users with driver role
echo "\n=== All Users with Driver Role ===\n";
$drivers = \Modules\UserManagement\Entities\User::whereHas('userLevel', function($q) {
    $q->where('name', 'driver');
})->orWhere('user_level_id', function($q) {
    $q->from('user_levels')->where('name', 'driver');
})->take(5)->get();

foreach ($drivers as $driver) {
    echo "  User ID: {$driver->id}, Name: {$driver->first_name} {$driver->last_name}\n";
}
