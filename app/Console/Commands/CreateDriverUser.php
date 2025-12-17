<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLevel;

class CreateDriverUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'driver:create {--phone=} {--email=} {--password=} {--first-name=} {--last-name=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a driver user in the database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get driver level
        $driverLevel = UserLevel::where('user_type', 'driver')
            ->where('sequence', 1)
            ->first();

        if (!$driverLevel) {
            $this->error('Driver level not found. Please run: php artisan db:seed --class=UserLevelSeeder');
            return 1;
        }

        // Generate credentials if not provided
        $phone = $this->option('phone') ?: $this->generatePhone();
        $email = $this->option('email') ?: "driver{$phone}@smartline.test";
        $password = $this->option('password') ?: $this->generatePassword();
        $firstName = $this->option('first-name') ?: 'Driver';
        $lastName = $this->option('last-name') ?: 'User';

        // Check if user already exists
        $existingDriver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if ($existingDriver) {
            $this->error("Driver with phone {$phone} already exists!");
            $this->info("Existing Driver Credentials:");
            $this->info("Phone: {$existingDriver->phone}");
            $this->info("Email: {$existingDriver->email}");
            $this->info("User ID: {$existingDriver->id}");
            return 1;
        }

        try {
            // Create driver user
            $driver = User::create([
                'id' => Str::uuid(),
                'user_level_id' => $driverLevel->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => "{$firstName} {$lastName}",
                'phone' => $phone,
                'email' => $email,
                'password' => Hash::make($password),
                'user_type' => 'driver',
                'is_active' => 1,
                'ref_code' => generateReferralCode(),
                'phone_verified_at' => now(),
            ]);

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

            $this->info('✅ Driver user created successfully!');
            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════');
            $this->info('           DRIVER USER CREDENTIALS');
            $this->info('═══════════════════════════════════════════════════════');
            $this->newLine();
            $this->info("Phone Number: {$phone}");
            $this->info("Email:        {$email}");
            $this->info("Password:     {$password}");
            $this->info("User ID:      {$driver->id}");
            $this->info("Name:         {$firstName} {$lastName}");
            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════');

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to create driver user: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Generate a random phone number
     */
    private function generatePhone(): string
    {
        return '1' . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    /**
     * Generate a random password
     */
    private function generatePassword(): string
    {
        return Str::random(12);
    }
}















