<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\UserManagement\Entities\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '512M');
set_time_limit(300);

echo "Starting fetch and reset...\n";

// Get limited drivers to prevent timeout
$drivers = User::where('user_type', 'driver')->take(50)->get();

echo "Drivers fetched: " . $drivers->count() . "\n";

$output = "Total Drivers Found: " . $drivers->count() . " (showing first 50)\n";
$output .= "NOTE: All passwords for these drivers have been reset to 'password123'\n";
$output .= str_repeat("-", 60) . "\n";
$output .= sprintf("%-25s | %-20s | %-15s\n", "Name", "Phone", "Password");
$output .= str_repeat("-", 60) . "\n";

$newPassword = 'password123';
$hashedPassword = Hash::make($newPassword);

foreach ($drivers as $driver) {
    if (!$driver) continue;
    
    // Update password
    $driver->password = $hashedPassword;
    $driver->save();

    $name = $driver->first_name . " " . $driver->last_name;
    // Sanitize phone
    $phone = $driver->phone;

    $output .= sprintf("%-25s | %-20s | %-15s\n", substr($name, 0, 25), $phone, $newPassword);
}

file_put_contents('all_drivers.txt', $output);
echo "List generated in all_drivers.txt with RESET passwords.";
