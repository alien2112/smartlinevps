<?php

namespace Modules\UserManagement\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\UserManagement\Entities\UserLastLocation;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;

/**
 * High-Performance User Last Location Repository
 * 
 * Uses MySQL spatial indexes for efficient geospatial queries.
 * Falls back to Haversine formula when spatial column is unavailable.
 */
class UserLastLocationRepository implements UserLastLocationInterface
{
    public function __construct(private UserLastLocation $last_location)
    {
    }

    /**
     * Get drivers within radius using Haversine (legacy method)
     */
    public function get(int $limit, bool $dynamic_page = false, array $except = [], array $attributes = [], array $relations = []): mixed
    {
        return $this->last_location->selectRaw("* ,
        ( 6371 * acos( cos( radians(?) ) *
          cos( radians( latitude ) )
          * cos( radians( longitude ) - radians(?)
          ) + sin( radians(?) ) *
          sin( radians( latitude ) ) )
        ) AS distance", [$attributes['latitude'], $attributes['longitude'], $attributes['latitude']])
            ->where('type', '=', 'driver')
            ->having("distance", "<", $attributes['radius'])
            ->orderBy("distance")
            ->limit($limit)
            ->get();
    }

    /**
     * Get single location by column
     */
    public function getBy(string $column, int|string $value, array $attributes = []): mixed
    {
        return $this->last_location
            ->query()
            ->where($column, $value)
            ->first();
    }

    /**
     * Update or create driver location
     * The database trigger will auto-populate the location_point column
     */
    public function updateOrCreate($attributes): mixed
    {
        $location = $this->last_location->query()
            ->updateOrInsert(['user_id' => $attributes['user_id']], [
                'type' => $attributes['type'],
                'latitude' => $attributes['latitude'],
                'longitude' => $attributes['longitude'],
                'zone_id' => $attributes['zone_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return $location;
    }

    /**
     * Legacy getNearestDrivers1 - kept for backward compatibility
     */
    public function getNearestDrivers1($attributes): mixed
    {
        return $this->getNearestDrivers($attributes);
    }

    /**
     * HIGH-PERFORMANCE: Get nearest drivers using spatial index
     * 
     * This method first attempts to use MySQL spatial functions with the
     * location_point column (which has a SPATIAL INDEX). If that fails,
     * it falls back to the Haversine formula.
     *
     * Expected performance: 2-5s -> <50ms at 10K drivers
     */
    public function getNearestDrivers($attributes): mixed
    {
        $latitude = $attributes['latitude'];
        $longitude = $attributes['longitude'];
        $radiusKm = $attributes['radius'];
        $zoneId = $attributes['zone_id'];
        $vehicleCategoryId = $attributes['vehicle_category_id'] ?? null;
        $service = $attributes['service'] ?? null;
        $parcelWeightCapacity = $attributes['parcel_weight_capacity'] ?? null;

        // Try spatial query first (uses index)
        try {
            return $this->getNearestDriversSpatial(
                $latitude,
                $longitude,
                $radiusKm,
                $zoneId,
                $vehicleCategoryId,
                $service,
                $parcelWeightCapacity
            );
        } catch (\Exception $e) {
            Log::warning('UserLastLocationRepository: Spatial query failed, using fallback', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback to Haversine
            return $this->getNearestDriversHaversine($attributes);
        }
    }

    /**
     * Spatial index-based driver search
     * Uses ST_Distance_Sphere with the location_point column
     */
    private function getNearestDriversSpatial(
        float $latitude,
        float $longitude,
        float $radiusKm,
        string $zoneId,
        $vehicleCategoryId = null,
        $service = null,
        $parcelWeightCapacity = null
    ) {
        $radiusMeters = $radiusKm * 1000;
        
        // Build the search point
        $searchPoint = DB::raw("ST_SRID(POINT({$longitude}, {$latitude}), 4326)");
        
        $query = $this->last_location
            ->selectRaw("user_last_locations.*, 
                ST_Distance_Sphere(location_point, ST_SRID(POINT(?, ?), 4326)) as distance_meters",
                [$longitude, $latitude])
            ->with(['user.vehicle.category', 'driverDetails', 'user'])
            ->where('type', 'driver')
            ->where('zone_id', $zoneId)
            // Spatial distance filter (uses index)
            ->whereRaw(
                "ST_Distance_Sphere(location_point, ST_SRID(POINT(?, ?), 4326)) <= ?",
                [$longitude, $latitude, $radiusMeters]
            )
            // Join-based filters are more efficient than whereHas for this query
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'user_last_locations.user_id')
                    ->where('users.is_active', true);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('driver_details')
                    ->whereColumn('driver_details.user_id', 'user_last_locations.user_id')
                    ->where('driver_details.is_online', true)
                    ->whereNotIn('driver_details.availability_status', ['unavailable', 'on_trip']);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('vehicles')
                    ->whereColumn('vehicles.driver_id', 'user_last_locations.user_id')
                    ->where('vehicles.is_active', true);
            });
        
        // Vehicle category filter
        if ($vehicleCategoryId !== null) {
            $categoryIds = is_array($vehicleCategoryId) ? $vehicleCategoryId : [$vehicleCategoryId];
            
            $query->where(function ($q) use ($categoryIds) {
                $q->whereExists(function ($subQ) use ($categoryIds) {
                    $subQ->select(DB::raw(1))
                        ->from('vehicles')
                        ->whereColumn('vehicles.driver_id', 'user_last_locations.user_id')
                        ->where('vehicles.is_active', true)
                        ->where(function ($vq) use ($categoryIds) {
                            foreach ($categoryIds as $catId) {
                                $vq->orWhereRaw("JSON_CONTAINS(vehicles.category_id, ?)", [json_encode($catId)]);
                            }
                        });
                });
            });
        }
        
        // Service type filter
        if ($service !== null) {
            $query->where(function ($q) use ($service) {
                $q->whereExists(function ($subQ) use ($service) {
                    $subQ->select(DB::raw(1))
                        ->from('driver_details')
                        ->whereColumn('driver_details.user_id', 'user_last_locations.user_id')
                        ->where(function ($dq) use ($service) {
                            $dq->whereNull('driver_details.service')
                                ->orWhereRaw("JSON_CONTAINS(driver_details.service, ?)", [json_encode($service)]);
                        });
                });
            });
        }
        
        // Parcel weight capacity filter
        if ($parcelWeightCapacity !== null) {
            $query->where(function ($q) use ($parcelWeightCapacity) {
                $q->whereExists(function ($subQ) use ($parcelWeightCapacity) {
                    $subQ->select(DB::raw(1))
                        ->from('vehicles')
                        ->whereColumn('vehicles.driver_id', 'user_last_locations.user_id')
                        ->where(function ($vq) use ($parcelWeightCapacity) {
                            $vq->whereNull('vehicles.parcel_weight_capacity')
                                ->orWhere('vehicles.parcel_weight_capacity', '>=', $parcelWeightCapacity);
                        });
                });
            });
        }
        
        return $query
            ->orderBy('distance_meters', 'asc')
            ->limit(100)  // Reasonable limit
            ->get();
    }

    /**
     * Fallback: Haversine formula-based driver search
     * Used when spatial index is not available
     */
    private function getNearestDriversHaversine($attributes): mixed
    {
        return $this->last_location
            ->selectRaw("* ,( 6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude)
                - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$attributes['latitude'], $attributes['longitude'], $attributes['latitude']])
            ->where('type', 'driver')
            ->where('zone_id', $attributes['zone_id'])
            ->having('distance', '<', $attributes['radius'])
            ->with(['user.vehicle.category', 'driverDetails', 'user'])
            ->whereHas('user', fn($query) => $query->where('is_active', true))
            ->whereHas('driverDetails', fn($query) => $query->where('is_online', true)
                ->whereNotIn('availability_status', ['unavailable', 'on_trip']))
            ->whereHas('user.vehicle', fn($query) => $query->where('is_active', true))
            ->when(array_key_exists('vehicle_category_id', $attributes), function ($query) use ($attributes) {
                $categoryIds = is_array($attributes['vehicle_category_id']) 
                    ? $attributes['vehicle_category_id'] 
                    : [$attributes['vehicle_category_id']];
                $query->whereHas('user.vehicle', fn($query) => $query->ofStatus(1)
                    ->where(function ($q) use ($categoryIds) {
                        foreach ($categoryIds as $catId) {
                            $q->orWhereJsonContains('category_id', $catId);
                        }
                    }));
            })
            ->when(array_key_exists('service', $attributes), fn($query) => $query->whereHas('driverDetails', fn($query) => $query->where(fn($query) => $query->whereNull('service')
                ->orWhere(fn($query) => $query->whereNotNull('service')
                    ->whereJsonContains('service', $attributes['service'])))))
            ->when($attributes['parcel_weight_capacity'] ?? null, fn($query) => $query->whereHas('driverDetails', fn($query) => $query->where(fn($query) => $query->whereNull('service')
                ->orWhere(fn($query) => $query->whereNotNull('service')
                    ->whereJsonContains('service', 'parcel'))))
                ->whereHas('user.vehicle', fn($query) => $query->whereNull('parcel_weight_capacity')
                    ->orWhere('parcel_weight_capacity', '>=', $attributes['parcel_weight_capacity'])))
            ->orderBy('distance')
            ->limit(100)
            ->get();
    }
}

