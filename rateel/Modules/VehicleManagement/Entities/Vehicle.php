<?php

namespace Modules\VehicleManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\AdminModule\Entities\ActivityLog;
use Modules\FareManagement\Entities\TripFare;
use Modules\UserManagement\Entities\User;

class Vehicle extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'ref_id',
        'brand_id',
        'model_id',
        'category_id',
        'licence_plate_number',
        'licence_expire_date',
        'vin_number',
        'transmission',
        'parcel_weight_capacity',
        'fuel_type',
        'ownership',
        'driver_id',
        'documents',
        'is_active',
        'is_primary',
        'has_pending_primary_request',
        'deleted_at',
        'created_at',
        'updated_at',
        'vehicle_request_status',
        'deny_note',
        'draft',
        'travel_type_requested',
        'is_travel_enabled'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'licence_expire_date' => 'date',
        'documents' => 'array',
        'is_active' => 'boolean',
        'is_primary' => 'boolean',
        'has_pending_primary_request' => 'boolean',
        'vehicle_request_status' => 'string',
        'draft' => 'array',
        'travel_type_requested' => 'boolean',
        'is_travel_enabled' => 'boolean',
    ];

    /**
     * Get categories as array (handles both JSON array and single UUID string)
     * Note: Use 'categories' attribute, not 'category_id' to avoid interfering with relationships
     */
    public function getCategoriesAttribute()
    {
        $value = $this->getAttributeFromArray('category_id');

        if (is_null($value)) {
            return [];
        }

        // Try to decode as JSON first
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // If it's a single UUID string, wrap it in an array
        if (is_string($value) && !empty($value)) {
            return [$value];
        }

        return [];
    }

    protected static function newFactory()
    {
        return \Modules\VehicleManagement\Database\factories\VehicleFactory::new();
    }

    protected function scopeOfStatus($query, $status=1)
    {
        $query->where('is_active', $status);
    }

    /**
     * Scope to get primary vehicle for a driver
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to get active vehicles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    public function brand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class, 'brand_id');
    }

    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id');
    }

    public function category()
    {
        return $this->belongsTo(VehicleCategory::class, 'category_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function tripFares()
    {
        return $this->hasMany(TripFare::class, 'vehicle_category_id');
    }

    public function logs ()
    {
        return $this->morphMany(ActivityLog::class, 'logable');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item){
            $item->ref_id = (static::withTrashed()->max('ref_id') ?? 100000) + 1;
            
            // If this is the first vehicle for the driver, set it as primary
            if ($item->driver_id && !static::where('driver_id', $item->driver_id)->exists()) {
                $item->is_primary = true;
            }
        });

        // When setting a vehicle as primary, unset others
        static::updating(function ($item) {
            if ($item->isDirty('is_primary') && $item->is_primary) {
                static::where('driver_id', $item->driver_id)
                    ->where('id', '!=', $item->id)
                    ->update(['is_primary' => false]);
            }
        });

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
