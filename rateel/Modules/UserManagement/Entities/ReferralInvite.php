<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReferralInvite extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'referrer_id',
        'invite_code',
        'invite_channel',
        'invite_token',
        'sent_at',
        'opened_at',
        'installed_at',
        'signup_at',
        'first_ride_at',
        'reward_at',
        'referee_id',
        'device_id',
        'ip_address',
        'user_agent',
        'platform',
        'status',
        'fraud_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'installed_at' => 'datetime',
        'signup_at' => 'datetime',
        'first_ride_at' => 'datetime',
        'reward_at' => 'datetime',
    ];

    const STATUS_SENT = 'sent';
    const STATUS_OPENED = 'opened';
    const STATUS_INSTALLED = 'installed';
    const STATUS_SIGNED_UP = 'signed_up';
    const STATUS_CONVERTED = 'converted';
    const STATUS_REWARDED = 'rewarded';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FRAUD_BLOCKED = 'fraud_blocked';

    const CHANNEL_LINK = 'link';
    const CHANNEL_CODE = 'code';
    const CHANNEL_QR = 'qr';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_WHATSAPP = 'whatsapp';
    const CHANNEL_COPY = 'copy';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (empty($invite->invite_token)) {
                $invite->invite_token = Str::random(64);
            }
            if (empty($invite->sent_at)) {
                $invite->sent_at = now();
            }
        });
    }

    /**
     * Referrer (who sent the invite)
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * Referee (who received and signed up)
     */
    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    /**
     * Reward record
     */
    public function reward(): HasOne
    {
        return $this->hasOne(ReferralReward::class, 'referral_invite_id');
    }

    /**
     * Generate shareable link
     */
    public function getShareableLinkAttribute(): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/invite/{$this->invite_token}";
    }

    /**
     * Check if invite is expired
     */
    public function isExpired(): bool
    {
        $settings = ReferralSetting::getSettings();
        $expiryDate = $this->sent_at->addDays($settings->invite_expiry_days);
        return now()->isAfter($expiryDate);
    }

    /**
     * Check if invite can still be used
     */
    public function isUsable(): bool
    {
        return in_array($this->status, [
            self::STATUS_SENT,
            self::STATUS_OPENED,
            self::STATUS_INSTALLED,
        ]) && !$this->isExpired();
    }

    /**
     * Scope: By referrer
     */
    public function scopeByReferrer($query, $referrerId)
    {
        return $query->where('referrer_id', $referrerId);
    }

    /**
     * Scope: Successful (converted or rewarded)
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [self::STATUS_CONVERTED, self::STATUS_REWARDED]);
    }

    /**
     * Scope: Pending (not yet converted)
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            self::STATUS_SENT,
            self::STATUS_OPENED,
            self::STATUS_INSTALLED,
            self::STATUS_SIGNED_UP,
        ]);
    }

    /**
     * Scope: Today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope: By date range
     */
    public function scopeDateRange($query, $start, $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }
}
