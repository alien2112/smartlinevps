<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== FIXING VEHICLE CATEGORY ===\n\n";

$vehicle = \Modules\VehicleManagement\Entities\Vehicle::where('user_id', '000302ca-4065-463a-9e3f-4e281eba7fb0')->first();

echo "Current categories: {$vehicle->category_id}\n";

$newCategories = [
    '5d48c2f7-194a-41e4-bd1c-b1188beb885b',
    '25bc1ba6-50af-4074-9206-60d6254407ea'
];

$vehicle->category_id = json_encode($newCategories);
$vehicle->save();

echo "Updated categories: {$vehicle->category_id}\n\n";
echo "✓ Vehicle now supports BOTH categories!\n\n";
echo "Now test the API again - it should return the trip!\n";
