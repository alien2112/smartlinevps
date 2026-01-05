<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class OfferUsage extends Model
{
    use HasUuids;

    protected $fillable = [
        'offer_id',
        'user_id',
        'trip_id',
        'original_fare',
        'discount_amount',
        'final_fare',
        'status',
    ];

    protected $casts = [
        'original_fare' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_fare' => 'decimal:2',
    ];

    // Status constants
    public const STATUS_APPLIED = 'applied';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    /**
     * The offer
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * The user who used the offer
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The trip where offer was applied
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_id');
    }

    /**
     * Mark as cancelled
     */
    public function markCancelled(): bool
    {
        return $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Mark as refunded
     */
    public function markRefunded(): bool
    {
        return $this->update(['status' => self::STATUS_REFUNDED]);
    }

    /**
     * Check if applied
     */
    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Scope: Applied
     */
    public function scopeApplied($query)
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    /**
     * Scope: For user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: For trip
     */
    public function scopeForTrip($query, string $tripId)
    {
        return $query->where('trip_id', $tripId);
    }
}
