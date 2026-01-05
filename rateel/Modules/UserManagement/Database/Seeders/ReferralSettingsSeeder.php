<?php

namespace Modules\UserManagement\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\UserManagement\Entities\ReferralSetting;

class ReferralSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates default referral settings if none exist.
     */
    public function run(): void
    {
        if (ReferralSetting::count() === 0) {
            ReferralSetting::create([
                'referrer_points' => 100,
                'referee_points' => 50,
                'reward_trigger' => 'first_ride',
                'min_ride_fare' => 20.00,
                'required_rides' => 1,
                'max_referrals_per_day' => 10,
                'max_referrals_total' => 100,
                'invite_expiry_days' => 30,
                'cooldown_minutes' => 5,
                'block_same_device' => true,
                'block_same_ip' => true,
                'require_phone_verified' => true,
                'is_active' => true,
                'show_leaderboard' => true,
            ]);

            $this->command->info('Referral settings created successfully.');
        } else {
            $this->command->info('Referral settings already exist. Skipping.');
        }
    }
}
