<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Modules\DispatchManagement\Entities\HoneycombSetting;
use Modules\DispatchManagement\Entities\HoneycombCellMetric;

/**
 * Honeycomb Dispatch Service
 * 
 * Implements H3 hexagonal grid-based dispatch system for:
 * - Faster driver matching (cell + neighbor search)
 * - Real-time supply/demand heatmaps
 * - Driver hotspot recommendations
 * - Cell-based surge pricing (optional)
 * 
 * Similar to DiDi/Uber's approach using Uber's H3 library.
 * 
 * @see https://www.uber.com/blog/h3/
 */
class HoneycombService
{
    // Redis key prefixes
    private const DRIVER_CELL_PREFIX = 'hc:drivers:';        // SET of driverIds per cell
    private const CELL_SUPPLY_PREFIX = 'hc:supply:';        // HASH of supply counts
    private const CELL_DEMAND_PREFIX = 'hc:demand:';        // Counter per window
    private const SETTINGS_CACHE_PREFIX = 'hc:settings:';   // Cached settings
    private const DRIVER_CURRENT_CELL = 'hc:driver:cell:';  // Driver's current cell
    
    private $redis;
    private $defaultResolution = 8;  // ~1km hex edge

    public function __construct()
    {
        $this->redis = Redis::connection('default');
    }

    // ============================================================
    // H3 COORDINATE CONVERSION
    // ============================================================

    /**
     * Convert lat/lng to H3 index
     * 
     * Uses PHP implementation since H3 C library bindings may not be available.
     * For production, consider using the h3-php extension or calling Node.js.
     * 
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param int $resolution H3 resolution (7-9 recommended)
     * @return string H3 index as hex string
     */
    public function latLngToH3(float $lat, float $lng, int $resolution = null): string
    {
        $resolution = $resolution ?? $this->defaultResolution;
        
        // For now, use a simplified grid-based approach
        // In production, use h3-php extension or Node.js H3 library
        return $this->approximateH3Index($lat, $lng, $resolution);
    }

    /**
     * Get H3 cell center coordinates
     * 
     * @param string $h3Index
     * @return array ['lat' => float, 'lng' => float]
     */
    public function h3ToLatLng(string $h3Index): array
    {
        // Parse the simplified index back to coordinates
        return $this->parseApproximateH3($h3Index);
    }

    /**
     * Get neighboring cells (k-ring)
     * 
     * @param string $h3Index Center cell
     * @param int $k Ring depth (1 = immediate neighbors)
     * @return array Array of H3 indexes
     */
    public function kRing(string $h3Index, int $k = 1): array
    {
        $center = $this->h3ToLatLng($h3Index);
        $resolution = $this->getResolutionFromIndex($h3Index);
        $edgeKm = $this->getEdgeLengthKm($resolution);
        
        $neighbors = [$h3Index];
        
        // Generate approximate hex neighbors
        // Hex grid has 6 neighbors per ring
        for ($ring = 1; $ring <= $k; $ring++) {
            $offsetKm = $edgeKm * $ring * 1.5; // Approximate offset
            
            // 6 directions for hex (every 60 degrees)
            for ($dir = 0; $dir < 6; $dir++) {
                $angle = deg2rad($dir * 60);
                $offsetLat = ($offsetKm / 111) * cos($angle);
                $offsetLng = ($offsetKm / (111 * cos(deg2rad($center['lat'])))) * sin($angle);
                
                $neighborH3 = $this->latLngToH3(
                    $center['lat'] + $offsetLat,
                    $center['lng'] + $offsetLng,
                    $resolution
                );
                
                if (!in_array($neighborH3, $neighbors)) {
                    $neighbors[] = $neighborH3;
                }
            }
        }
        
        return $neighbors;
    }

