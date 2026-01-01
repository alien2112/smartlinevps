<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserLevel;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\ZoneManagement\Entities\Zone;

class Offer extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'short_description',
        'terms_conditions',
        'image',
        'banner_image',
        'discount_type',
        'discount_amount',
        'max_discount',
        'min_trip_amount',
        'limit_per_user',
        'global_limit',
        'total_used',
        'total_discount_given',
        'start_date',
        'end_date',
        'zone_type',
        'zone_ids',
        'customer_level_type',
        'customer_level_ids',
        'customer_type',
        'customer_ids',
        'service_type',
        'vehicle_category_ids',
        'priority',
        'is_active',
        'show_in_app',
        'created_by',
    ];

    protected $casts = [
        'discount_amount' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_trip_amount' => 'decimal:2',
        'limit_per_user' => 'integer',
        'global_limit' => 'integer',
        'total_used' => 'integer',
        'total_discount_given' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'zone_ids' => 'array',
        'customer_level_ids' => 'array',
        'customer_ids' => 'array',
        'vehicle_category_ids' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'show_in_app' => 'boolean',
    ];

    // Discount Types
    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_FREE_RIDE = 'free_ride';

    // Target Types
    public const TARGET_ALL = 'all';
    public const TARGET_SELECTED = 'selected';

    /**
     * Creator of the offer
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usage records
     */
    public function usages(): HasMany
    {
        return $this->hasMany(OfferUsage::class);
    }

    /**
     * Applied usages only
     */
    public function appliedUsages(): HasMany
    {
        return $this->hasMany(OfferUsage::class)->where('status', 'applied');
    }

    /**
     * Check if offer is within valid date range
     */
    public function isWithinValidPeriod(): bool
    {
        $now = now();
        return $now->gte($this->start_date) && $now->lte($this->end_date);
    }

    /**
     * Check if offer has started
     */
    public function hasStarted(): bool
    {
        return now()->gte($this->start_date);
    }

    /**
     * Check if offer has expired
     */
    public function hasExpired(): bool
    {
        return now()->gt($this->end_date);
    }

    /**
     * Check if global limit is reached
     */
    public function isGlobalLimitReached(): bool
    {
        if ($this->global_limit === null) {
            return false;
        }
        return $this->total_used >= $this->global_limit;
    }

    /**
     * Check if user has reached their limit
     */
    public function isUserLimitReached(string $userId): bool
    {
        $userUsedCount = $this->usages()
            ->where('user_id', $userId)
            ->where('status', 'applied')
            ->count();

        return $userUsedCount >= $this->limit_per_user;
    }

    /**
     * Check if zone is allowed
     */
    public function isZoneAllowed(?string $zoneId): bool
    {
        if ($this->zone_type === self::TARGET_ALL) {
            return true;
        }
        if (empty($this->zone_ids) || !$zoneId) {
            return false;
        }
        return in_array($zoneId, $this->zone_ids, true);
    }

    /**
     * Check if customer level is allowed
     */
    public function isCustomerLevelAllowed(?string $levelId): bool
    {
        if ($this->customer_level_type === self::TARGET_ALL) {
            return true;
        }
        if (empty($this->customer_level_ids) || !$levelId) {
            return false;
        }
        return in_array($levelId, $this->customer_level_ids, true);
    }

    /**
     * Check if specific customer is allowed
     */
    public function isCustomerAllowed(string $customerId): bool
    {
        if ($this->customer_type === self::TARGET_ALL) {
            return true;
        }
        if (empty($this->customer_ids)) {
            return false;
        }
        return in_array($customerId, $this->customer_ids, true);
    }

    /**
     * Check if service/vehicle category is allowed
     */
    public function isServiceAllowed(string $tripType, ?string $vehicleCategoryId = null): bool
    {
        if ($this->service_type === self::TARGET_ALL) {
            return true;
        }
        
        // Match specific service type
        if ($this->service_type === 'ride' && $tripType === 'ride_request') {
            return true;
        }
        if ($this->service_type === 'parcel' && $tripType === 'parcel') {
            return true;
        }
        
        // Match selected vehicle categories
        if ($this->service_type === self::TARGET_SELECTED && $vehicleCategoryId) {
            if (empty($this->vehicle_category_ids)) {
                return false;
            }
            return in_array($vehicleCategoryId, $this->vehicle_category_ids, true);
        }
        
        return false;
    }

    /**
     * Calculate discount for a given fare
     */
    public function calculateDiscount(float $fare): float
    {
        if ($fare < $this->min_trip_amount) {
            return 0.0;
        }

        $discount = match ($this->discount_type) {
            self::TYPE_PERCENTAGE => $fare * ($this->discount_amount / 100),
            self::TYPE_FIXED => (float) $this->discount_amount,
            self::TYPE_FREE_RIDE => $fare, // 100% discount
            default => 0.0,
        };

        // Apply max discount cap if set
        if ($this->max_discount !== null && $discount > $this->max_discount) {
            $discount = (float) $this->max_discount;
        }

        // Discount cannot exceed fare
        return min(round($discount, 2), $fare);
    }

    /**
     * Increment usage count atomically
     */
    public function incrementUsage(float $discountAmount): bool
    {
        $this->increment('total_used');
        $this->increment('total_discount_given', $discountAmount);
        return true;
    }

    /**
     * Get remaining uses
     */
    public function getRemainingUses(): ?int
    {
        if ($this->global_limit === null) {
            return null;
        }
        return max(0, $this->global_limit - $this->total_used);
    }

    /**
     * Get status label
     */
    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        if ($this->hasExpired()) {
            return 'expired';
        }
        if (!$this->hasStarted()) {
            return 'scheduled';
        }
        if ($this->isGlobalLimitReached()) {
            return 'exhausted';
        }
        return 'active';
    }

    /**
     * Get zones for display
     */
    public function getZonesListAttribute(): array
    {
        if ($this->zone_type === self::TARGET_ALL) {
            return ['All Zones'];
        }
        if (empty($this->zone_ids)) {
            return [];
        }
        return Zone::whereIn('id', $this->zone_ids)->pluck('name')->toArray();
    }

    /**
     * Get customer levels for display
     */
    public function getCustomerLevelsListAttribute(): array
    {
        if ($this->customer_level_type === self::TARGET_ALL) {
            return ['All Levels'];
        }
        if (empty($this->customer_level_ids)) {
            return [];
        }
        return UserLevel::whereIn('id', $this->customer_level_ids)->pluck('name')->toArray();
    }

    /**
     * Get vehicle categories for display
     */
    public function getVehicleCategoriesListAttribute(): array
    {
        if ($this->service_type === self::TARGET_ALL || $this->service_type !== self::TARGET_SELECTED) {
            return [$this->service_type === self::TARGET_ALL ? 'All Services' : ucfirst($this->service_type)];
        }
        if (empty($this->vehicle_category_ids)) {
            return [];
        }
        return VehicleCategory::whereIn('id', $this->vehicle_category_ids)->pluck('name')->toArray();
    }

    /**
     * Scope: Active offers
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
        return $query->where('start_date', '<=', $now)
                     ->where('end_date', '>=', $now);
    }

    /**
     * Scope: Show in app
     */
    public function scopeShowInApp($query)
    {
        return $query->where('show_in_app', true);
    }

    /**
     * Scope: Order by priority
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
