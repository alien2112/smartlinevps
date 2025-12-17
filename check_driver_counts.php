<?php

require __DIR__ . '/rateel/vendor/autoload.php';
$app = require_once __DIR__ . '/rateel/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$driver = \Modules\UserManagement\Entities\User::where('phone', '+201208673028')->first();
$driverDetail = \Modules\UserManagement\Entities\DriverDetail::where('user_id', $driver->id)->first();

echo "Driver: {$driver->first_name} {$driver->last_name}\n\n";
echo "ride_count: {$driverDetail->ride_count}\n";
echo "parcel_count: {$driverDetail->parcel_count}\n";
echo "parcel_follow_status: " . ($driverDetail->parcel_follow_status ? 'true' : 'false') . "\n\n";

echo "=== LOGIC ANALYSIS ===\n";
echo "If ride_count ({$driverDetail->ride_count}) < 1: " . ($driverDetail->ride_count < 1 ? 'YES - will show ride_requests' : 'NO - will NOT show ride_requests') . "\n";
echo "If parcel_follow_status is false: " . (!$driverDetail->parcel_follow_status ? 'YES - will only show parcels' : 'NO') . "\n\n";

if ($driverDetail->ride_count >= 1 && !$driverDetail->parcel_follow_status) {
    echo "✗ PROBLEM FOUND!\n";
    echo "The driver has ride_count >= 1 AND parcel_follow_status = false\n";
    echo "This causes the query to ONLY look for PARCEL types, excluding RIDE_REQUEST!\n\n";
    echo "The pending trip is type 'ride_request', so it won't appear!\n";
}