    /**
     * Approximate H3 index using grid-based encoding
     * 
     * This is a simplified implementation. For production accuracy,
     * use the actual H3 library via Node.js or h3-php extension.
     */
    private function approximateH3Index(float $lat, float $lng, int $resolution): string
    {
        // Grid size based on resolution
        $gridSize = match ($resolution) {
            7 => 0.05,   // ~5km
            8 => 0.015,  // ~1.5km
            9 => 0.005,  // ~500m
            default => 0.015,
        };
        
        // Snap to grid
        $gridLat = round($lat / $gridSize) * $gridSize;
        $gridLng = round($lng / $gridSize) * $gridSize;
        
        // Create a unique index
        $latInt = (int)(($gridLat + 90) * 10000);
        $lngInt = (int)(($gridLng + 180) * 10000);
        
        // Format: r{resolution}_{latHex}_{lngHex}
        return sprintf('r%d_%06x_%06x', $resolution, $latInt, $lngInt);
    }

    /**
     * Parse approximate H3 index back to coordinates
     */
    private function parseApproximateH3(string $h3Index): array
    {
        if (preg_match('/^r(\d+)_([0-9a-f]+)_([0-9a-f]+)$/i', $h3Index, $matches)) {
            $latInt = hexdec($matches[2]);
            $lngInt = hexdec($matches[3]);
            
            return [
                'lat' => ($latInt / 10000) - 90,
                'lng' => ($lngInt / 10000) - 180,
            ];
        }
        
        return ['lat' => 0, 'lng' => 0];
    }

    /**
     * Get resolution from H3 index
     */
    private function getResolutionFromIndex(string $h3Index): int
    {
        if (preg_match('/^r(\d+)_/', $h3Index, $matches)) {
            return (int)$matches[1];
        }
        return $this->defaultResolution;
    }

    /**
     * Get approximate edge length in km for resolution
     */
    private function getEdgeLengthKm(int $resolution): float
    {
        return match ($resolution) {
            7 => 2.6,
            8 => 0.98,
            9 => 0.37,
            default => 0.98,
        };
    }

    // ============================================================
    // DRIVER CELL MANAGEMENT
    // ============================================================

    /**
     * Update driver's cell when their location changes
     * 
     * @param string $driverId
     * @param float $lat
     * @param float $lng
     * @param string $zoneId
     * @param string $category budget|pro|vip
     */
    public function updateDriverCell(
        string $driverId,
        float $lat,
        float $lng,
        string $zoneId,
        string $category = 'budget'
    ): void {
        $settings = $this->getSettings($zoneId);
        
        if (!$settings || !$settings['enabled']) {
            return;
        }
        
        $newCell = $this->latLngToH3($lat, $lng, $settings['h3_resolution']);
        $currentCellKey = self::DRIVER_CURRENT_CELL . $driverId;
        
        // Get driver's current cell
        $currentCell = $this->redis->get($currentCellKey);
        
        if ($currentCell === $newCell) {
            // Same cell, just refresh TTL
            $this->redis->expire($currentCellKey, 300);
            return;
        }
        
        $pipeline = $this->redis->pipeline();
        
        // Remove from old cell
        if ($currentCell) {
            $oldCellKey = $this->getCellDriversKey($zoneId, $currentCell);
            $pipeline->srem($oldCellKey, $driverId);
            
            // Decrement supply counter
            $supplyKey = $this->getCellSupplyKey($zoneId, $currentCell);
            $pipeline->hincrby($supplyKey, 'total', -1);
            $pipeline->hincrby($supplyKey, $category, -1);
        }
        
        // Add to new cell
        $newCellKey = $this->getCellDriversKey($zoneId, $newCell);
        $pipeline->sadd($newCellKey, $driverId);
        $pipeline->expire($newCellKey, 600);  // 10 min TTL
        
        // Increment supply counter
        $supplyKey = $this->getCellSupplyKey($zoneId, $newCell);
        $pipeline->hincrby($supplyKey, 'total', 1);
        $pipeline->hincrby($supplyKey, $category, 1);
        $pipeline->expire($supplyKey, 600);
        
        // Update driver's current cell
        $pipeline->setex($currentCellKey, 300, $newCell);
        
        $pipeline->exec();
        
        Log::debug('Driver cell updated', [
            'driver_id' => $driverId,
            'old_cell' => $currentCell,
            'new_cell' => $newCell,
            'zone_id' => $zoneId,
        ]);
    }

