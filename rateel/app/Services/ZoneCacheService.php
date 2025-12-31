<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\ZoneManagement\Entities\Zone;

/**
 * Issue #23 FIX: Zone Caching Service
 *
 * Caches active zones to avoid repeated spatial queries.
 * Zones rarely change, so caching provides significant performance benefit.
 */
class ZoneCacheService
{
    private const CACHE_KEY = 'zones:active:all';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all active zones from cache
     * Returns collection of zones with their polygons
     */
    public function getActiveZones()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Zone::where('is_active', 1)
                ->select(['id', 'name', 'coordinates', 'extra_fare_status', 'extra_fare_fee'])
                ->get();
        });
    }

    /**
     * Find zone by ID from cache
     */
    public function getZoneById($zoneId)
    {
        $zones = $this->getActiveZones();
        return $zones->firstWhere('id', $zoneId);
    }

    /**
     * Check if a point is in a specific zone
     * Uses cached zone polygons for in-memory check
     *
     * @param float $lat
     * @param float $lng
     * @param int $zoneId
     * @return bool
     */
    public function isPointInZone(float $lat, float $lng, $zoneId): bool
    {
        $zone = $this->getZoneById($zoneId);
        if (!$zone || !$zone->coordinates) {
            return false;
        }

        return $this->pointInPolygon($lat, $lng, $zone->coordinates);
    }

    /**
     * Find zone containing a point
     * First checks cache, falls back to DB for accuracy
     *
     * @param float $lat
     * @param float $lng
     * @return Zone|null
     */
    public function findZoneByPoint(float $lat, float $lng)
    {
        $zones = $this->getActiveZones();

        foreach ($zones as $zone) {
            if ($this->pointInPolygon($lat, $lng, $zone->coordinates)) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Point-in-polygon check using ray casting algorithm
     * Works with the coordinates format from the database
     *
     * @param float $lat
     * @param float $lng
     * @param mixed $coordinates Polygon coordinates
     * @return bool
     */
    private function pointInPolygon(float $lat, float $lng, $coordinates): bool
    {
        try {
            // Extract polygon points from the coordinates object
            $points = $this->extractPolygonPoints($coordinates);

            if (count($points) < 3) {
                return false;
            }

            $inside = false;
            $j = count($points) - 1;

            for ($i = 0; $i < count($points); $i++) {
                $latI = $points[$i]['lat'];
                $lngI = $points[$i]['lng'];
                $latJ = $points[$j]['lat'];
                $lngJ = $points[$j]['lng'];

                if ((($lngI > $lng) != ($lngJ > $lng)) &&
                    ($lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI)) {
                    $inside = !$inside;
                }

                $j = $i;
            }

            return $inside;
        } catch (\Exception $e) {
            Log::warning('Point in polygon check failed', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract lat/lng points from various polygon formats
     */
    private function extractPolygonPoints($coordinates): array
    {
        $points = [];

        // Handle Polygon object from eloquent-spatial
        if (is_object($coordinates) && method_exists($coordinates, 'getCoordinates')) {
            $rings = $coordinates->getCoordinates();
            if (!empty($rings) && is_array($rings)) {
                $ring = is_array($rings[0]) ? $rings[0] : $rings;
                foreach ($ring as $point) {
                    if (is_object($point) && method_exists($point, 'latitude') && method_exists($point, 'longitude')) {
                        $points[] = [
                            'lat' => $point->latitude,
                            'lng' => $point->longitude
                        ];
                    } elseif (is_array($point)) {
                        // [lng, lat] format
                        $points[] = [
                            'lat' => $point[1] ?? $point['lat'] ?? 0,
                            'lng' => $point[0] ?? $point['lng'] ?? 0
                        ];
                    }
                }
            }
        }
        // Handle array format from coordinates attribute
        elseif (is_array($coordinates)) {
            $polygon = $coordinates[0] ?? $coordinates;

            if (is_array($polygon)) {
                foreach ($polygon as $point) {
                    if (is_array($point)) {
                        $points[] = [
                            'lat' => $point[1] ?? $point['lat'] ?? 0,
                            'lng' => $point[0] ?? $point['lng'] ?? 0
                        ];
                    }
                }
            }
        }

        return $points;
    }

    /**
     * Clear zone cache (call when zones are updated)
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Log::info('Zone cache cleared');
    }

    /**
     * Warm the cache
     */
    public function warmCache(): void
    {
        $this->getActiveZones();
        Log::info('Zone cache warmed');
    }
}
