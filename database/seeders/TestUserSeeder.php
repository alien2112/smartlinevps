<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLevel;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get the first level for customer and driver
        $customerLevel = UserLevel::where('user_type', 'customer')
            ->where('sequence', 1)
            ->first();

        $driverLevel = UserLevel::where('user_type', 'driver')
            ->where('sequence', 1)
            ->first();

        if (!$customerLevel) {
            $this->command->error('Customer level not found. Please run: php artisan db:seed --class=UserLevelSeeder');
            return;
        }

        if (!$driverLevel) {
            $this->command->error('Driver level not found. Please run: php artisan db:seed --class=UserLevelSeeder');
            return;
        }

        // Create Test Customer
        $customerPhone = '1234567890';
        $customerPassword = 'password123';
        
        $existingCustomer = User::where('phone', $customerPhone)
            ->where('user_type', 'customer')
            ->first();

        if (!$existingCustomer) {
            $customer = User::create([
                'id' => Str::uuid(),
                'user_level_id' => $customerLevel->id,
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'full_name' => 'Test Customer',
                'phone' => $customerPhone,
                'email' => 'testcustomer@example.com',
                'password' => Hash::make($customerPassword),
                'user_type' => 'customer',
                'is_active' => 1,
                'ref_code' => generateReferralCode(),
                'phone_verified_at' => now(),
            ]);

            // Create user account for customer
            $customer->userAccount()->create([
                'id' => Str::uuid(),
                'user_id' => $customer->id,
                'payable_balance' => 0,
                'receivable_balance' => 0,
                'received_balance' => 0,
                'pending_balance' => 0,
                'wallet_balance' => 0,
                'total_withdrawn' => 0,
            ]);

            $this->command->info('Test Customer created successfully!');
            $this->command->info('Phone: ' . $customerPhone);
            $this->command->info('Password: ' . $customerPassword);
        } else {
            $this->command->info('Test Customer already exists.');
        }

        // Create Test Driver
        $driverPhone = '0987654321';
        $driverPassword = 'password123';
        
        $existingDriver = User::where('phone', $driverPhone)
            ->where('user_type', 'driver')
            ->first();

        if (!$existingDriver) {
            $driver = User::create([
                'id' => Str::uuid(),
                'user_level_id' => $driverLevel->id,
                'first_name' => 'Test',
                'last_name' => 'Driver',
                'full_name' => 'Test Driver',
                'phone' => $driverPhone,
                'email' => 'testdriver@example.com',
                'password' => Hash::make($driverPassword),
                'user_type' => 'driver',
                'is_active' => 1,
                'ref_code' => generateReferralCode(),
                'phone_verified_at' => now(),
            ]);

            // Create user account for driver
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

            $this->command->info('Test Driver created successfully!');
            $this->command->info('Phone: ' . $driverPhone);
            $this->command->info('Password: ' . $driverPassword);
        } else {
            $this->command->info('Test Driver already exists.');
        }
    }
}

