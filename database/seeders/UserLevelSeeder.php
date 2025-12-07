<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UserManagement\Entities\UserLevel;
use Illuminate\Support\Str;

class UserLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Check if levels already exist
        $customerLevel = UserLevel::where('user_type', 'customer')
            ->where('sequence', 1)
            ->first();

        $driverLevel = UserLevel::where('user_type', 'driver')
            ->where('sequence', 1)
            ->first();

        // Create Customer Level (Bronze - Starting Level) if it doesn't exist
        if (!$customerLevel) {
            UserLevel::create([
                'id' => Str::uuid(),
                'sequence' => 1,
                'name' => 'Bronze',
                'reward_type' => 'no_rewards',
                'reward_amount' => null,
                'image' => null,
                'targeted_ride' => 0,
                'targeted_ride_point' => 0,
                'targeted_amount' => 0,
                'targeted_amount_point' => 0,
                'targeted_cancel' => 0,
                'targeted_cancel_point' => 0,
                'targeted_review' => 0,
                'targeted_review_point' => 0,
                'user_type' => 'customer',
                'is_active' => 1,
            ]);
            $this->command->info('Customer level (Bronze) created successfully.');
        } else {
            $this->command->info('Customer level (Bronze) already exists.');
        }

        // Create Driver Level (Bronze - Starting Level) if it doesn't exist
        if (!$driverLevel) {
            UserLevel::create([
                'id' => Str::uuid(),
                'sequence' => 1,
                'name' => 'Bronze',
                'reward_type' => 'no_rewards',
                'reward_amount' => null,
                'image' => null,
                'targeted_ride' => 0,
                'targeted_ride_point' => 0,
                'targeted_amount' => 0,
                'targeted_amount_point' => 0,
                'targeted_cancel' => 0,
                'targeted_cancel_point' => 0,
                'targeted_review' => 0,
                'targeted_review_point' => 0,
                'user_type' => 'driver',
                'is_active' => 1,
            ]);
            $this->command->info('Driver level (Bronze) created successfully.');
        } else {
            $this->command->info('Driver level (Bronze) already exists.');
        }
    }
}

