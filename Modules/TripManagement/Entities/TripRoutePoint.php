<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TripRoutePoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_request_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'accuracy',
        'timestamp',
        'event_type',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed' => 'decimal:2',
        'heading' => 'decimal:2',
        'accuracy' => 'decimal:2',
        'timestamp' => 'integer',
    ];

    /**
     * Get the trip request that owns this route point
     */
    public function tripRequest()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    /**
     * Scope to get points for a specific trip
     */
    public function scopeForTrip($query, $tripRequestId)
    {
        return $query->where('trip_request_id', $tripRequestId);
    }

    /**
     * Scope to get points in a time range
     */
    public function scopeBetweenTimestamps($query, $startTimestamp, $endTimestamp)
    {
        return $query->whereBetween('timestamp', [$startTimestamp, $endTimestamp]);
    }

    /**
     * Scope to get event points only
     */
    public function scopeEvents($query)
    {
        return $query->where('event_type', '!=', 'NORMAL');
    }

    /**
     * Check if this point is an event
     */
    public function isEvent()
    {
        return $this->event_type !== 'NORMAL';
    }
}
