<?php

namespace Modules\UserManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\ReferralFraudLog;
use Modules\UserManagement\Entities\ReferralInvite;
use Modules\UserManagement\Entities\ReferralReward;
use Modules\UserManagement\Entities\ReferralSetting;
use Modules\UserManagement\Entities\User;

class ReferralService
{
    /**
     * Generate a new referral invite
     */
    public function generateInvite(User $referrer, string $channel = 'link', ?string $platform = null): ?ReferralInvite
    {
        $settings = ReferralSetting::getSettings();

        if (!$settings->is_active) {
            return null;
        }

        // Check cooldown
        $lastInvite = ReferralInvite::where('referrer_id', $referrer->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastInvite && $lastInvite->created_at->addMinutes($settings->cooldown_minutes)->isFuture()) {
            return null;
        }

        // Ensure user has ref_code
        if (empty($referrer->ref_code)) {
            $referrer->ref_code = $this->generateUniqueRefCode($referrer);
            $referrer->save();
        }

        return ReferralInvite::create([
            'referrer_id' => $referrer->id,
            'invite_code' => $referrer->ref_code,
            'invite_channel' => $channel,
            'invite_token' => Str::random(64),
            'platform' => $platform,
            'status' => ReferralInvite::STATUS_SENT,
        ]);
    }

    /**
     * Generate unique referral code based on user's name
     * Format: name-randid (e.g., ahmed-a1b2, john-x7y8)
     */
    public function generateUniqueRefCode(?User $user = null): string
    {
        // Get name prefix from user's first name (sanitized, lowercase, max 10 chars)
        $namePrefix = '';
        if ($user && !empty($user->first_name)) {
            // Remove non-alphanumeric characters, convert to lowercase
            $namePrefix = preg_replace('/[^a-zA-Z0-9]/', '', $user->first_name);
            $namePrefix = strtolower(substr($namePrefix, 0, 10));
        }

        // If no valid name, use 'user' as fallback
        if (empty($namePrefix)) {
            $namePrefix = 'user';
        }

        do {
            // Generate random suffix (4 alphanumeric characters)
            $randomSuffix = strtolower(Str::random(4));
            $code = $namePrefix . '-' . $randomSuffix;
        } while (User::where('ref_code', $code)->exists());

        return $code;
    }

    /**
     * Track invite opened (link clicked)
     */
    public function trackOpened(string $token, ?string $deviceId = null, ?string $ip = null, ?string $userAgent = null): ?ReferralInvite
    {
        $invite = ReferralInvite::where('invite_token', $token)->first();

        if (!$invite || !$invite->isUsable()) {
            return null;
        }

        $invite->update([
            'opened_at' => now(),
            'device_id' => $deviceId ?? $invite->device_id,
            'ip_address' => $ip ?? $invite->ip_address,
            'user_agent' => $userAgent ?? $invite->user_agent,
            'status' => ReferralInvite::STATUS_OPENED,
        ]);

        return $invite;
    }

    /**
     * Track app installed
     */
    public function trackInstalled(string $token, string $deviceId, ?string $platform = null): ?ReferralInvite
    {
        $invite = ReferralInvite::where('invite_token', $token)->first();

        if (!$invite || !$invite->isUsable()) {
            return null;
        }

        $invite->update([
            'installed_at' => now(),
            'device_id' => $deviceId,
            'platform' => $platform ?? $invite->platform,
            'status' => ReferralInvite::STATUS_INSTALLED,
        ]);

        return $invite;
    }

