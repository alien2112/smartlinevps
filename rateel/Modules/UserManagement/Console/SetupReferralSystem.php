<?php

namespace Modules\UserManagement\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\UserManagement\Entities\ReferralSetting;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\ReferralService;

class SetupReferralSystem extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'referral:setup 
                            {--generate-codes : Generate referral codes for all users without one}
                            {--reset-settings : Reset referral settings to defaults}';

    /**
     * The console command description.
     */
    protected $description = 'Set up the referral system - create settings and generate codes';

    protected ReferralService $referralService;

    public function __construct(ReferralService $referralService)
    {
        parent::__construct();
        $this->referralService = $referralService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Setting up Referral System...');
        $this->newLine();

        // Step 1: Check if tables exist
        $this->info('Step 1: Checking database tables...');
        if (!Schema::hasTable('referral_settings')) {
            $this->error('❌ referral_settings table does not exist!');
            $this->warn('Please run: php artisan migrate');
            return 1;
        }

        if (!Schema::hasTable('referral_invites')) {
            $this->error('❌ referral_invites table does not exist!');
            $this->warn('Please run: php artisan migrate');
            return 1;
        }

        if (!Schema::hasTable('referral_rewards')) {
            $this->error('❌ referral_rewards table does not exist!');
            $this->warn('Please run: php artisan migrate');
            return 1;
        }

        $this->info('✅ All referral tables exist.');
        $this->newLine();

        // Step 2: Create or update settings
        $this->info('Step 2: Setting up referral settings...');
        
        if ($this->option('reset-settings') || ReferralSetting::count() === 0) {
            // Clear cache first
            ReferralSetting::clearCache();
            
            // Delete existing and create new
            if ($this->option('reset-settings')) {
                ReferralSetting::truncate();
                $this->warn('⚠️ Existing settings deleted.');
            }
            
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
            
            $this->info('✅ Default referral settings created.');
        } else {
            $settings = ReferralSetting::first();
            $this->info('✅ Referral settings already exist:');
            $this->table(
                ['Setting', 'Value'],
                [
                    ['Status', $settings->is_active ? '✅ Active' : '❌ Inactive'],
                    ['Referrer Points', $settings->referrer_points],
                    ['Referee Points', $settings->referee_points],
                    ['Reward Trigger', $settings->reward_trigger],
                    ['Min Ride Fare', $settings->min_ride_fare . ' EGP'],
                ]
            );
        }
        $this->newLine();

        // Step 3: Generate referral codes for users
        if ($this->option('generate-codes')) {
            $this->info('Step 3: Generating referral codes for users...');
            
            $usersWithoutCode = User::whereNull('ref_code')
                ->orWhere('ref_code', '')
                ->count();
            
            if ($usersWithoutCode === 0) {
                $this->info('✅ All users already have referral codes.');
            } else {
                $this->info("Found {$usersWithoutCode} users without referral codes.");
                
                $bar = $this->output->createProgressBar($usersWithoutCode);
                $bar->start();
                
                User::whereNull('ref_code')
                    ->orWhere('ref_code', '')
                    ->chunkById(100, function ($users) use ($bar) {
                        foreach ($users as $user) {
                            $user->ref_code = $this->referralService->generateUniqueRefCode($user);
                            $user->save();
                            $bar->advance();
                        }
                    });
                
                $bar->finish();
                $this->newLine();
                $this->info("✅ Generated referral codes for {$usersWithoutCode} users.");
            }
        } else {
            $usersWithoutCode = User::whereNull('ref_code')
                ->orWhere('ref_code', '')
                ->count();
            
            if ($usersWithoutCode > 0) {
                $this->warn("⚠️ {$usersWithoutCode} users don't have referral codes.");
                $this->info('Run with --generate-codes to generate them.');
            }
        }
        $this->newLine();

        // Step 4: Show summary
        $this->info('📊 Referral System Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Users', User::count()],
                ['Users with Codes', User::whereNotNull('ref_code')->where('ref_code', '!=', '')->count()],
                ['Total Invites', DB::table('referral_invites')->count()],
                ['Total Rewards', DB::table('referral_rewards')->count()],
                ['Conversions', DB::table('referral_invites')->whereIn('status', ['converted', 'rewarded'])->count()],
            ]
        );
        $this->newLine();

        $this->info('🎉 Referral system setup complete!');
        $this->newLine();
        $this->info('Admin Dashboard: /admin/referral');
        $this->info('API Endpoints:');
        $this->line('  GET  /api/customer/referral/my-code');
        $this->line('  POST /api/customer/referral/generate-invite');
        $this->line('  GET  /api/customer/referral/stats');
        $this->line('  GET  /api/customer/referral/history');
        $this->line('  GET  /api/customer/referral/rewards');
        $this->line('  GET  /api/customer/referral/leaderboard');
        
        return 0;
    }
}