    /**
     * Remove driver from cells (when going offline)
     */
    public function removeDriverFromCells(string $driverId, string $zoneId): void
    {
        $currentCellKey = self::DRIVER_CURRENT_CELL . $driverId;
        $currentCell = $this->redis->get($currentCellKey);
        
        if ($currentCell) {
            $cellKey = $this->getCellDriversKey($zoneId, $currentCell);
            $this->redis->srem($cellKey, $driverId);
            $this->redis->del($currentCellKey);
        }
    }

    // ============================================================
    // DISPATCH ACCELERATION
    // ============================================================

    /**
     * Get candidate drivers using honeycomb cell search
     * 
     * This is the main dispatch acceleration method.
     * Instead of scanning all drivers, it only looks at:
     * 1. Origin cell
     * 2. k-ring neighbor cells
     * 
     * @param float $pickupLat
     * @param float $pickupLng
     * @param string $zoneId
     * @param string|null $category Filter by category
     * @return array Array of driverIds
     */
    public function getCandidateDrivers(
        float $pickupLat,
        float $pickupLng,
        string $zoneId,
        ?string $category = null
    ): array {
        $settings = $this->getSettings($zoneId);
        
        if (!$settings || !$settings['enabled'] || !$settings['dispatch_enabled']) {
            // Fallback: honeycomb not enabled
            return [];
        }
        
        $originCell = $this->latLngToH3($pickupLat, $pickupLng, $settings['h3_resolution']);
        $searchCells = $this->kRing($originCell, $settings['search_depth_k']);
        
        // Get all drivers from search cells
        $pipeline = $this->redis->pipeline();
        foreach ($searchCells as $cell) {
            $cellKey = $this->getCellDriversKey($zoneId, $cell);
            $pipeline->smembers($cellKey);
        }
        
        $results = $pipeline->exec();
        
        // Merge all driver IDs
        $driverIds = [];
        foreach ($results as $cellDrivers) {
            if (is_array($cellDrivers)) {
                $driverIds = array_merge($driverIds, $cellDrivers);
            }
        }
        
        // Remove duplicates
        $driverIds = array_unique($driverIds);
        
        Log::info('Honeycomb candidate search', [
            'pickup' => [$pickupLat, $pickupLng],
            'origin_cell' => $originCell,
            'cells_searched' => count($searchCells),
            'candidates_found' => count($driverIds),
            'zone_id' => $zoneId,
        ]);
        
        return $driverIds;
    }

    // ============================================================
    // DEMAND TRACKING
    // ============================================================

    /**
     * Record a ride request in the origin cell
     * 
     * @param float $pickupLat
     * @param float $pickupLng
     * @param string $zoneId
     * @param string $category
     */
    public function recordDemand(
        float $pickupLat,
        float $pickupLng,
        string $zoneId,
        string $category = 'budget'
    ): void {
        $settings = $this->getSettings($zoneId);
        
        if (!$settings || !$settings['enabled']) {
            return;
        }
        
        $cell = $this->latLngToH3($pickupLat, $pickupLng, $settings['h3_resolution']);
        $windowKey = $this->getTimeWindow();
        
        $demandKey = $this->getCellDemandKey($zoneId, $cell, $windowKey);
        
        $pipeline = $this->redis->pipeline();
        $pipeline->hincrby($demandKey, 'total', 1);
        $pipeline->hincrby($demandKey, $category, 1);
        $pipeline->expire($demandKey, 600);  // 10 min TTL
        $pipeline->exec();
    }

    // ============================================================
    // HEATMAP & HOTSPOTS
    // ============================================================