    /**
     * Link signup to referral invite
     */
    public function linkSignup(User $referee, string $refCode, ?string $deviceId = null, ?string $ip = null): ?ReferralInvite
    {
        $settings = ReferralSetting::getSettings();

        if (!$settings->is_active) {
            return null;
        }

        // Find referrer by code
        $referrer = User::where('ref_code', $refCode)->first();

        if (!$referrer) {
            Log::warning('Referral: Invalid ref_code', ['ref_code' => $refCode]);
            return null;
        }

        // Fraud check: Self-referral
        if ($referrer->id === $referee->id) {
            $this->logFraud(null, $referee->id, ReferralFraudLog::TYPE_SELF_REFERRAL, [
                'ref_code' => $refCode,
            ], $deviceId, $ip);
            return null;
        }

        // Fraud check: Same device
        if ($settings->block_same_device && $deviceId && $referrer->device_fingerprint === $deviceId) {
            $this->logFraud(null, $referee->id, ReferralFraudLog::TYPE_DUPLICATE_DEVICE, [
                'referrer_device' => $referrer->device_fingerprint,
                'referee_device' => $deviceId,
            ], $deviceId, $ip);
            return null;
        }

        // Fraud check: Same IP
        if ($settings->block_same_ip && $ip && $referrer->signup_ip === $ip) {
            $this->logFraud(null, $referee->id, ReferralFraudLog::TYPE_DUPLICATE_IP, [
                'referrer_ip' => $referrer->signup_ip,
                'referee_ip' => $ip,
            ], $deviceId, $ip);
            return null;
        }

        // Check referrer limits
        if (!$this->checkReferrerLimits($referrer)) {
            $this->logFraud(null, $referee->id, ReferralFraudLog::TYPE_VELOCITY_LIMIT, [
                'referrer_id' => $referrer->id,
            ], $deviceId, $ip);
            return null;
        }

        // Find or create invite
        $invite = ReferralInvite::where('referrer_id', $referrer->id)
            ->where('invite_code', $refCode)
            ->whereNull('referee_id')
            ->whereIn('status', [
                ReferralInvite::STATUS_SENT,
                ReferralInvite::STATUS_OPENED,
                ReferralInvite::STATUS_INSTALLED,
            ])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$invite) {
            // Create new invite record for this signup
            $invite = ReferralInvite::create([
                'referrer_id' => $referrer->id,
                'invite_code' => $refCode,
                'invite_channel' => ReferralInvite::CHANNEL_CODE,
                'invite_token' => Str::random(64),
                'status' => ReferralInvite::STATUS_SENT,
            ]);
        }

        // Check expiry
        if ($invite->isExpired()) {
            $invite->update(['status' => ReferralInvite::STATUS_EXPIRED]);
            $this->logFraud($invite->id, $referee->id, ReferralFraudLog::TYPE_EXPIRED_INVITE, [
                'sent_at' => $invite->sent_at,
            ], $deviceId, $ip);
            return null;
        }

        // Link referee
        $invite->update([
            'referee_id' => $referee->id,
            'signup_at' => now(),
            'device_id' => $deviceId ?? $invite->device_id,
            'ip_address' => $ip ?? $invite->ip_address,
            'status' => ReferralInvite::STATUS_SIGNED_UP,
        ]);

        // Update referee's referred_by
        $referee->update([
            'referred_by' => $referrer->id,
            'device_fingerprint' => $deviceId,
            'signup_ip' => $ip,
        ]);

        // Update referrer's count
        $referrer->increment('referral_count');

        // If trigger is signup, process reward immediately
        if ($settings->reward_trigger === 'signup') {
            $this->processReward($invite);
        }

        Log::info('Referral: Signup linked', [
            'invite_id' => $invite->id,
            'referrer_id' => $referrer->id,
            'referee_id' => $referee->id,
        ]);

