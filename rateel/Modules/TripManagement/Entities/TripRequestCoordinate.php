<?php

namespace Modules\TripManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class TripRequestCoordinate extends Model
{
    use HasFactory, HasSpatial;

    protected $fillable = [
        'trip_request_id',
        'pickup_coordinates',
        'pickup_address',
        'destination_coordinates',
        'is_reached_destination',
        'destination_address',
        'intermediate_coordinates',
        'int_coordinate_1',
        'is_reached_1',
        'int_coordinate_2',
        'is_reached_2',
        'intermediate_addresses',
        'start_coordinates',
        'drop_coordinates',
        'driver_accept_coordinates',
        'customer_request_coordinates',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'pickup_coordinates' => Point::class,
        'destination_coordinates' => Point::class,
        'start_coordinates' => Point::class,
        'drop_coordinates' => Point::class,
        'driver_accept_coordinates' => Point::class,
        'customer_request_coordinates' => Point::class,
        'int_coordinate_1' => Point::class,
        'int_coordinate_2' => Point::class,
        'intermediate_coordinates' => 'array',
        'intermediate_addresses' => 'array',
        'is_reached_destination' => 'boolean',
        'is_reached_1' => 'boolean',
        'is_reached_2' => 'boolean'
    ];

    public function tripRequest()
    {
        return $this->belongsTo(TripRequest::class, 'trip_request_id');
    }

    /**
     * Get latitude from a Point coordinate
     * 
     * Eloquent Spatial correctly returns latitude from the latitude property.
     * For Alexandria, Egypt: latitude ~31°N
     *
     * @param Point|null $point
     * @return float|null
     */
    public function getCorrectLatitude(?Point $point): ?float
    {
        return $point?->latitude;
    }

    /**
     * Get longitude from a Point coordinate
     *
     * Eloquent Spatial correctly returns longitude from the longitude property.
     * For Alexandria, Egypt: longitude ~29-30°E
     *
     * @param Point|null $point
     * @return float|null
     */
    public function getCorrectLongitude(?Point $point): ?float
    {
        return $point?->longitude;
    }

    /**
     * Get pickup coordinates as [lat, lng] array with correct values
     */
    public function getPickupLatLng(): array
    {
        return [
            $this->getCorrectLatitude($this->pickup_coordinates),
            $this->getCorrectLongitude($this->pickup_coordinates),
        ];
    }

    /**
     * Get destination coordinates as [lat, lng] array with correct values
     */
    public function getDestinationLatLng(): array
    {
        return [
            $this->getCorrectLatitude($this->destination_coordinates),
            $this->getCorrectLongitude($this->destination_coordinates),
        ];
    }

    /**
     * Get drop coordinates as [lat, lng] array with correct values
     */
    public function getDropLatLng(): ?array
    {
        if (!$this->drop_coordinates) {
            return null;
        }
        return [
            $this->getCorrectLatitude($this->drop_coordinates),
            $this->getCorrectLongitude($this->drop_coordinates),
        ];
    }

    /**
     * Get start coordinates as [lat, lng] array with correct values
     */
    public function getStartLatLng(): ?array
    {
        if (!$this->start_coordinates) {
            return null;
        }
        return [
            $this->getCorrectLatitude($this->start_coordinates),
            $this->getCorrectLongitude($this->start_coordinates),
        ];
    }

    public function scopeDistanceSphere($query, $column, $location, $distance)
    {
        // Eloquent Spatial correctly stores coordinates:
        // - Point(lat, lng) constructor stores WKT as POINT(lng lat) in MySQL
        // - MySQL's POINT(X, Y) format expects X=longitude, Y=latitude for geographic data
        // - ST_Distance_Sphere expects POINT(lng, lat) which is what we have in the database
        //
        // The $location object (typically UserLastLocation) has latitude/longitude columns
        // We create the comparison POINT in the same (lng, lat) format as stored data
        // Use SRID 0 to match the stored coordinates
        return $query->whereRaw("ST_Distance_Sphere($column, ST_GeomFromText('POINT($location->longitude $location->latitude)', 0)) < $distance");
    }

    protected static function newFactory()
    {
        return \Modules\TripManagement\Database\factories\TripRequestCoordinateFactory::new();
    }
}
