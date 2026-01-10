<?php

namespace Modules\UserManagement\Entities;

use App\Enums\DriverStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DriverDetail extends Model
{
    use HasFactory;

    // VIP abuse prevention: max low-category trips before deprioritization
    public const LOW_CATEGORY_TRIP_LIMIT = 5;

    // Travel approval status constants
    public const TRAVEL_STATUS_NONE = 'none';
    public const TRAVEL_STATUS_REQUESTED = 'requested';
    public const TRAVEL_STATUS_APPROVED = 'approved';
    public const TRAVEL_STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'is_online',
        'availability_status',
        'online',
        'offline',
        'online_time',
        'accepted',
        'completed',
        'start_driving',
        'on_driving_time',
        'idle_time',
        'service',
        'ride_count',
        'parcel_count',
        'service',
        // VIP abuse prevention
        'low_category_trips_today',
        'low_category_trips_date',
        // Travel approval system
        'travel_status',
        'travel_requested_at',
        'travel_approved_at',
        'travel_rejected_at',
        'travel_processed_by',
        'travel_rejection_reason',
        // Destination preferences
        'destination_preferences',
        'destination_filter_enabled',
        'destination_radius_km',
        // Honeycomb preference
        'honeycomb_enabled',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'online_time' => 'double',
        'on_driving_time' => 'double',
        'idle_time' => 'double',
        'service' => 'array',
        'parcel_count' => 'integer',
        'ride_count' => 'integer',
        'low_category_trips_today' => 'integer',
        'low_category_trips_date' => 'date',
        // Travel approval casts
        'travel_requested_at' => 'datetime',
        'travel_approved_at' => 'datetime',
        'travel_rejected_at' => 'datetime',
        // Destination preferences casts
        'destination_preferences' => 'array',
        'destination_filter_enabled' => 'boolean',
        'destination_radius_km' => 'decimal:2',
        // Honeycomb preference cast
        'honeycomb_enabled' => 'boolean',
    ];

    /**
     * Relationship to User (Driver)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Increment the low-category trip counter for today
     * Used when a VIP/Pro driver accepts a lower-tier trip
     */
    public function incrementLowCategoryTrip(): void
    {
        $today = now()->toDateString();

        // Reset counter if it's a new day
        if ($this->low_category_trips_date !== $today) {
            $this->low_category_trips_today = 0;
            $this->low_category_trips_date = $today;
        }

        $this->low_category_trips_today++;
        $this->save();
    }

    /**
     * Check if driver should be deprioritized for low-category trips
     * Returns true if driver has taken too many low-category trips today
     */
    public function shouldDeprioritize(): bool
    {
        $today = now()->toDateString();

        // If date doesn't match today, counter is stale (effectively 0)
        if ($this->low_category_trips_date !== $today) {
            return false;
        }

        return $this->low_category_trips_today >= self::LOW_CATEGORY_TRIP_LIMIT;
    }

    /**
     * Get remaining low-category trips allowed today
     */
    public function getRemainingLowCategoryTrips(): int
    {
        $today = now()->toDateString();

        if ($this->low_category_trips_date !== $today) {
            return self::LOW_CATEGORY_TRIP_LIMIT;
        }

        return max(0, self::LOW_CATEGORY_TRIP_LIMIT - $this->low_category_trips_today);
    }

    /**
     * Reset daily counter (called by scheduled command)
     */
    public function resetDailyCounter(): void
    {
        $this->low_category_trips_today = 0;
        $this->low_category_trips_date = now()->toDateString();
        $this->save();
    }

    // ============================================
    // TRAVEL APPROVAL SYSTEM METHODS
    // ============================================

    /**
     * Request travel privilege
     * Driver must be VIP category to request
     */
    public function requestTravelPrivilege(): bool
    {
        if ($this->travel_status === self::TRAVEL_STATUS_APPROVED) {
            return false; // Already approved
        }

        $this->travel_status = self::TRAVEL_STATUS_REQUESTED;
        $this->travel_requested_at = now();
        $this->travel_rejected_at = null;
        $this->travel_rejection_reason = null;
        return $this->save();
    }

    /**
     * Approve travel privilege (admin action)
     */
    public function approveTravelPrivilege(int $adminId): bool
    {
        $this->travel_status = self::TRAVEL_STATUS_APPROVED;
        $this->travel_approved_at = now();
        $this->travel_processed_by = $adminId;
        $this->travel_rejected_at = null;
        $this->travel_rejection_reason = null;
        return $this->save();
    }

    /**
     * Reject travel privilege (admin action)
     */
    public function rejectTravelPrivilege(int $adminId, ?string $reason = null): bool
    {
        $this->travel_status = self::TRAVEL_STATUS_REJECTED;
        $this->travel_rejected_at = now();
        $this->travel_processed_by = $adminId;
        $this->travel_rejection_reason = $reason;
        return $this->save();
    }

    /**
     * Revoke travel privilege (admin action)
     */
    public function revokeTravelPrivilege(int $adminId, ?string $reason = null): bool
    {
        $this->travel_status = self::TRAVEL_STATUS_NONE;
        $this->travel_approved_at = null;
        $this->travel_rejected_at = now();
        $this->travel_processed_by = $adminId;
        $this->travel_rejection_reason = $reason;
        return $this->save();
    }

    /**
     * Check if driver is approved for travel bookings
     */
    public function isTravelApproved(): bool
    {
        return $this->travel_status === self::TRAVEL_STATUS_APPROVED;
    }

    /**
     * Check if driver has pending travel request
     */
    public function hasPendingTravelRequest(): bool
    {
        return $this->travel_status === self::TRAVEL_STATUS_REQUESTED;
    }

    /**
     * Check if driver can request travel (not already requested/approved)
     */
    public function canRequestTravel(): bool
    {
        return in_array($this->travel_status, [
            self::TRAVEL_STATUS_NONE,
            self::TRAVEL_STATUS_REJECTED,
        ]);
    }

    /**
     * Get travel status for API response
     */
    public function getTravelStatusInfo(): array
    {
        return [
            'travel_status' => $this->travel_status ?? self::TRAVEL_STATUS_NONE,
            'travel_enabled' => $this->isTravelApproved(),
            'travel_requested_at' => $this->travel_requested_at?->toISOString(),
            'travel_approved_at' => $this->travel_approved_at?->toISOString(),
            'travel_rejected_at' => $this->travel_rejected_at?->toISOString(),
            'travel_rejection_reason' => $this->travel_rejection_reason,
            'can_request_travel' => $this->canRequestTravel(),
        ];
    }

    // ============================================
    // DESTINATION PREFERENCE METHODS
    // ============================================

    /**
     * Add or update a destination preference
     *
     * @param array $destination ['latitude', 'longitude', 'address', 'radius_km']
     * @param int|null $id Destination ID to update (1-3), null to add new
     * @return bool
     */
    public function setDestinationPreference(array $destination, ?int $id = null): bool
    {
        $preferences = $this->destination_preferences ?? [];

        // Validate max 3 destinations
        if ($id === null && count($preferences) >= 3) {
            return false;
        }

        // Prepare destination data
        $destinationData = [
            'id' => $id ?? (count($preferences) + 1),
            'latitude' => (float) $destination['latitude'],
            'longitude' => (float) $destination['longitude'],
            'address' => $destination['address'] ?? null,
            'radius_km' => $destination['radius_km'] ?? $this->destination_radius_km ?? 5.0,
            'created_at' => $destination['created_at'] ?? now()->toISOString(),
        ];

        if ($id !== null) {
            // Update existing
            $preferences = collect($preferences)->map(function ($pref) use ($id, $destinationData) {
                return $pref['id'] === $id ? $destinationData : $pref;
            })->toArray();
        } else {
            // Add new
            $preferences[] = $destinationData;
        }

        $this->destination_preferences = $preferences;
        return $this->save();
    }

    /**
     * Remove a destination preference by ID
     *
     * @param int $id Destination ID (1-3)
     * @return bool
     */
    public function removeDestinationPreference(int $id): bool
    {
        $preferences = collect($this->destination_preferences ?? [])
            ->reject(fn($pref) => $pref['id'] === $id)
            ->values()
            ->toArray();

        $this->destination_preferences = $preferences;
        return $this->save();
    }

    /**
     * Toggle destination filter on/off
     *
     * @return bool
     */
    public function toggleDestinationFilter(): bool
    {
        $this->destination_filter_enabled = !$this->destination_filter_enabled;
        return $this->save();
    }

    /**
     * Set destination filter status explicitly
     *
     * @param bool $enabled
     * @return bool
     */
    public function setDestinationFilter(bool $enabled): bool
    {
        $this->destination_filter_enabled = $enabled;
        return $this->save();
    }

    /**
     * Update global destination radius
     *
     * @param float $radiusKm Radius in kilometers (1-15)
     * @return bool
     */
    public function setDestinationRadius(float $radiusKm): bool
    {
        if ($radiusKm < 1 || $radiusKm > 15) {
            return false;
        }

        $this->destination_radius_km = $radiusKm;
        return $this->save();
    }

    /**
     * Get destination preferences for API response
     *
     * @return array
     */
    public function getDestinationPreferencesInfo(): array
    {
        return [
            'destinations' => $this->destination_preferences ?? [],
            'filter_enabled' => $this->destination_filter_enabled ?? false,
            'default_radius_km' => (float) ($this->destination_radius_km ?? 5.0),
            'max_destinations' => 3,
            'min_radius_km' => 1.0,
            'max_radius_km' => 15.0,
            'can_add_more' => count($this->destination_preferences ?? []) < 3,
        ];
    }

    /**
     * Check if a trip destination is within any preferred destinations
     *
     * @param float $destLat Trip destination latitude
     * @param float $destLng Trip destination longitude
     * @return bool True if destination matches any preference
     */
    public function matchesDestinationPreference(float $destLat, float $destLng): bool
    {
        // If filter is disabled, all destinations match
        if (!$this->destination_filter_enabled) {
            return true;
        }

        $preferences = $this->destination_preferences ?? [];

        // If no preferences set, all destinations match
        if (empty($preferences)) {
            return true;
        }

        // Check if trip destination is within radius of ANY preferred destination
        foreach ($preferences as $pref) {
            $distance = haversineDistance(
                $pref['latitude'],
                $pref['longitude'],
                $destLat,
                $destLng
            );

            $radiusMeters = ($pref['radius_km'] ?? $this->destination_radius_km ?? 5.0) * 1000;

            if ($distance <= $radiusMeters) {
                return true;
            }
        }

        return false;
    }

    // HONEYCOMB PREFERENCE METHODS

    /**
     * Toggle honeycomb dispatch on/off for this driver
     *
     * @return bool Success status
     */
    public function toggleHoneycomb(): bool
    {
        $this->honeycomb_enabled = !$this->honeycomb_enabled;
        return $this->save();
    }

    /**
     * Set honeycomb dispatch status explicitly
     *
     * @param bool $enabled
     * @return bool Success status
     */
    public function setHoneycomb(bool $enabled): bool
    {
        $this->honeycomb_enabled = $enabled;
        return $this->save();
    }

    /**
     * Get honeycomb preference info for API response
     *
     * @return array
     */
    public function getHoneycombInfo(): array
    {
        return [
            'honeycomb_enabled' => $this->honeycomb_enabled ?? true,
            'description' => $this->honeycomb_enabled 
                ? 'Honeycomb dispatch is enabled - you will be matched using optimized zone-based dispatch'
                : 'Honeycomb dispatch is disabled - you will be matched using standard distance-based dispatch',
        ];
    }

    protected static function newFactory()
    {
        return \Modules\UserManagement\Database\factories\DriverDetailFactory::new();
    }
}
