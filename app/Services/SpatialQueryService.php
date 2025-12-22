<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * High-Performance Spatial Query Service
 * 
 * Uses MySQL spatial functions with indexes for efficient
 * geospatial queries (driver search, zone lookup, etc.)
 */
class SpatialQueryService
{
    /**
     * Find nearest drivers using spatial index
     * 
     * This is a high-performance replacement for the Haversine-based
     * query that doesn't utilize indexes.
     *
     * @param float $latitude
     * @param float $longitude
     * @param string $zoneId
     * @param float $radiusKm Radius in kilometers
     * @param array $options Additional filter options
     * @return Collection
     */
    public function findNearestDrivers(
        float $latitude,
        float $longitude,
        string $zoneId,
        float $radiusKm = 5,
        array $options = []
    ): Collection {
        $radiusMeters = $radiusKm * 1000;
        
        // Build the point from driver location
        $point = "ST_SRID(POINT({$longitude}, {$latitude}), 4326)";
        
        try {
            $query = DB::table('user_last_locations as ull')
                ->select([
                    'ull.*',
                    DB::raw("ST_Distance_Sphere(ull.location_point, {$point}) as distance_meters")
                ])
                ->join('users as u', 'ull.user_id', '=', 'u.id')
                ->join('driver_details as dd', 'u.id', '=', 'dd.user_id')
                ->leftJoin('vehicles as v', function ($join) {
                    $join->on('u.id', '=', 'v.driver_id')
                        ->where('v.is_active', '=', 1);
                })
                // Spatial filter using index
                ->whereRaw("ST_Distance_Sphere(ull.location_point, {$point}) <= ?", [$radiusMeters])
                ->where('ull.type', 'driver')
                ->where('ull.zone_id', $zoneId)
                // Driver status filters
                ->where('u.is_active', true)
                ->where('dd.is_online', true)
                ->whereNotIn('dd.availability_status', ['unavailable', 'on_trip']);
            
            // Optional: Filter by vehicle category
            if (!empty($options['vehicle_category_id'])) {
                $categoryIds = is_array($options['vehicle_category_id']) 
                    ? $options['vehicle_category_id'] 
                    : [$options['vehicle_category_id']];
                
                $query->where(function ($q) use ($categoryIds) {
                    foreach ($categoryIds as $catId) {
                        $q->orWhereRaw("JSON_CONTAINS(v.category_id, ?)", [json_encode($catId)]);
                    }
                });
            }
            
            // Optional: Filter by service type
            if (!empty($options['service'])) {
                $query->where(function ($q) use ($options) {
                    $q->whereNull('dd.service')
                        ->orWhereRaw("JSON_CONTAINS(dd.service, ?)", [json_encode($options['service'])]);
                });
            }
            
            // Optional: Filter by parcel weight capacity
            if (!empty($options['parcel_weight_capacity'])) {
                $query->where(function ($q) use ($options) {
                    $q->whereNull('v.parcel_weight_capacity')
                        ->orWhere('v.parcel_weight_capacity', '>=', $options['parcel_weight_capacity']);
                });
            }
            
            // Order by distance and limit
            $limit = $options['limit'] ?? 50;
            
            return $query
                ->orderBy('distance_meters', 'asc')
                ->limit($limit)
                ->get();
                
        } catch (\Exception $e) {
            Log::error('SpatialQueryService: findNearestDrivers failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to original Haversine query if spatial fails
            return $this->findNearestDriversFallback($latitude, $longitude, $zoneId, $radiusKm, $options);
        }
    }

    /**
     * Fallback method using Haversine formula (no spatial index)
     */
    private function findNearestDriversFallback(
        float $latitude,
        float $longitude,
        string $zoneId,
        float $radiusKm,
        array $options
    ): Collection {
        return DB::table('user_last_locations as ull')
            ->selectRaw("ull.*, 
                (6371 * acos(cos(radians(?)) * cos(radians(ull.latitude)) 
                * cos(radians(ull.longitude) - radians(?)) 
                + sin(radians(?)) * sin(radians(ull.latitude)))) AS distance_km",
                [$latitude, $longitude, $latitude])
            ->join('users as u', 'ull.user_id', '=', 'u.id')
            ->join('driver_details as dd', 'u.id', '=', 'dd.user_id')
            ->where('ull.type', 'driver')
            ->where('ull.zone_id', $zoneId)
            ->where('u.is_active', true)
            ->where('dd.is_online', true)
            ->whereNotIn('dd.availability_status', ['unavailable', 'on_trip'])
            ->having('distance_km', '<', $radiusKm)
            ->orderBy('distance_km', 'asc')
            ->limit($options['limit'] ?? 50)
            ->get();
    }

    /**
     * Find trips within radius of a point using spatial index
     *
     * @param float $latitude
     * @param float $longitude
     * @param float $radiusKm
     * @param string $zoneId
     * @param array $options
     * @return Collection
     */
    public function findTripsNearby(
        float $latitude,
        float $longitude,
        float $radiusKm,
        string $zoneId,
        array $options = []
    ): Collection {
        $radiusMeters = $radiusKm * 1000;
        $point = "ST_SRID(POINT({$longitude}, {$latitude}), 4326)";
        
        try {
            $query = DB::table('trip_requests as tr')
                ->select([
                    'tr.*',
                    DB::raw("ST_Distance_Sphere(trc.pickup_coordinates, {$point}) as distance_meters")
                ])
                ->join('trip_request_coordinates as trc', 'tr.id', '=', 'trc.trip_request_id')
                ->whereRaw("ST_Distance_Sphere(trc.pickup_coordinates, {$point}) <= ?", [$radiusMeters])
                ->where('tr.zone_id', $zoneId)
                ->where('tr.current_status', 'pending');
            
            // Filter by vehicle category
            if (!empty($options['vehicle_category_id'])) {
                $categoryIds = is_array($options['vehicle_category_id']) 
                    ? $options['vehicle_category_id'] 
                    : [$options['vehicle_category_id']];
                
                $query->where(function ($q) use ($categoryIds) {
                    $q->whereIn('tr.vehicle_category_id', $categoryIds)
                        ->orWhereNull('tr.vehicle_category_id');
                });
            }
            
            // Filter by trip type
            if (!empty($options['type'])) {
                $query->where('tr.type', $options['type']);
            }
            
            // Exclude ignored requests
            if (!empty($options['exclude_driver_id'])) {
                $query->whereNotExists(function ($subquery) use ($options) {
                    $subquery->select(DB::raw(1))
                        ->from('rejected_driver_requests')
                        ->whereColumn('rejected_driver_requests.trip_request_id', 'tr.id')
                        ->where('rejected_driver_requests.user_id', $options['exclude_driver_id']);
                });
            }
            
            return $query
                ->orderBy('distance_meters', 'asc')
                ->limit($options['limit'] ?? 20)
                ->get();
                
        } catch (\Exception $e) {
            Log::error('SpatialQueryService: findTripsNearby failed', [
                'error' => $e->getMessage()
            ]);
            
            return collect();
        }
    }

    /**
     * Check if a point is within any active zone
     *
     * @param float $latitude
     * @param float $longitude
     * @return object|null Zone object or null
     */
    public function getZoneContainingPoint(float $latitude, float $longitude): ?object
    {
        // Check cache first
        $cached = PerformanceCache::getZoneByPoint($latitude, $longitude);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $point = "ST_SRID(POINT({$longitude}, {$latitude}), 4326)";
            
            $zone = DB::table('zones')
                ->selectRaw("*, ST_AsText(ST_Centroid(coordinates)) as center")
                ->whereRaw("ST_Contains(coordinates, {$point})")
                ->where('is_active', 1)
                ->first();
            
            // Cache the result (even if null to prevent repeated lookups)
            PerformanceCache::setZoneByPoint($latitude, $longitude, $zone);
            
            return $zone;
            
        } catch (\Exception $e) {
            Log::error('SpatialQueryService: getZoneContainingPoint failed', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Verify both pickup and destination are in the same zone (single query)
     *
     * @param float $pickupLat
     * @param float $pickupLng
     * @param float $destLat
     * @param float $destLng
     * @param string $zoneId
     * @return array ['pickup' => bool, 'destination' => bool]
     */
    public function verifyPointsInZone(
        float $pickupLat,
        float $pickupLng,
        float $destLat,
        float $destLng,
        string $zoneId
    ): array {
        try {
            $pickupPoint = "ST_SRID(POINT({$pickupLng}, {$pickupLat}), 4326)";
            $destPoint = "ST_SRID(POINT({$destLng}, {$destLat}), 4326)";
            
            $result = DB::table('zones')
                ->selectRaw("
                    ST_Contains(coordinates, {$pickupPoint}) as pickup_in_zone,
                    ST_Contains(coordinates, {$destPoint}) as destination_in_zone
                ")
                ->where('id', $zoneId)
                ->where('is_active', 1)
                ->first();
            
            return [
                'pickup' => (bool) ($result->pickup_in_zone ?? false),
                'destination' => (bool) ($result->destination_in_zone ?? false),
            ];
            
        } catch (\Exception $e) {
            Log::error('SpatialQueryService: verifyPointsInZone failed', [
                'error' => $e->getMessage()
            ]);
            
            return ['pickup' => false, 'destination' => false];
        }
    }

    /**
     * Update driver location with spatial point
     *
     * @param string $userId
     * @param float $latitude
     * @param float $longitude
     * @param string $zoneId
     * @return bool
     */
    public function updateDriverLocation(
        string $userId,
        float $latitude,
        float $longitude,
        string $zoneId
    ): bool {
        try {
            // The trigger will auto-populate location_point
            DB::table('user_last_locations')
                ->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'type' => 'driver',
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'zone_id' => $zoneId,
                        'updated_at' => now(),
                    ]
                );
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('SpatialQueryService: updateDriverLocation failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
