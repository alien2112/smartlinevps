<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class CouponRedemption extends Model
{
    use HasUuids;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'ride_id',
        'idempotency_key',
        'status',
        'estimated_fare',
        'estimated_discount',
        'final_fare',
        'final_discount',
        'city_id',
        'service_type',
        'reserved_at',
        'applied_at',
        'cancelled_at',
        'expires_at',
    ];

    protected $casts = [
        'estimated_fare' => 'decimal:2',
        'estimated_discount' => 'decimal:2',
        'final_fare' => 'decimal:2',
        'final_discount' => 'decimal:2',
        'reserved_at' => 'datetime',
        'applied_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_RESERVED = 'RESERVED';
    public const STATUS_APPLIED = 'APPLIED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_EXPIRED = 'EXPIRED';

    /**
     * The coupon that was redeemed
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The user who redeemed the coupon
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The ride this redemption is for
     */
    public function ride(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'ride_id');
    }

    /**
     * Check if redemption is active (reserved or applied)
     */
    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_RESERVED, self::STATUS_APPLIED], true);
    }

    /**
     * Check if redemption is reserved
     */
    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    /**
     * Check if redemption is applied
     */
    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Check if reservation has expired
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }
        if ($this->expires_at && now()->gt($this->expires_at)) {
            return true;
        }
        return false;
    }

    /**
     * Mark as applied
     */
    public function markApplied(float $finalFare, float $finalDiscount): bool
    {
        return $this->update([
            'status' => self::STATUS_APPLIED,
            'final_fare' => $finalFare,
            'final_discount' => $finalDiscount,
            'applied_at' => now(),
        ]);
    }

    /**
     * Mark as cancelled
     */
    public function markCancelled(): bool
    {
        return $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Mark as expired
     */
    public function markExpired(): bool
    {
        return $this->update([
            'status' => self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Scope: Reserved redemptions
     */
    public function scopeReserved($query)
    {
        return $query->where('status', self::STATUS_RESERVED);
    }

    /**
     * Scope: Applied redemptions
     */
    public function scopeApplied($query)
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    /**
     * Scope: Active (reserved or applied)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_RESERVED, self::STATUS_APPLIED]);
    }

    /**
     * Scope: For a specific ride
     */
    public function scopeForRide($query, string $rideId)
    {
        return $query->where('ride_id', $rideId);
    }

    /**
     * Scope: Expired reservations
     */
    public function scopeExpiredReservations($query)
    {
        return $query->where('status', self::STATUS_RESERVED)
                     ->where('expires_at', '<', now());
    }
}
