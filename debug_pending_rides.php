<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Checking Latest Trip Request ===\n";
$trip = \Modules\TripManagement\Entities\TripRequest::orderBy('created_at', 'desc')->first();

if ($trip) {
    echo "Trip ID: {$trip->id}\n";
    echo "Ref ID: {$trip->ref_id}\n";
    echo "Status: {$trip->current_status}\n";
    echo "Zone ID: {$trip->zone_id}\n";
    echo "Vehicle Category ID: {$trip->vehicle_category_id}\n";
    echo "Type: {$trip->type}\n";
    echo "Created: {$trip->created_at}\n";

    if ($trip->coordinate) {
        echo "Pickup Coordinates: ";
        if ($trip->coordinate->pickup_coordinates) {
            echo "Lat: {$trip->coordinate->pickup_coordinates->latitude}, ";
            echo "Lng: {$trip->coordinate->pickup_coordinates->longitude}\n";
        } else {
            echo "NULL\n";
        }
    } else {
        echo "No coordinate record\n";
    }
} else {
    echo "No trips found in database\n";
}

echo "\n=== Checking Pending Trips ===\n";
$pendingTrips = \Modules\TripManagement\Entities\TripRequest::where('current_status', 'pending')->get();
echo "Total pending trips: " . $pendingTrips->count() . "\n";

if ($pendingTrips->count() > 0) {
    foreach ($pendingTrips as $pending) {
        echo "  - Trip {$pending->ref_id}: Zone {$pending->zone_id}, Vehicle Cat: {$pending->vehicle_category_id}\n";
    }
}

echo "\n=== Checking Driver Info (if logged in) ===\n";
// You'll need to provide driver ID or test with a specific driver
// For now, let's check the first online driver
$driver = \Modules\UserManagement\Entities\DriverDetail::where('is_online', 1)->first();
if ($driver) {
    echo "Online Driver User ID: {$driver->user_id}\n";
    echo "Availability Status: {$driver->availability_status}\n";

    $user = \App\Models\User::find($driver->user_id);
    if ($user && $user->vehicle) {
        echo "Vehicle Category IDs: {$user->vehicle->category_id}\n";
    }

    $location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $driver->user_id)->first();
    if ($location) {
        echo "Driver Location: Lat {$location->latitude}, Lng {$location->longitude}\n";
        echo "Driver Zone: {$location->zone_id}\n";
    }
} else {
    echo "No online drivers found\n";
}
