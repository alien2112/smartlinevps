<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates complete referral tracking system with points-based rewards
     */
    public function up(): void
    {
        // 1. Referral Settings - All configurable from dashboard
        Schema::create('referral_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Points rewards (not money)
            $table->integer('referrer_points')->default(100)->comment('Points given to referrer');
            $table->integer('referee_points')->default(50)->comment('Points given to new user');

            // Trigger settings
            $table->enum('reward_trigger', ['signup', 'first_ride', 'three_rides', 'deposit'])->default('first_ride');
            $table->decimal('min_ride_fare', 16, 2)->default(20.00)->comment('Minimum fare for ride to count');
            $table->integer('required_rides')->default(1)->comment('Number of rides required for reward');

            // Limits
            $table->integer('max_referrals_per_day')->default(10)->comment('Max successful referrals per day');
            $table->integer('max_referrals_total')->default(100)->comment('Max total referrals per user');
            $table->integer('invite_expiry_days')->default(30)->comment('Days before invite expires');

            // Fraud prevention settings
            $table->boolean('block_same_device')->default(true);
            $table->boolean('block_same_ip')->default(true);
            $table->boolean('require_phone_verified')->default(true);
            $table->integer('cooldown_minutes')->default(5)->comment('Minutes between invites');

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('show_leaderboard')->default(true);

            $table->timestamps();
        });

        // 2. Referral Invites - Track entire funnel
        Schema::create('referral_invites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referrer_id')->constrained('users')->onDelete('cascade');

            // Invite details
            $table->string('invite_code', 20)->comment('Same as users.ref_code');
            $table->enum('invite_channel', ['link', 'code', 'qr', 'sms', 'whatsapp', 'copy'])->default('link');
            $table->string('invite_token', 64)->unique()->comment('Unique tracking token');

            // Funnel tracking timestamps
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('opened_at')->nullable()->comment('Link clicked');
            $table->timestamp('installed_at')->nullable()->comment('App installed');
            $table->timestamp('signup_at')->nullable()->comment('Account created');
            $table->timestamp('first_ride_at')->nullable()->comment('First ride completed');
            $table->timestamp('reward_at')->nullable()->comment('Reward issued');

            // Referee (filled after signup)
            $table->foreignUuid('referee_id')->nullable()->constrained('users')->onDelete('set null');

            // Device/Fraud tracking
            $table->string('device_id', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('platform', 20)->nullable()->comment('ios, android, web');

            // Status
            $table->enum('status', [
                'sent',
                'opened',
                'installed',
                'signed_up',
                'converted',
                'rewarded',
                'expired',
                'fraud_blocked'
            ])->default('sent');
            $table->string('fraud_reason', 255)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('referrer_id');
            $table->index('referee_id');
            $table->index('invite_token');
            $table->index('status');
            $table->index('device_id');
            $table->index('ip_address');
            $table->index(['referrer_id', 'status']);
            $table->index('created_at');
        });

        // 3. Referral Rewards - Track points issued
        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_invite_id')->constrained('referral_invites')->onDelete('cascade');

            // Referrer reward
            $table->foreignUuid('referrer_id')->constrained('users')->onDelete('cascade');
            $table->integer('referrer_points')->default(0);
            $table->enum('referrer_status', ['pending', 'eligible', 'paid', 'failed', 'fraud'])->default('pending');
            $table->timestamp('referrer_paid_at')->nullable();

            // Referee reward
            $table->foreignUuid('referee_id')->constrained('users')->onDelete('cascade');
            $table->integer('referee_points')->default(0);
            $table->enum('referee_status', ['pending', 'eligible', 'paid', 'failed', 'fraud'])->default('pending');
            $table->timestamp('referee_paid_at')->nullable();

            // Trigger info
            $table->enum('trigger_type', ['signup', 'first_ride', 'three_rides', 'deposit'])->default('first_ride');
            $table->foreignUuid('trigger_trip_id')->nullable()->constrained('trip_requests')->onDelete('set null');

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('referrer_id');
            $table->index('referee_id');
            $table->index(['referrer_status', 'referee_status']);
            $table->index('created_at');
        });

        // 4. Referral Fraud Logs
        Schema::create('referral_fraud_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_invite_id')->nullable()->constrained('referral_invites')->onDelete('set null');
            $table->foreignUuid('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->enum('fraud_type', [
                'self_referral',
                'duplicate_device',
                'duplicate_ip',
                'velocity_limit',
                'fake_account',
                'vpn_detected',
                'expired_invite',
                'low_fare_ride',
                'unpaid_ride',
                'blocked_user'
            ]);
            $table->json('details')->nullable();

            $table->string('device_id', 255)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index('user_id');
            $table->index('fraud_type');
            $table->index('device_id');
            $table->index('ip_address');
            $table->index('created_at');
        });

        // 5. Add columns to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'device_fingerprint')) {
                $table->string('device_fingerprint', 255)->nullable()->after('ref_code');
            }
            if (!Schema::hasColumn('users', 'referral_count')) {
                $table->integer('referral_count')->default(0)->after('device_fingerprint');
            }
            if (!Schema::hasColumn('users', 'successful_referrals')) {
                $table->integer('successful_referrals')->default(0)->after('referral_count');
            }
            if (!Schema::hasColumn('users', 'signup_ip')) {
                $table->string('signup_ip', 45)->nullable()->after('successful_referrals');
            }
            if (!Schema::hasColumn('users', 'referred_by')) {
                $table->foreignUuid('referred_by')->nullable()->after('signup_ip');
            }
        });

        // 6. Insert default settings
        \DB::table('referral_settings')->insert([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'referrer_points' => 100,
            'referee_points' => 50,
            'reward_trigger' => 'first_ride',
            'min_ride_fare' => 20.00,
            'required_rides' => 1,
            'max_referrals_per_day' => 10,
            'max_referrals_total' => 100,
            'invite_expiry_days' => 30,
            'block_same_device' => true,
            'block_same_ip' => true,
            'require_phone_verified' => true,
            'cooldown_minutes' => 5,
            'is_active' => true,
            'show_leaderboard' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_fraud_logs');
        Schema::dropIfExists('referral_rewards');
        Schema::dropIfExists('referral_invites');
        Schema::dropIfExists('referral_settings');

        Schema::table('users', function (Blueprint $table) {
            $columns = ['device_fingerprint', 'referral_count', 'successful_referrals', 'signup_ip', 'referred_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
