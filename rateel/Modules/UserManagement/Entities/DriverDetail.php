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

    protected static function newFactory()
    {
        return \Modules\UserManagement\Database\factories\DriverDetailFactory::new();
    }
}
