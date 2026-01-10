<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripMonitoring extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trip_monitoring';

    protected $fillable = [
        'trip_id',
        'driver_id',
        'is_enabled',
        'auto_alert_enabled',
        'alert_delay_minutes',
        'monitoring_started_at',
        'monitoring_ended_at',
        'last_location_update',
        'last_latitude',
        'last_longitude',
        'alert_triggered',
        'alert_triggered_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'auto_alert_enabled' => 'boolean',
        'alert_delay_minutes' => 'integer',
        'monitoring_started_at' => 'datetime',
        'monitoring_ended_at' => 'datetime',
        'last_location_update' => 'datetime',
        'last_latitude' => 'decimal:8',
        'last_longitude' => 'decimal:8',
        'alert_triggered' => 'boolean',
        'alert_triggered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the trip being monitored
     */
    public function trip()
    {
        return $this->belongsTo(TripRequest::class, 'trip_id');
    }

    /**
     * Get the driver
     */
    public function driver()
    {
        return $this->belongsTo(\Modules\UserManagement\Entities\User::class, 'driver_id');
    }

    /**
     * Scope to get enabled monitoring
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Check if monitoring is active
     */
    public function isActive(): bool
    {
        return $this->is_enabled && !$this->monitoring_ended_at;
    }

    /**
     * Update location
     */
    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->update([
            'last_latitude' => $latitude,
            'last_longitude' => $longitude,
            'last_location_update' => now(),
        ]);
    }

    /**
     * Trigger alert
     */
    public function triggerAlert(): void
    {
        $this->update([
            'alert_triggered' => true,
            'alert_triggered_at' => now(),
        ]);
    }
}
