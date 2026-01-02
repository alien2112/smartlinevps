<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'driver_id',
        'trip_requests_enabled',
        'trip_updates_enabled',
        'payment_notifications_enabled',
        'promotional_notifications_enabled',
        'system_notifications_enabled',
        'email_notifications_enabled',
        'sms_notifications_enabled',
        'push_notifications_enabled',
        'quiet_hours_start',
        'quiet_hours_end',
        'quiet_hours_enabled',
    ];

    protected $casts = [
        'trip_requests_enabled' => 'boolean',
        'trip_updates_enabled' => 'boolean',
        'payment_notifications_enabled' => 'boolean',
        'promotional_notifications_enabled' => 'boolean',
        'system_notifications_enabled' => 'boolean',
        'email_notifications_enabled' => 'boolean',
        'sms_notifications_enabled' => 'boolean',
        'push_notifications_enabled' => 'boolean',
        'quiet_hours_enabled' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    /**
     * Helper methods
     */
    public function isInQuietHours(): bool
    {
        if (!$this->quiet_hours_enabled || !$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = now()->format('H:i');
        $start = $this->quiet_hours_start;
        $end = $this->quiet_hours_end;

        // Handle cases where quiet hours span midnight
        if ($start < $end) {
            return $now >= $start && $now < $end;
        } else {
            return $now >= $start || $now < $end;
        }
    }

    public function canReceiveNotification(string $category): bool
    {
        if ($this->isInQuietHours()) {
            return false;
        }

        return match ($category) {
            'trips' => $this->trip_requests_enabled && $this->trip_updates_enabled,
            'earnings' => $this->payment_notifications_enabled,
            'promotions' => $this->promotional_notifications_enabled,
            'system' => $this->system_notifications_enabled,
            default => true,
        };
    }

    /**
     * Get or create settings for driver
     */
    public static function getOrCreateForDriver(string $driverId): self
    {
        return self::firstOrCreate(
            ['driver_id' => $driverId],
            [
                'trip_requests_enabled' => true,
                'trip_updates_enabled' => true,
                'payment_notifications_enabled' => true,
                'promotional_notifications_enabled' => true,
                'system_notifications_enabled' => true,
                'push_notifications_enabled' => true,
            ]
        );
    }
}
