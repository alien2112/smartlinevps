<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Entities\User;

class Coupon extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'value',
        'max_discount',
        'min_fare',
        'global_limit',
        'per_user_limit',
        'global_used_count',
        'starts_at',
        'ends_at',
        'allowed_city_ids',
        'allowed_service_types',
        'eligibility_type',
        'segment_key',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_fare' => 'decimal:2',
        'global_limit' => 'integer',
        'per_user_limit' => 'integer',
        'global_used_count' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'allowed_city_ids' => 'array',
        'allowed_service_types' => 'array',
        'is_active' => 'boolean',
    ];

    // Coupon Types
    public const TYPE_PERCENT = 'PERCENT';
    public const TYPE_FIXED = 'FIXED';
    public const TYPE_FREE_RIDE_CAP = 'FREE_RIDE_CAP';

    // Eligibility Types
    public const ELIGIBILITY_ALL = 'ALL';
    public const ELIGIBILITY_TARGETED = 'TARGETED';
    public const ELIGIBILITY_SEGMENT = 'SEGMENT';

    // Segments
    public const SEGMENT_INACTIVE_30_DAYS = 'INACTIVE_30_DAYS';
    public const SEGMENT_NEW_USER = 'NEW_USER';
    public const SEGMENT_HIGH_VALUE = 'HIGH_VALUE';

    /**
     * Creator of the coupon
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Targeted users for this coupon
     */
    public function targetUsers(): HasMany
    {
        return $this->hasMany(CouponTargetUser::class);
    }

    /**
     * All redemptions of this coupon
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Applied redemptions only
     */
    public function appliedRedemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class)->where('status', CouponRedemption::STATUS_APPLIED);
    }

    /**
     * Check if coupon is within valid date range
     */
    public function isWithinValidPeriod(): bool
    {
        $now = now();
        return $now->gte($this->starts_at) && $now->lte($this->ends_at);
    }

    /**
     * Check if global limit is reached
     */
    public function isGlobalLimitReached(): bool
    {
        if ($this->global_limit === null) {
            return false;
        }
        return $this->global_used_count >= $this->global_limit;
    }

    /**
     * Check if user has reached their limit
     */
    public function isUserLimitReached(string $userId): bool
    {
        $userUsedCount = $this->redemptions()
            ->where('user_id', $userId)
            ->whereIn('status', [CouponRedemption::STATUS_RESERVED, CouponRedemption::STATUS_APPLIED])
            ->count();

        return $userUsedCount >= $this->per_user_limit;
    }

    /**
     * Check if city is allowed
     */
    public function isCityAllowed(?string $cityId): bool
    {
        if (empty($this->allowed_city_ids)) {
            return true; // All cities allowed
        }
        return in_array($cityId, $this->allowed_city_ids, true);
    }

    /**
     * Normalize service type to handle variations
     */
    private function normalizeServiceType(?string $serviceType): ?string
    {
        if ($serviceType === null) {
            return null;
        }

        $serviceType = strtolower(trim($serviceType));

        // Map common variations to canonical forms
        $mappings = [
            'ride' => 'ride_request',
            'ride-request' => 'ride_request',
        ];

        return $mappings[$serviceType] ?? $serviceType;
    }

    /**
     * Check if service type is allowed
     */
    public function isServiceTypeAllowed(?string $serviceType): bool
    {
        if (empty($this->allowed_service_types)) {
            return true; // All service types allowed
        }

        $normalizedInput = $this->normalizeServiceType($serviceType);

        // Normalize all allowed service types and check
        foreach ($this->allowed_service_types as $allowedType) {
            if ($this->normalizeServiceType($allowedType) === $normalizedInput) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate discount for a given fare
     */
    public function calculateDiscount(float $fare): float
    {
        if ($fare < $this->min_fare) {
            return 0.0;
        }

        $discount = match ($this->type) {
            self::TYPE_PERCENT => $fare * ($this->value / 100),
            self::TYPE_FIXED => (float) $this->value,
            self::TYPE_FREE_RIDE_CAP => $fare, // 100% discount
            default => 0.0,
        };

        // Apply max discount cap if set
        if ($this->max_discount !== null && $discount > $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        // Discount cannot exceed fare
        return min($discount, $fare);
    }

    /**
     * Increment global used count atomically
     */
    public function incrementUsedCount(): bool
    {
        return $this->increment('global_used_count') > 0;
    }

    /**
     * Decrement global used count atomically
     */
    public function decrementUsedCount(): bool
    {
        if ($this->global_used_count <= 0) {
            return false;
        }
        return $this->decrement('global_used_count') > 0;
    }

    /**
     * Scope: Active coupons
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Valid (within date range)
     */
    public function scopeValid($query)
    {
        $now = now();
        return $query->where('starts_at', '<=', $now)
                     ->where('ends_at', '>=', $now);
    }

    /**
     * Scope: By code
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper(trim($code)));
    }
}
