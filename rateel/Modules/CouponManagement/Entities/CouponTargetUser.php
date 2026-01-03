<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Entities;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\UserManagement\Entities\User;

class CouponTargetUser extends Model
{
    use HasUuids;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'notified',
        'notified_at',
    ];

    protected $casts = [
        'notified' => 'boolean',
        'notified_at' => 'datetime',
    ];

    /**
     * The coupon this target belongs to
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * The targeted user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark as notified
     */
    public function markNotified(): bool
    {
        return $this->update([
            'notified' => true,
            'notified_at' => now(),
        ]);
    }

    /**
     * Scope: Not notified
     */
    public function scopeNotNotified($query)
    {
        return $query->where('notified', false);
    }
}
