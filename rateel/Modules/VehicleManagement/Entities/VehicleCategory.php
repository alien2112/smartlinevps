<?php

namespace Modules\VehicleManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AdminModule\Entities\ActivityLog;
use Modules\FareManagement\Entities\TripFare;

class VehicleCategory extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    // Category level constants for dispatch priority
    // Level determines hierarchy: higher levels can accept lower level rides
    public const LEVEL_SCOOTER = 1;  // Scooter/Motorcycle - basic tier
    public const LEVEL_BUDGET = 1;   // Budget cars - basic tier (same as scooter)
    public const LEVEL_TAXI = 2;     // Standard taxi - mid tier
    public const LEVEL_PRO = 2;      // Pro cars - mid tier (same as taxi)
    public const LEVEL_VIP = 3;      // VIP - premium tier

    // Vehicle type constants
    public const TYPE_CAR = 'car';
    public const TYPE_MOTORCYCLE = 'motorcycle';
    public const TYPE_SCOOTER = 'scooter';
    public const TYPE_TAXI = 'taxi';

    protected $fillable = [
        'name',
        'description',
        'image',
        'type',
        'category_level',
        'is_active',
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'category_level' => 'integer',
    ];

    /**
     * Check if this category can accept rides of the given level
     * Higher level categories can accept lower level rides
     */
    public function canAcceptLevel(int $requestedLevel): bool
    {
        return $this->category_level >= $requestedLevel;
    }

    /**
     * Check if this is a VIP category
     */
    public function isVip(): bool
    {
        return $this->category_level === self::LEVEL_VIP;
    }

    /**
     * Check if this is a Pro category
     */
    public function isPro(): bool
    {
        return $this->category_level === self::LEVEL_PRO;
    }

    /**
     * Check if this is a Budget category
     */
    public function isBudget(): bool
    {
        return $this->category_level === self::LEVEL_BUDGET;
    }

    /**
     * Get category level name based on type and level
     */
    public function getLevelNameAttribute(): string
    {
        // For motorcycles/scooters, use scooter naming
        if (in_array($this->type, [self::TYPE_MOTORCYCLE, self::TYPE_SCOOTER])) {
            return match($this->category_level) {
                self::LEVEL_VIP => 'VIP Scooter',
                self::LEVEL_PRO => 'Pro Scooter',
                default => 'Scooter',
            };
        }

        // For cars, use car naming
        return match($this->category_level) {
            self::LEVEL_VIP => 'VIP',
            self::LEVEL_PRO, self::LEVEL_TAXI => 'Taxi',
            default => 'Budget',
        };
    }

    /**
     * Check if this is a Scooter category
     */
    public function isScooter(): bool
    {
        return in_array($this->type, [self::TYPE_MOTORCYCLE, self::TYPE_SCOOTER])
            && $this->category_level === self::LEVEL_SCOOTER;
    }

    /**
     * Check if this is a Taxi category
     */
    public function isTaxi(): bool
    {
        return $this->type === self::TYPE_CAR && $this->category_level === self::LEVEL_TAXI;
    }

    /**
     * Get all available category types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_CAR => 'Car',
            self::TYPE_MOTORCYCLE => 'Motorcycle',
            self::TYPE_SCOOTER => 'Scooter',
            self::TYPE_TAXI => 'Taxi',
        ];
    }

    /**
     * Get all available category levels
     */
    public static function getLevels(): array
    {
        return [
            self::LEVEL_BUDGET => 'Budget / Scooter',
            self::LEVEL_PRO => 'Pro / Taxi',
            self::LEVEL_VIP => 'VIP',
        ];
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'category_id');
    }

    public function tripFares()
    {
        return $this->hasMany(TripFare::class, 'vehicle_category_id');
    }

    public function logs ()
    {
        return $this->morphMany(ActivityLog::class, 'logable');
    }

    protected function scopeOfStatus($query, $status=1)
    {
        $query->where('is_active', $status);
    }

    protected static function newFactory()
    {
        return \Modules\VehicleManagement\Database\factories\VehicleCategoryFactory::new();
    }

    protected static function boot()
    {
        parent::boot();

        static::updated(function($item) {
            $array = [];
            foreach ($item->changes as $key => $change){
                $array[$key] = $item->original[$key];
            }
            if(!empty($array)) {
                $log = new ActivityLog();
                $log->edited_by = auth()->user()->id ?? 'user_update';
                $log->before = $array;
                $log->after = $item->changes;
                $item->logs()->save($log);
            }
        });

        static::deleted(function($item) {
            $log = new ActivityLog();
            $log->edited_by = auth()->user()->id;
            $log->before = $item->original;
            $item->logs()->save($log);
        });

    }

}