    /**
     * Generate all hexagon cells that cover a zone's geographic bounds
     *
     * @param string $zoneId
     * @param int $resolution H3 resolution
     * @return array Array of h3 indexes covering the zone
     */
    public function generateZoneCoverage(string $zoneId, int $resolution = null): array
    {
        // Get zone boundaries
        $zone = \Modules\ZoneManagement\Entities\Zone::find($zoneId);

        if (!$zone || !$zone->coordinates) {
            return [];
        }

        $resolution = $resolution ?? $this->defaultResolution;

        // Extract polygon coordinates
        $coordinates = $zone->coordinates[0] ?? null;
        if (!$coordinates) {
            return [];
        }

        // Get bounding box from coordinates
        $bounds = $this->getPolygonBounds($coordinates);

        if (!$bounds) {
            return [];
        }

        // Generate grid of hexagons covering the bounding box
        $hexagons = $this->generateHexGrid(
            $bounds['min_lat'],
            $bounds['max_lat'],
            $bounds['min_lng'],
            $bounds['max_lng'],
            $resolution
        );

        // Filter to only hexagons within the zone polygon
        $zoneCells = [];
        foreach ($hexagons as $h3Index) {
            $center = $this->h3ToLatLng($h3Index);
            if ($this->isPointInPolygon($center['lat'], $center['lng'], $coordinates)) {
                $zoneCells[] = $h3Index;
            }
        }

        return $zoneCells;
    }

    /**
     * Generate hexagonal grid covering a bounding box
     *
     * @param float $minLat
     * @param float $maxLat
     * @param float $minLng
     * @param float $maxLng
     * @param int $resolution
     * @return array
     */
    private function generateHexGrid(float $minLat, float $maxLat, float $minLng, float $maxLng, int $resolution): array
    {
        $gridSize = match ($resolution) {
            7 => 0.05,
            8 => 0.015,
            9 => 0.005,
            default => 0.015,
        };

        $hexagons = [];

        // Generate grid points
        for ($lat = $minLat; $lat <= $maxLat; $lat += $gridSize) {
            for ($lng = $minLng; $lng <= $maxLng; $lng += $gridSize) {
                $h3Index = $this->latLngToH3($lat, $lng, $resolution);
                if (!in_array($h3Index, $hexagons)) {
                    $hexagons[] = $h3Index;
                }
            }
        }

        return $hexagons;
    }

    /**
     * Get bounding box from polygon coordinates
     *
     * @param mixed $coordinates Polygon coordinates
     * @return array|null
     */
    private function getPolygonBounds($coordinates): ?array
    {
        $points = [];

        // Extract points from polygon
        if (is_object($coordinates) && method_exists($coordinates, 'getCoordinates')) {
            $points = $coordinates->getCoordinates()[0] ?? [];
        } elseif (is_array($coordinates)) {
            $points = $coordinates[0] ?? $coordinates;
        }

        if (empty($points)) {
            return null;
        }

        $minLat = PHP_FLOAT_MAX;
        $maxLat = PHP_FLOAT_MIN;
        $minLng = PHP_FLOAT_MAX;
        $maxLng = PHP_FLOAT_MIN;

        foreach ($points as $point) {
            $lat = null;
            $lng = null;

            if (is_array($point)) {
                // Array format [lng, lat] (MySQL standard)
                $lng = $point[0];
                $lat = $point[1];
            } elseif (is_object($point)) {
                if (isset($point->latitude) && isset($point->longitude)) {
                    $lat = $point->latitude;
                    $lng = $point->longitude;
                } elseif (isset($point->lat) && isset($point->lng)) {
                    $lat = $point->lat;
                    $lng = $point->lng;
                }
            }

            if ($lat !== null && $lng !== null) {
                $minLat = min($minLat, $lat);
                $maxLat = max($maxLat, $lat);
                $minLng = min($minLng, $lng);
                $maxLng = max($maxLng, $lng);
            }
        }

        return [
            'min_lat' => $minLat,
            'max_lat' => $maxLat,
            'min_lng' => $minLng,
            'max_lng' => $maxLng,
        ];
    }

