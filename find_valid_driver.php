<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Finding Valid Driver ===\n\n";

// Get all driver details
$allDriverDetails = \Modules\UserManagement\Entities\DriverDetail::all();
echo "Total driver details records: " . $allDriverDetails->count() . "\n";

// Find driver details with existing users
$validDrivers = [];
foreach ($allDriverDetails as $dd) {
    $user = \Modules\UserManagement\Entities\User::find($dd->user_id);
    if ($user) {
        $validDrivers[] = [
            'driver_detail_id' => $dd->id,
            'user_id' => $user->id,
            'name' => $user->first_name . ' ' . $user->last_name,
            'is_online' => $dd->is_online,
            'availability_status' => $dd->availability_status,
        ];
    }
}

echo "Valid drivers (with user records): " . count($validDrivers) . "\n\n";

if (count($validDrivers) > 0) {
    echo "First 5 valid drivers:\n";
    foreach (array_slice($validDrivers, 0, 5) as $vd) {
        echo "  - {$vd['name']} (User ID: {$vd['user_id']}, Online: {$vd['is_online']}, Status: {$vd['availability_status']})\n";
    }

    // Find an online available driver with valid user
    $onlineDriver = null;
    foreach ($validDrivers as $vd) {
        if ($vd['is_online'] == 1 && $vd['availability_status'] == 'available') {
            $onlineDriver = $vd;
            break;
        }
    }

    if ($onlineDriver) {
        echo "\nFound online available driver: {$onlineDriver['name']}\n";
        echo "User ID: {$onlineDriver['user_id']}\n";

        $user = \Modules\UserManagement\Entities\User::find($onlineDriver['user_id']);
        if ($user->vehicle) {
            echo "Has vehicle: Yes\n";
            echo "Vehicle category_id: {$user->vehicle->category_id}\n";
        } else {
            echo "Has vehicle: No\n";
        }

        $location = \Modules\UserManagement\Entities\UserLastLocation::where('user_id', $onlineDriver['user_id'])->first();
        if ($location) {
            echo "Has location: Yes (Zone: {$location->zone_id})\n";
        } else {
            echo "Has location: No\n";
        }
    } else {
        echo "\nNo online available drivers found\n";
    }
} else {
    echo "ERROR: No valid drivers found in database!\n";
}

echo "\n=== Orphaned Driver Details ===\n";
$orphanedCount = \Modules\UserManagement\Entities\DriverDetail::whereNotIn('user_id',
    \Modules\UserManagement\Entities\User::pluck('id')
)->count();
echo "Orphaned driver_details records (no matching user): {$orphanedCount}\n";
