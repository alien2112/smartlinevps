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
    ];

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

    protected static function newFactory()
    {
        return \Modules\UserManagement\Database\factories\DriverDetailFactory::new();
    }
}