    /**
     * Check if a point is inside a polygon using ray-casting algorithm
     *
     * @param float $lat
     * @param float $lng
     * @param mixed $coordinates
     * @return bool
     */
    private function isPointInPolygon(float $lat, float $lng, $coordinates): bool
    {
        $points = [];

        // Extract points from polygon
        if (is_object($coordinates) && method_exists($coordinates, 'getCoordinates')) {
            $points = $coordinates->getCoordinates()[0] ?? [];
        } elseif (is_array($coordinates)) {
            $points = $coordinates[0] ?? $coordinates;
        }

        if (count($points) < 3) {
            return false;
        }

        $inside = false;
        $j = count($points) - 1;

        for ($i = 0; $i < count($points); $i++) {
            $pointI = $this->extractLatLng($points[$i]);
            $pointJ = $this->extractLatLng($points[$j]);

            if (!$pointI || !$pointJ) {
                $j = $i;
                continue;
            }

            $latI = $pointI['lat'];
            $lngI = $pointI['lng'];
            $latJ = $pointJ['lat'];
            $lngJ = $pointJ['lng'];

            if ((($lngI > $lng) != ($lngJ > $lng)) &&
                ($lat < ($latJ - $latI) * ($lng - $lngI) / ($lngJ - $lngI) + $latI)) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * Extract lat/lng from various point formats
     */
    private function extractLatLng($point): ?array
    {
        if (is_array($point)) {
            // MySQL spatial format [lng, lat]
            return ['lat' => $point[1], 'lng' => $point[0]];
        } elseif (is_object($point)) {
            if (isset($point->latitude) && isset($point->longitude)) {
                return ['lat' => $point->latitude, 'lng' => $point->longitude];
            } elseif (isset($point->lat) && isset($point->lng)) {
                return ['lat' => $point->lat, 'lng' => $point->lng];
            }
        }
        return null;
    }

    /**
     * Get heatmap data for a zone
     *
     * @param string $zoneId
     * @param int $windowMinutes Time window to consider
     * @return Collection
     */
    public function getHeatmap(string $zoneId, int $windowMinutes = 5): Collection
    {
        $settings = $this->getSettings($zoneId);

        if (!$settings || !$settings['heatmap_enabled']) {
            return collect([]);
        }

        // Generate all hexagons covering the zone
        $allCells = $this->generateZoneCoverage($zoneId, $settings['h3_resolution']);

        $windowKey = $this->getTimeWindow();
        $heatmapData = collect();

        // Process all cells (both active and empty)
        foreach ($allCells as $h3Index) {
            $supplyKey = $this->getCellSupplyKey($zoneId, $h3Index);
            $demandKey = $this->getCellDemandKey($zoneId, $h3Index, $windowKey);

            $supply = $this->redis->hgetall($supplyKey);
            $demand = $this->redis->hgetall($demandKey);

            $supplyTotal = (int)($supply['total'] ?? 0);
            $demandTotal = (int)($demand['total'] ?? 0);

            // Skip completely empty cells unless configured to show all
            if ($supplyTotal === 0 && $demandTotal === 0 && $settings['min_drivers_to_color_cell'] > 0) {
                // Still include cell but with minimal data for grid visualization
                $center = $this->h3ToLatLng($h3Index);
                $heatmapData->push([
                    'h3_index' => $h3Index,
                    'center' => $center,
                    'supply' => 0,
                    'demand' => 0,
                    'imbalance' => 0,
                    'intensity' => 0,
                    'surge_multiplier' => 1.0,
                    'is_empty' => true,
                ]);
                continue;
            }

            // Calculate metrics
            $imbalance = $demandTotal / max($supplyTotal, 1);
            $center = $this->h3ToLatLng($h3Index);

            $heatmapData->push([
                'h3_index' => $h3Index,
                'center' => $center,
                'supply' => $supplyTotal,
                'demand' => $demandTotal,
                'imbalance' => round($imbalance, 2),
                'intensity' => min($imbalance / 5.0, 1.0),
                'surge_multiplier' => $this->calculateSurge($imbalance, $settings),
                'is_empty' => false,
                'supply_breakdown' => [
                    'budget' => (int)($supply['budget'] ?? 0),
                    'pro' => (int)($supply['pro'] ?? 0),
                    'vip' => (int)($supply['vip'] ?? 0),
                ],
            ]);
        }

        return $heatmapData->sortByDesc('imbalance');
    }

    /**
     * Get hotspots (high-demand cells) for driver app
     * 
     * @param string $zoneId
     * @param int $limit
     * @return Collection
     */
    public function getHotspots(string $zoneId, int $limit = 5): Collection
    {
        $heatmap = $this->getHeatmap($zoneId);
        
        return $heatmap
            ->where('imbalance', '>', 1.5)
            ->where('demand', '>=', 2)
            ->take($limit)
            ->map(fn($cell) => [
                'h3_index' => $cell['h3_index'],
                'center' => $cell['center'],
                'demand' => $cell['demand'],
                'supply' => $cell['supply'],
                'incentive' => $this->calculateDriverIncentive($cell['imbalance'], $zoneId),
            ]);
    }

    /**
     * Get cell stats for a specific location (driver's current position)
     * 
     * @param float $lat
     * @param float $lng
     * @param string $zoneId
     * @return array
     */
    public function getCellStats(float $lat, float $lng, string $zoneId): array
    {
        $settings = $this->getSettings($zoneId);
        
        if (!$settings || !$settings['enabled']) {
            return ['enabled' => false];
        }
        
        $cell = $this->latLngToH3($lat, $lng, $settings['h3_resolution']);
        $windowKey = $this->getTimeWindow();
        
        $supplyKey = $this->getCellSupplyKey($zoneId, $cell);
        $demandKey = $this->getCellDemandKey($zoneId, $cell, $windowKey);
        
        $supply = $this->redis->hgetall($supplyKey);
        $demand = $this->redis->hgetall($demandKey);
        
        $supplyTotal = (int)($supply['total'] ?? 0);
        $demandTotal = (int)($demand['total'] ?? 0);
        $imbalance = $demandTotal / max($supplyTotal, 1);
        
        // Get nearby hotspots
        $hotspots = $this->getHotspots($zoneId, 3);
        
        return [
            'enabled' => true,
            'current_cell' => $cell,
            'supply' => $supplyTotal,
            'demand' => $demandTotal,
            'imbalance' => round($imbalance, 2),
            'is_hotspot' => $imbalance > 1.5 && $demandTotal >= 2,
            'nearby_hotspots' => $hotspots->toArray(),
            'suggested_direction' => $this->getSuggestedDirection($lat, $lng, $hotspots),
        ];
    }

    // ============================================================
    // SURGE PRICING
    // ============================================================

    /**
     * Get surge multiplier for a location
     * 
     * @param float $lat
     * @param float $lng
     * @param string $zoneId
     * @return float
     */
    public function getSurgeMultiplier(float $lat, float $lng, string $zoneId): float
    {
        $settings = $this->getSettings($zoneId);
        
        if (!$settings || !$settings['surge_enabled']) {
            return 1.0;
        }
        
        $cell = $this->latLngToH3($lat, $lng, $settings['h3_resolution']);
        $windowKey = $this->getTimeWindow();
        
        $supplyKey = $this->getCellSupplyKey($zoneId, $cell);
        $demandKey = $this->getCellDemandKey($zoneId, $cell, $windowKey);
        
        $supplyTotal = (int)($this->redis->hget($supplyKey, 'total') ?? 0);
        $demandTotal = (int)($this->redis->hget($demandKey, 'total') ?? 0);
        
        $imbalance = $demandTotal / max($supplyTotal, 1);
        
        return $this->calculateSurge($imbalance, $settings);
    }

    /**
     * Calculate surge multiplier from imbalance
     */
    private function calculateSurge(float $imbalance, array $settings): float
    {
        if (!($settings['surge_enabled'] ?? false)) {
            return 1.0;
        }
        
        $threshold = (float)($settings['surge_threshold'] ?? 1.5);
        $cap = (float)($settings['surge_cap'] ?? 2.0);
        $step = (float)($settings['surge_step'] ?? 0.1);
        
        if ($imbalance < $threshold) {
            return 1.0;
        }
        
        $excess = $imbalance - $threshold;
        $steps = floor($excess / 0.5);
        $surge = 1.0 + ($steps * $step);
        
        return min($surge, $cap);
    }

    /**
     * Calculate driver incentive for moving to a cell
     */
    private function calculateDriverIncentive(float $imbalance, string $zoneId): float
    {
        $settings = $this->getSettings($zoneId);
        
        if (!$settings || !($settings['incentives_enabled'] ?? false)) {
            return 0;
        }
        
        $threshold = (float)($settings['incentive_threshold'] ?? 2.0);
        $maxIncentive = (float)($settings['max_incentive_amount'] ?? 50.0);
        
        if ($imbalance < $threshold) {
            return 0;
        }
        
        $excess = $imbalance - $threshold;
        return min($excess * 10, $maxIncentive);
    }

    // ============================================================
    // SETTINGS MANAGEMENT
    // ============================================================

    /**
     * Get honeycomb settings for a zone (cached)
     */
    public function getSettings(string $zoneId): ?array
    {
        $cacheKey = self::SETTINGS_CACHE_PREFIX . $zoneId;
        
        // Try Redis cache first
        $cached = $this->redis->get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }
        
        // Fetch from database
        $settings = HoneycombSetting::where('zone_id', $zoneId)->first();
        
        if (!$settings) {
            // Try global settings
            $settings = HoneycombSetting::whereNull('zone_id')->first();
        }
        
        if (!$settings) {
            return null;
        }
        
        $cached = $settings->toRedisCache();
        
        // Cache for 5 minutes
        $this->redis->setex($cacheKey, 300, json_encode($cached));
        
        return $cached;
    }

    /**
     * Clear settings cache (call after admin update)
     */
    public function clearSettingsCache(string $zoneId = null): void
    {
        if ($zoneId) {
            $this->redis->del(self::SETTINGS_CACHE_PREFIX . $zoneId);
        } else {
            // Clear all settings caches
            $keys = $this->redis->keys(self::SETTINGS_CACHE_PREFIX . '*');
            if (!empty($keys)) {
                $this->redis->del(...$keys);
            }
        }
        
        // Publish event for Node.js to reload
        $this->redis->publish('dispatch.config.updated', json_encode([
            'type' => 'honeycomb',
            'zone_id' => $zoneId,
            'timestamp' => now()->toIso8601String(),
        ]));
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    private function getCellDriversKey(string $zoneId, string $h3Index): string
    {
        return self::DRIVER_CELL_PREFIX . $zoneId . ':' . $h3Index;
    }

    private function getCellSupplyKey(string $zoneId, string $h3Index): string
    {
        return self::CELL_SUPPLY_PREFIX . $zoneId . ':' . $h3Index;
    }

    private function getCellDemandKey(string $zoneId, string $h3Index, string $windowKey): string
    {
        return self::CELL_DEMAND_PREFIX . $zoneId . ':' . $h3Index . ':' . $windowKey;
    }

    private function getTimeWindow(): string
    {
        // 5-minute windows (configurable)
        $timestamp = floor(time() / 300) * 300;
        return (string)$timestamp;
    }

    /**
     * Get suggested direction to nearest hotspot
     */
    private function getSuggestedDirection(float $lat, float $lng, Collection $hotspots): ?array
    {
        if ($hotspots->isEmpty()) {
            return null;
        }
        
        $nearest = $hotspots->first();
        $targetLat = $nearest['center']['lat'];
        $targetLng = $nearest['center']['lng'];
        
        // Calculate bearing
        $dLng = deg2rad($targetLng - $lng);
        $y = sin($dLng) * cos(deg2rad($targetLat));
        $x = cos(deg2rad($lat)) * sin(deg2rad($targetLat)) - 
             sin(deg2rad($lat)) * cos(deg2rad($targetLat)) * cos($dLng);
        $bearing = rad2deg(atan2($y, $x));
        
        // Approximate distance
        $distanceKm = $this->haversineDistance($lat, $lng, $targetLat, $targetLng);
        
        return [
            'bearing' => round(fmod($bearing + 360, 360)),
            'distance_km' => round($distanceKm, 1),
            'target_cell' => $nearest['h3_index'],
            'incentive' => $nearest['incentive'],
        ];
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}