        return $invite;
    }

    /**
     * Check referrer limits
     */
    protected function checkReferrerLimits(User $referrer): bool
    {
        $settings = ReferralSetting::getSettings();

        // Check daily limit
        $todayCount = ReferralReward::where('referrer_id', $referrer->id)
            ->whereDate('created_at', today())
            ->count();

        if ($todayCount >= $settings->max_referrals_per_day) {
            return false;
        }

        // Check total limit
        if ($referrer->successful_referrals >= $settings->max_referrals_total) {
            return false;
        }

        return true;
    }

    /**
     * Process reward on trip completion (called from observer)
     */
    public function processRideCompletion(TripRequest $trip): bool
    {
        $settings = ReferralSetting::getSettings();

        if (!$settings->is_active) {
            return false;
        }

        // Only for ride_request type
        if ($trip->type !== 'ride_request') {
            return false;
        }

        // Must be completed and paid
        if ($trip->current_status !== 'completed' || $trip->payment_status !== 'paid') {
            return false;
        }

        $customer = $trip->customer;
        if (!$customer) {
            return false;
        }

        // Find referral invite
        $invite = ReferralInvite::where('referee_id', $customer->id)
            ->where('status', ReferralInvite::STATUS_SIGNED_UP)
            ->first();

        if (!$invite) {
            return false;
        }

        // Check if reward already processed
        if (ReferralReward::where('referral_invite_id', $invite->id)->exists()) {
            return false;
        }

        // Count completed rides
        $completedRides = TripRequest::where('customer_id', $customer->id)
            ->where('type', 'ride_request')
            ->where('current_status', 'completed')
            ->where('payment_status', 'paid')
            ->count();

        // Check trigger condition
        $shouldReward = match ($settings->reward_trigger) {
            'first_ride' => $completedRides === 1,
            'three_rides' => $completedRides === $settings->required_rides,
            default => false,
        };

        if (!$shouldReward) {
            return false;
        }

        // Check minimum fare
        if ($trip->paid_fare < $settings->min_ride_fare) {
            $this->logFraud($invite->id, $customer->id, ReferralFraudLog::TYPE_LOW_FARE_RIDE, [
                'paid_fare' => $trip->paid_fare,
                'min_required' => $settings->min_ride_fare,
            ]);
            return false;
        }

        // Update invite
        $invite->update([
            'first_ride_at' => now(),
            'status' => ReferralInvite::STATUS_CONVERTED,
        ]);

        return $this->processReward($invite, $trip);
    }

    /**
     * Process and issue reward points
     */
    public function processReward(ReferralInvite $invite, ?TripRequest $trip = null): bool
    {
        $settings = ReferralSetting::getSettings();

        if (!$settings->is_active) {
            return false;
        }

        $referrer = $invite->referrer;
        $referee = $invite->referee;

        if (!$referrer || !$referee) {
            return false;
        }

        // Final fraud checks
        if (!$this->checkReferrerLimits($referrer)) {
            $this->logFraud($invite->id, $referee->id, ReferralFraudLog::TYPE_VELOCITY_LIMIT);
            $invite->update(['status' => ReferralInvite::STATUS_FRAUD_BLOCKED, 'fraud_reason' => 'velocity_limit']);
            return false;
        }

        DB::beginTransaction();
        try {
            // Create reward record
            $reward = ReferralReward::create([
                'referral_invite_id' => $invite->id,
                'referrer_id' => $referrer->id,
                'referrer_points' => $settings->referrer_points,
                'referrer_status' => ReferralReward::STATUS_ELIGIBLE,
                'referee_id' => $referee->id,
                'referee_points' => $settings->referee_points,
                'referee_status' => ReferralReward::STATUS_ELIGIBLE,
                'trigger_type' => $settings->reward_trigger,
                'trigger_trip_id' => $trip?->id,
            ]);

            // Issue points to referrer
            $referrer->increment('loyalty_points', $settings->referrer_points);
            $reward->update([
                'referrer_status' => ReferralReward::STATUS_PAID,
                'referrer_paid_at' => now(),
            ]);

            // Issue points to referee
            $referee->increment('loyalty_points', $settings->referee_points);
            $reward->update([
                'referee_status' => ReferralReward::STATUS_PAID,
                'referee_paid_at' => now(),
            ]);

            // Update counts
            $referrer->increment('successful_referrals');

            // Update invite status
            $invite->update([
                'reward_at' => now(),
                'status' => ReferralInvite::STATUS_REWARDED,
            ]);

            DB::commit();

            Log::info('Referral: Reward issued', [
                'invite_id' => $invite->id,
                'referrer_id' => $referrer->id,
                'referrer_points' => $settings->referrer_points,
                'referee_id' => $referee->id,
                'referee_points' => $settings->referee_points,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Referral: Reward failed', [
                'invite_id' => $invite->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Log fraud attempt
     */
    public function logFraud(
        ?string $inviteId,
        ?string $userId,
        string $fraudType,
        array $details = [],
        ?string $deviceId = null,
        ?string $ip = null
    ): ReferralFraudLog {
        return ReferralFraudLog::create([
            'referral_invite_id' => $inviteId,
            'user_id' => $userId,
            'fraud_type' => $fraudType,
            'details' => $details,
            'device_id' => $deviceId,
            'ip_address' => $ip,
        ]);
    }

    /**
     * Get user's referral stats
     */
    public function getUserStats(User $user): array
    {
        $invitesSent = ReferralInvite::where('referrer_id', $user->id)->count();
        $invitesOpened = ReferralInvite::where('referrer_id', $user->id)->whereNotNull('opened_at')->count();
        $signups = ReferralInvite::where('referrer_id', $user->id)->whereNotNull('signup_at')->count();
        $conversions = ReferralInvite::where('referrer_id', $user->id)->successful()->count();

        $totalPointsEarned = ReferralReward::where('referrer_id', $user->id)
            ->where('referrer_status', ReferralReward::STATUS_PAID)
            ->sum('referrer_points');

        return [
            'ref_code' => $user->ref_code,
            'invites_sent' => $invitesSent,
            'invites_opened' => $invitesOpened,
            'signups' => $signups,
            'conversions' => $conversions,
            'total_points_earned' => (int) $totalPointsEarned,
            'referral_count' => $user->referral_count,
            'successful_referrals' => $user->successful_referrals,
        ];
    }

    /**
     * Get analytics for admin dashboard
     */
    public function getAnalytics(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? \Carbon\Carbon::parse($startDate)->startOfDay() : now()->subDays(30)->startOfDay();
        $end = $endDate ? \Carbon\Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        return [
            'invites_sent' => ReferralInvite::dateRange($start, $end)->count(),
            'invites_opened' => ReferralInvite::dateRange($start, $end)->whereNotNull('opened_at')->count(),
            'installs' => ReferralInvite::dateRange($start, $end)->whereNotNull('installed_at')->count(),
            'signups' => ReferralInvite::dateRange($start, $end)->whereNotNull('signup_at')->count(),
            'first_rides' => ReferralInvite::dateRange($start, $end)->whereNotNull('first_ride_at')->count(),
            'conversions' => ReferralInvite::dateRange($start, $end)->successful()->count(),
            'fraud_blocks' => ReferralFraudLog::dateRange($start, $end)->count(),
            'rewards_issued' => ReferralReward::whereBetween('created_at', [$start, $end])
                ->where('referrer_status', ReferralReward::STATUS_PAID)->count(),
            'total_points_issued' => ReferralReward::whereBetween('created_at', [$start, $end])
                ->where('referrer_status', ReferralReward::STATUS_PAID)
                ->sum(DB::raw('referrer_points + referee_points')),
            'top_referrers' => $this->getTopReferrers($start, $end, 10),
            'fraud_by_type' => ReferralFraudLog::dateRange($start, $end)
                ->selectRaw('fraud_type, COUNT(*) as count')
                ->groupBy('fraud_type')
                ->pluck('count', 'fraud_type'),
            'daily_stats' => $this->getDailyStats($start, $end),
        ];
    }

    /**
     * Get top referrers
     */
    public function getTopReferrers($start, $end, int $limit = 10): array
    {
        return User::select('users.id', 'users.first_name', 'users.last_name', 'users.ref_code', 'users.successful_referrals')
            ->withCount(['referralInvites as period_conversions' => function ($q) use ($start, $end) {
                $q->successful()->whereBetween('created_at', [$start, $end]);
            }])
            ->withSum(['referralRewardsAsReferrer as period_points' => function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])
                    ->where('referrer_status', ReferralReward::STATUS_PAID);
            }], 'referrer_points')
            ->having('period_conversions', '>', 0)
            ->orderBy('period_conversions', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get daily stats for chart
     */
    protected function getDailyStats($start, $end): array
    {
        return ReferralInvite::selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as invites')
            ->selectRaw('SUM(CASE WHEN signup_at IS NOT NULL THEN 1 ELSE 0 END) as signups')
            ->selectRaw('SUM(CASE WHEN status IN ("converted", "rewarded") THEN 1 ELSE 0 END) as conversions')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Validate referral code
     */
    public function validateCode(string $code): array
    {
        $settings = ReferralSetting::getSettings();

        if (!$settings->is_active) {
            return ['valid' => false, 'message' => 'Referral program is currently inactive'];
        }

        $referrer = User::where('ref_code', $code)->first();

        if (!$referrer) {
            return ['valid' => false, 'message' => 'Invalid referral code'];
        }

        if (!$referrer->is_active) {
            return ['valid' => false, 'message' => 'Referrer account is inactive'];
        }

        if ($referrer->successful_referrals >= $settings->max_referrals_total) {
            return ['valid' => false, 'message' => 'Referrer has reached maximum referrals'];
        }

        return [
            'valid' => true,
            'message' => 'Valid referral code',
            'referrer_name' => $referrer->first_name,
            'referee_points' => $settings->referee_points,
        ];
    }
}
