<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Modules\UserManagement\Entities\DriverDetail;
use Modules\UserManagement\Entities\UserAccount;
use Modules\UserManagement\Entities\UserLevel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$phone = $argv[1] ?? null;
$email = $argv[2] ?? null;

if (!$phone || !$email) {
    echo json_encode(['error' => 'Phone and email are required']);
    exit(1);
}

// Check if driver already exists
$existing = User::where('phone', $phone)->where('user_type', 'driver')->first();
if ($existing) {
    echo json_encode(['error' => 'Driver already exists', 'id' => $existing->id]);
    exit(1);
}

DB::beginTransaction();
try {
    // Get first driver level
    $driverLevel = UserLevel::where('user_type', 'driver')->first();
    
    // Generate referral code
    $refCode = 'TEST-' . strtoupper(Str::random(8));
    
    // Create test driver
    $driver = User::create([
        'id' => Str::uuid(),
        'user_level_id' => $driverLevel?->id,
        'first_name' => 'Test',
        'last_name' => 'Driver',
        'full_name' => 'Test Driver',
        'phone' => $phone,
        'email' => $email,
        'password' => Hash::make('Test123456!'),
        'user_type' => 'driver',
        'is_active' => true,
        'ref_code' => $refCode,
        'onboarding_step' => 'approved',
        'phone_verified_at' => now(),
        'email_verified_at' => now(),
    ]);
    
    // Update onboarding_state if column exists
    if (Schema::hasColumn('users', 'onboarding_state')) {
        $driver->update(['onboarding_state' => 'approved']);
    }
    
    // Update is_approved if column exists
    if (Schema::hasColumn('users', 'is_approved')) {
        $driver->update(['is_approved' => true]);
    }
    
    // Create driver details
    $driver->driverDetails()->create([
        'user_id' => $driver->id,
        'is_online' => false,
        'availability_status' => 'unavailable',
    ]);
    
    // Create user account
    $driver->userAccount()->create([
        'id' => Str::uuid(),
        'user_id' => $driver->id,
        'payable_balance' => 0,
        'receivable_balance' => 0,
        'received_balance' => 0,
        'pending_balance' => 0,
        'wallet_balance' => 0,
        'total_withdrawn' => 0,
    ]);
    
    DB::commit();
    echo json_encode(['success' => true, 'id' => $driver->id, 'ref_code' => $driver->ref_code, 'phone' => $driver->phone]);
} catch (\Exception $e) {
    DB::rollBack();
    echo json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit(1);
}
