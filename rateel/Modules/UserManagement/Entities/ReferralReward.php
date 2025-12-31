<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TripManagement\Entities\TripRequest;

class ReferralReward extends Model
{
    use HasUuid;

    protected $fillable = [
        'referral_invite_id',
        'referrer_id',
        'referrer_points',
        'referrer_status',
        'referrer_paid_at',
        'referee_id',
        'referee_points',
        'referee_status',
        'referee_paid_at',
        'trigger_type',
        'trigger_trip_id',
        'notes',
    ];

    protected $casts = [
        'referrer_points' => 'integer',
        'referee_points' => 'integer',
        'referrer_paid_at' => 'datetime',
        'referee_paid_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_ELIGIBLE = 'eligible';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_FRAUD = 'fraud';

    const TRIGGER_SIGNUP = 'signup';
    const TRIGGER_FIRST_RIDE = 'first_ride';
    const TRIGGER_THREE_RIDES = 'three_rides';
    const TRIGGER_DEPOSIT = 'deposit';

    /**
     * Referral invite
     */
    public function invite(): BelongsTo
    {
        return $this->belongsTo(ReferralInvite::class, 'referral_invite_id');
    }

    /**
     * Referrer user
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * Referee user
     */
    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }

    /**
     * Trigger trip
     */
    public function triggerTrip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trigger_trip_id');
    }

    /**
     * Total points issued
     */
    public function getTotalPointsAttribute(): int
    {
        $total = 0;
        if ($this->referrer_status === self::STATUS_PAID) {
            $total += $this->referrer_points;
        }
        if ($this->referee_status === self::STATUS_PAID) {
            $total += $this->referee_points;
        }
        return $total;
    }

    /**
     * Scope: Paid rewards
     */
    public function scopePaid($query)
    {
        return $query->where('referrer_status', self::STATUS_PAID);
    }

    /**
     * Scope: By referrer
     */
    public function scopeByReferrer($query, $referrerId)
    {
        return $query->where('referrer_id', $referrerId);
    }

    /**
     * Scope: Today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
