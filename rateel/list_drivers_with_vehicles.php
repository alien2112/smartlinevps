<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\UserManagement\Entities\User;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

ini_set('memory_limit', '512M');
set_time_limit(300);

echo "Starting fetch of drivers WITH VEHICLES...\n";

// Get drivers that explicitly have a vehicle
$drivers = User::where('user_type', 'driver')
    ->has('vehicle')
    ->with('vehicle') // Eager load vehicle
    ->take(50)
    ->get();

echo "Drivers with vehicles fetched: " . $drivers->count() . "\n";

$output = "Drivers WITH VEHICLES: " . $drivers->count() . " (showing first 50)\n";
$output .= "NOTE: All passwords for these drivers have been reset to 'password123'\n";
$output .= str_repeat("-", 100) . "\n";
$output .= sprintf("%-25s | %-16s | %-12s | %-20s | %-20s\n", "Name", "Phone", "Password", "Vehicle Category", "License Plate");
$output .= str_repeat("-", 100) . "\n";

$newPassword = 'password123';
$hashedPassword = Hash::make($newPassword);

foreach ($drivers as $driver) {
    if (!$driver) continue;
    
    // Update password
    $driver->password = $hashedPassword;
    $driver->save();

    $name = $driver->first_name . " " . $driver->last_name;
    $phone = $driver->phone;
    
    // Vehicle Info
    $vehicle = $driver->vehicle;
    $categoryName = "Unknown";
    $licensePlate = "N/A";

    if ($vehicle) {
        $licensePlate = $vehicle->licence_plate_number ?? "N/A";
        
        $rawId = $vehicle->category_id;
        $ids = json_decode($rawId, true);
        if (!is_array($ids)) {
            $ids = [$rawId];
        }
        
        // Just get the first category name for brevity
        $cat = VehicleCategory::whereIn('id', $ids)->first();
        if ($cat) {
            $categoryName = $cat->name;
        }
    }

    $output .= sprintf("%-25s | %-16s | %-12s | %-20s | %-20s\n", 
        substr($name, 0, 25), 
        $phone, 
        $newPassword,
        substr($categoryName, 0, 20),
        $licensePlate
    );
}

file_put_contents('drivers_with_vehicles.txt', $output);
echo "List generated in drivers_with_vehicles.txt";
