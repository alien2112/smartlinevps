<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$driverId = $argv[1] ?? null;

if (!$driverId) {
    echo json_encode(['error' => 'Driver ID is required']);
    exit(1);
}

DB::beginTransaction();
try {
    $driver = User::find($driverId);
    if ($driver) {
        // Delete related records
        $driver->driverDetails()->delete();
        $driver->userAccount()->delete();
        // Force delete to bypass soft deletes for test cleanup
        $driver->forceDelete();
        DB::commit();
        echo json_encode(['success' => true, 'message' => 'Driver deleted']);
    } else {
        DB::rollBack();
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
    }
} catch (\Exception $e) {
    DB::rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
