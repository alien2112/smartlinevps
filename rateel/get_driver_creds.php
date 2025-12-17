<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\Hash;

$excludePhone = '+201208673028';
$password = 'password123';
$hashed = Hash::make($password);

// Find a driver that corresponds to a real user (not the one we just used)
$driver = User::where('user_type', 'driver')
              ->where('phone', '!=', $excludePhone)
              ->first();

if ($driver) {
    // Reset password to ensure we know it
    $driver->password = $hashed;
    $driver->save();

    $output = "Phone: " . $driver->phone . "\n" .
              "Password: " . $password . "\n" .
              "Name: " . $driver->first_name . " " . $driver->last_name . "\n";
    
    file_put_contents('creds_new.txt', $output);
    echo "Credentials written to creds_new.txt";
} else {
    file_put_contents('creds_new.txt', "No other driver found");
    echo "No other driver found";
}
