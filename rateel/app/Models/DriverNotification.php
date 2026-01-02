<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverNotification extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'driver_id',
        'type',
        'title',
        'message',
        'data',
        'action_type',
        'action_url',
        'is_read',
        'read_at',
        'priority',
        'category',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    // Notification types
    const TYPE_TRIP_REQUEST = 'trip_request';
    const TYPE_TRIP_ACCEPTED = 'trip_accepted';
    const TYPE_TRIP_STARTED = 'trip_started';
    const TYPE_TRIP_COMPLETED = 'trip_completed';
    const TYPE_TRIP_CANCELLED = 'trip_cancelled';
    const TYPE_PAYMENT_RECEIVED = 'payment_received';
    const TYPE_WITHDRAWAL_APPROVED = 'withdrawal_approved';
    const TYPE_WITHDRAWAL_REJECTED = 'withdrawal_rejected';
    const TYPE_DOCUMENT_VERIFIED = 'document_verified';
    const TYPE_DOCUMENT_REJECTED = 'document_rejected';
    const TYPE_LEVEL_UP = 'level_up';
    const TYPE_ACHIEVEMENT_UNLOCKED = 'achievement_unlocked';
    const TYPE_PROMOTION = 'promotion';
    const TYPE_SYSTEM_ANNOUNCEMENT = 'system_announcement';
    const TYPE_ACCOUNT_UPDATE = 'account_update';

    // Priority levels
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // Categories
    const CATEGORY_TRIPS = 'trips';
    const CATEGORY_EARNINGS = 'earnings';
    const CATEGORY_PROMOTIONS = 'promotions';
    const CATEGORY_SYSTEM = 'system';
    const CATEGORY_DOCUMENTS = 'documents';

    /**
     * Relationships
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    /**
     * Scopes
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Helper methods
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        return $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function markAsUnread(): bool
    {
        return $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Static helper to create notification
     */
    public static function notify(
        string $driverId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        string $priority = self::PRIORITY_NORMAL,
        ?string $category = null,
        ?string $actionType = null,
        ?string $actionUrl = null
    ): self {
        return self::create([
            'driver_id' => $driverId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'category' => $category,
            'action_type' => $actionType,
            'action_url' => $actionUrl,
        ]);
    }
}
