<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\UserManagement\Entities\User;
use Modules\VehicleManagement\Entities\VehicleCategory;

$phone = '+201208673028'; 
$driver = User::where('user_type', 'driver')->where('phone', $phone)->first();

$output = "";

if ($driver) {
    $output .= "Driver Found: " . $driver->first_name . " " . $driver->last_name . "\n";
    $vehicle = $driver->vehicle;
    if ($vehicle) {
        $rawId = $vehicle->category_id;
        $output .= "Category ID (raw): " . $rawId . "\n";
        
        $ids = json_decode($rawId, true);
        
        // If decoding fails, check if it's just a string ID
        if (!is_array($ids)) {
            $ids = [$rawId];
        }

        $categories = VehicleCategory::whereIn('id', $ids)->get();
        if ($categories->count() > 0) {
             foreach ($categories as $cat) {
                 $output .= "Category Name: " . $cat->name . "\n";
                 $output .= "Category Type: " . $cat->type . "\n";
             }
        } else {
             $output .= "Categories found: 0\n";
        }
        
    } else {
        $output .= "No vehicle found for this driver.\n";
    }
} else {
    $output .= "Driver with phone $phone not found.\n";
}

file_put_contents('vehicle_info.txt', $output);
echo "Done";
