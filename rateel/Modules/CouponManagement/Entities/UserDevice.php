<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

class UserDevice extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'fcm_token',
        'platform',
        'device_id',
        'device_model',
        'app_version',
        'is_active',
        'last_used_at',
        'failure_count',
        'deactivated_at',
        'deactivation_reason',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'failure_count' => 'integer',
        'deactivated_at' => 'datetime',
    ];

    // Platforms
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_WEB = 'web';

    // Max failures before deactivation
    public const MAX_FAILURE_COUNT = 3;

    /**
     * The user this device belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark token as used (update last_used_at)
     */
    public function markUsed(): bool
    {
        return $this->update([
            'last_used_at' => now(),
            'failure_count' => 0, // Reset failure count on successful use
        ]);
    }

    /**
     * Record a failure
     */
    public function recordFailure(?string $reason = null): bool
    {
        $this->increment('failure_count');

        if ($this->failure_count >= self::MAX_FAILURE_COUNT) {
            return $this->deactivate($reason ?? 'max_failures_reached');
        }

        return true;
    }

    /**
     * Deactivate the device token
     */
    public function deactivate(string $reason): bool
    {
        return $this->update([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivation_reason' => $reason,
        ]);
    }

    /**
     * Reactivate the device token
     */
    public function reactivate(): bool
    {
        return $this->update([
            'is_active' => true,
            'failure_count' => 0,
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ]);
    }

    /**
     * Scope: Active devices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By platform
     */
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope: For user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
