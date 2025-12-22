<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Centralized Performance Caching Service
 * 
 * Provides high-performance caching for frequently accessed data
 * with proper TTL management and cache invalidation strategies.
 */
class PerformanceCache
{
    // Cache TTLs in seconds
    public const TTL_CONFIG = 3600;           // 1 hour for business config
    public const TTL_ROUTE = 1800;            // 30 minutes for route calculations
    public const TTL_ZONE = 300;              // 5 minutes for zone data
    public const TTL_DRIVER_LOCATION = 30;    // 30 seconds for driver locations
    public const TTL_PENDING_TRIPS = 10;      // 10 seconds for pending trips count

    // Cache key prefixes
    public const PREFIX_CONFIG = 'config:';
    public const PREFIX_ROUTE = 'route:';
    public const PREFIX_ZONE = 'zone:';
    public const PREFIX_DRIVER = 'driver:';
    public const PREFIX_TRIP = 'trip:';

    /**
     * Get business configuration with proper caching
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getConfig(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::PREFIX_CONFIG . $key;
        
        return Cache::remember($cacheKey, self::TTL_CONFIG, function () use ($key, $default) {
            try {
                $config = \Modules\BusinessManagement\Entities\BusinessSetting::query()
                    ->where('key_name', $key)
                    ->first();
                
                return $config?->value ?? $default;
            } catch (\Exception $e) {
                Log::warning('PerformanceCache: Failed to fetch config', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
                return $default;
            }
        });
    }

    /**
     * Get cached route calculation
     *
     * @param array $origin [lat, lng]
     * @param array $destination [lat, lng]
     * @param array $waypoints
     * @return array|null
     */
    public static function getRoute(array $origin, array $destination, array $waypoints = []): ?array
    {
        $cacheKey = self::buildRouteKey($origin, $destination, $waypoints);
        
        return Cache::get($cacheKey);
    }

    /**
     * Store route calculation in cache
     *
     * @param array $origin
     * @param array $destination
     * @param array $waypoints
     * @param array $routeData
     * @return void
     */
    public static function setRoute(array $origin, array $destination, array $waypoints, array $routeData): void
    {
        $cacheKey = self::buildRouteKey($origin, $destination, $waypoints);
        
        Cache::put($cacheKey, $routeData, self::TTL_ROUTE);
    }

    /**
     * Build a deterministic cache key for routes
     *
     * @param array $origin
     * @param array $destination
     * @param array $waypoints
     * @return string
     */
    private static function buildRouteKey(array $origin, array $destination, array $waypoints): string
    {
        // Round coordinates to 4 decimal places (~11m accuracy) to increase cache hits
        $originKey = round($origin[0], 4) . ',' . round($origin[1], 4);
        $destKey = round($destination[0], 4) . ',' . round($destination[1], 4);
        
        $waypointsKey = '';
        if (!empty($waypoints)) {
            $wpParts = [];
            foreach ($waypoints as $wp) {
                if (isset($wp[0], $wp[1])) {
                    $wpParts[] = round($wp[0], 4) . ',' . round($wp[1], 4);
                }
            }
            $waypointsKey = ':' . implode('|', $wpParts);
        }
        
        return self::PREFIX_ROUTE . md5($originKey . ':' . $destKey . $waypointsKey);
    }

    /**
     * Get zone by point with caching
     *
     * @param float $lat
     * @param float $lng
     * @return mixed
     */
    public static function getZoneByPoint(float $lat, float $lng): mixed
    {
        // Round to 3 decimal places (~111m) for zone lookups
        $cacheKey = self::PREFIX_ZONE . 'point:' . round($lat, 3) . ':' . round($lng, 3);
        
        return Cache::get($cacheKey);
    }

    /**
     * Store zone data for a point
     *
     * @param float $lat
     * @param float $lng
     * @param mixed $zone
     * @return void
     */
    public static function setZoneByPoint(float $lat, float $lng, mixed $zone): void
    {
        $cacheKey = self::PREFIX_ZONE . 'point:' . round($lat, 3) . ':' . round($lng, 3);
        
        Cache::put($cacheKey, $zone, self::TTL_ZONE);
    }

    /**
     * Get pending trips count for a zone
     *
     * @param string $zoneId
     * @return int|null
     */
    public static function getPendingTripsCount(string $zoneId): ?int
    {
        return Cache::get(self::PREFIX_TRIP . 'pending:' . $zoneId);
    }

    /**
     * Set pending trips count for a zone
     *
     * @param string $zoneId
     * @param int $count
     * @return void
     */
    public static function setPendingTripsCount(string $zoneId, int $count): void
    {
        Cache::put(self::PREFIX_TRIP . 'pending:' . $zoneId, $count, self::TTL_PENDING_TRIPS);
    }

    /**
     * Invalidate all config cache
     *
     * @return void
     */
    public static function invalidateConfig(): void
    {
        // Use cache tags if available, otherwise clear specific keys
        if (method_exists(Cache::getStore(), 'tags')) {
            Cache::tags(['config'])->flush();
        }
    }

    /**
     * Invalidate zone cache for a specific zone
     *
     * @param string $zoneId
     * @return void
     */
    public static function invalidateZone(string $zoneId): void
    {
        Cache::forget(self::PREFIX_ZONE . $zoneId);
    }

    /**
     * Invalidate pending trips cache for a zone
     *
     * @param string $zoneId
     * @return void
     */
    public static function invalidatePendingTrips(string $zoneId): void
    {
        Cache::forget(self::PREFIX_TRIP . 'pending:' . $zoneId);
    }

    /**
     * Get multiple config values at once (batch fetch)
     *
     * @param array $keys
     * @return array
     */
    public static function getConfigs(array $keys): array
    {
        $result = [];
        $missingKeys = [];
        
        // Check cache first
        foreach ($keys as $key) {
            $cacheKey = self::PREFIX_CONFIG . $key;
            $value = Cache::get($cacheKey);
            
            if ($value !== null) {
                $result[$key] = $value;
            } else {
                $missingKeys[] = $key;
            }
        }
        
        // Batch fetch missing keys
        if (!empty($missingKeys)) {
            try {
                $configs = \Modules\BusinessManagement\Entities\BusinessSetting::query()
                    ->whereIn('key_name', $missingKeys)
                    ->get()
                    ->keyBy('key_name');
                
                foreach ($missingKeys as $key) {
                    $value = $configs->get($key)?->value;
                    $result[$key] = $value;
                    
                    // Cache the value
                    Cache::put(self::PREFIX_CONFIG . $key, $value, self::TTL_CONFIG);
                }
            } catch (\Exception $e) {
                Log::warning('PerformanceCache: Failed to batch fetch configs', [
                    'keys' => $missingKeys,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $result;
    }
}
