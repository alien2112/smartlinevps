<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralFraudLog extends Model
{
    use HasUuid;

    public $timestamps = false;

    protected $fillable = [
        'referral_invite_id',
        'user_id',
        'fraud_type',
        'details',
        'device_id',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    const TYPE_SELF_REFERRAL = 'self_referral';
    const TYPE_DUPLICATE_DEVICE = 'duplicate_device';
    const TYPE_DUPLICATE_IP = 'duplicate_ip';
    const TYPE_VELOCITY_LIMIT = 'velocity_limit';
    const TYPE_FAKE_ACCOUNT = 'fake_account';
    const TYPE_VPN_DETECTED = 'vpn_detected';
    const TYPE_EXPIRED_INVITE = 'expired_invite';
    const TYPE_LOW_FARE_RIDE = 'low_fare_ride';
    const TYPE_UNPAID_RIDE = 'unpaid_ride';
    const TYPE_BLOCKED_USER = 'blocked_user';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($log) {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    /**
     * Referral invite
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(ReferralInvite::class, 'referral_invite_id');
    }

    /**
     * User involved
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get human-readable fraud type
     */
    public function getFraudTypeLabel(): string
    {
        return match ($this->fraud_type) {
            self::TYPE_SELF_REFERRAL => 'Self Referral Attempt',
            self::TYPE_DUPLICATE_DEVICE => 'Duplicate Device',
            self::TYPE_DUPLICATE_IP => 'Duplicate IP Address',
            self::TYPE_VELOCITY_LIMIT => 'Too Many Referrals',
            self::TYPE_FAKE_ACCOUNT => 'Fake Account Suspected',
            self::TYPE_VPN_DETECTED => 'VPN/Proxy Detected',
            self::TYPE_EXPIRED_INVITE => 'Expired Invite',
            self::TYPE_LOW_FARE_RIDE => 'Ride Fare Too Low',
            self::TYPE_UNPAID_RIDE => 'Unpaid Ride',
            self::TYPE_BLOCKED_USER => 'Blocked User',
            default => ucfirst(str_replace('_', ' ', $this->fraud_type)),
        };
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('fraud_type', $type);
    }

    /**
     * Scope: By date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
