<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ReferralSetting extends Model
{
    use HasUuid;

    protected $fillable = [
        // Points rewards
        'referrer_points',
        'referee_points',

        // Trigger settings
        'reward_trigger',
        'min_ride_fare',
        'required_rides',

        // Limits
        'max_referrals_per_day',
        'max_referrals_total',
        'invite_expiry_days',

        // Fraud prevention
        'block_same_device',
        'block_same_ip',
        'require_phone_verified',
        'cooldown_minutes',

        // Status
        'is_active',
        'show_leaderboard',
    ];

    protected $casts = [
        'referrer_points' => 'integer',
        'referee_points' => 'integer',
        'min_ride_fare' => 'decimal:2',
        'required_rides' => 'integer',
        'max_referrals_per_day' => 'integer',
        'max_referrals_total' => 'integer',
        'invite_expiry_days' => 'integer',
        'block_same_device' => 'boolean',
        'block_same_ip' => 'boolean',
        'require_phone_verified' => 'boolean',
        'cooldown_minutes' => 'integer',
        'is_active' => 'boolean',
        'show_leaderboard' => 'boolean',
    ];

    /**
     * Get settings (cached)
     */
    public static function getSettings(): self
    {
        return Cache::remember('referral_settings', 3600, function () {
            return self::first() ?? new self([
                'referrer_points' => 100,
                'referee_points' => 50,
                'reward_trigger' => 'first_ride',
                'is_active' => true,
            ]);
        });
    }

    /**
     * Clear settings cache
     */
    public static function clearCache(): void
    {
        Cache::forget('referral_settings');
    }

    /**
     * Boot method to clear cache on update
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function () {
            self::clearCache();
        });
    }
}
