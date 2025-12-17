<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== FIXING VEHICLE CATEGORY ===\n\n";

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();
$vehicle = $driver->vehicle;

if (!$vehicle) {
    echo "✗ No vehicle found!\n";
    exit;
}

echo "Vehicle ID: {$vehicle->id}\n";
echo "Current categories: {$vehicle->category_id}\n\n";

$newCategories = [
    '5d48c2f7-194a-41e4-bd1c-b1188beb885b',
    '25bc1ba6-50af-4074-9206-60d6254407ea'
];

$vehicle->category_id = json_encode($newCategories);
$vehicle->save();

echo "Updated categories: {$vehicle->category_id}\n\n";
echo "✓ Vehicle now supports BOTH categories!\n\n";
echo "Now test your Postman API call again.\n";
echo "The trip should appear now!\n";
