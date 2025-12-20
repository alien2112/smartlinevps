<?php

namespace Modules\TripManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\UserManagement\Entities\User;

class LostItem extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'trip_request_id',
        'customer_id',
        'driver_id',
        'category',
        'description',
        'image_url',
        'status',
        'driver_response',
        'driver_notes',
        'admin_notes',
        'contact_preference',
        'item_lost_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'item_lost_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_DRIVER_CONTACTED = 'driver_contacted';
    const STATUS_FOUND = 'found';
    const STATUS_RETURNED = 'returned';
    const STATUS_CLOSED = 'closed';

    /**
     * Category constants
     */
    const CATEGORY_PHONE = 'phone';
    const CATEGORY_WALLET = 'wallet';
    const CATEGORY_BAG = 'bag';
    const CATEGORY_KEYS = 'keys';
    const CATEGORY_OTHER = 'other';

    /**
     * Get all valid statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_DRIVER_CONTACTED,
            self::STATUS_FOUND,
            self::STATUS_RETURNED,
            self::STATUS_CLOSED,
        ];
    }

    /**
     * Get all valid categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_PHONE,
            self::CATEGORY_WALLET,
            self::CATEGORY_BAG,
            self::CATEGORY_KEYS,
            self::CATEGORY_OTHER,
        ];
    }

    /**
     * Get the trip associated with this lost item.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    /**
     * Get the customer who reported the lost item.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Get the driver of the trip.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Get the status change logs.
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(LostItemStatusLog::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the image URL attribute with full path.
     */
    public function getImageUrlAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        return asset('storage/lost-items/' . $value);
    }

    /**
     * Scope for filtering by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }
}
