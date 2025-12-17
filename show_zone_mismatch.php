<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== ZONE MISMATCH DETAILS ===\n\n";

$trip = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')
    ->orderBy('created_at', 'desc')
    ->first();

$driver = \Modules\UserManagement\Entities\DriverDetail::whereNotNull('user_id')
    ->whereIn('user_id', \Modules\UserManagement\Entities\User::pluck('id'))
    ->where('is_online', 1)
    ->where('availability_status', 'available')
    ->first();

if (!$trip || !$driver) {
    echo "Missing trip or driver\n";
    exit;
}

$location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->user_id)->first();

echo "CUSTOMER'S TRIP:\n";
echo "  Zone ID: {$trip->zone_id}\n";

// Get zone name
$tripZone = \Modules\ZoneManagement\Entities\Zone::find($trip->zone_id);
if ($tripZone) {
    echo "  Zone Name: {$tripZone->name}\n";
}

echo "\nDRIVER'S LOCATION:\n";
echo "  Zone ID: {$location->zone_id}\n";

$driverZone = \Modules\ZoneManagement\Entities\Zone::find($location->zone_id);
if ($driverZone) {
    echo "  Zone Name: {$driverZone->name}\n";
}

echo "\n";
if ($trip->zone_id === $location->zone_id) {
    echo "✓ ZONES MATCH!\n";
} else {
    echo "✗ ZONES DO NOT MATCH!\n";
    echo "\n=== SOLUTION ===\n";
    echo "You need to either:\n";
    echo "1. Update driver's location zone to: {$trip->zone_id}\n";
    echo "2. OR create a new trip request from a customer in zone: {$location->zone_id}\n";
    echo "\nTo update driver's location zone, run:\n";
    echo "UPDATE user_last_locations SET zone_id = '{$trip->zone_id}' WHERE user_id = '{$driver->user_id}';\n";
}
