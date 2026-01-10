<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmergencyAlert extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'user_type',
        'trip_id',
        'alert_type',
        'status',
        'latitude',
        'longitude',
        'location_address',
        'notes',
        'resolved_at',
        'resolved_by',
        'resolution_notes',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'resolved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Alert type constants
    const TYPE_PANIC = 'panic';
    const TYPE_POLICE = 'police';
    const TYPE_MEDICAL = 'medical';
    const TYPE_ACCIDENT = 'accident';
    const TYPE_HARASSMENT = 'harassment';

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_FALSE_ALARM = 'false_alarm';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the user who triggered the alert
     */
    public function user()
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'user_id');
    }

    /**
     * Get the trip associated with the alert
     */
    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_id');
    }

    /**
     * Get the admin who resolved the alert
     */
    public function resolver()
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'resolved_by');
    }

    /**
     * Scope to get active alerts
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get by alert type
     */
    public function scopeType($query, $type)
    {
        return $query->where('alert_type', $type);
    }

    /**
     * Check if alert is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Resolve the alert
     */
    public function resolve(string $notes = null, $resolvedBy = null): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
            'resolution_notes' => $notes,
        ]);
    }
}
