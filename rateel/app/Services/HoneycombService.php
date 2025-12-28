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
        
        // Get all supply keys for this zone
        $supplyPattern = self::CELL_SUPPLY_PREFIX . $zoneId . ':*';
        $supplyKeys = $this->redis->keys($supplyPattern);
        
        $heatmapData = collect();
        
        foreach ($supplyKeys as $key) {
            // Extract h3 from key
            preg_match('/^' . preg_quote(self::CELL_SUPPLY_PREFIX . $zoneId . ':', '/') . '(.+)$/', $key, $matches);
            $h3Index = $matches[1] ?? null;
            
            if (!$h3Index) continue;
            
            $supply = $this->redis->hgetall($key);
            $supplyTotal = (int)($supply['total'] ?? 0);
            
            if ($supplyTotal < $settings['min_drivers_to_color_cell']) {
                continue;
            }
            
            // Get demand for current window
            $windowKey = $this->getTimeWindow();
            $demandKey = $this->getCellDemandKey($zoneId, $h3Index, $windowKey);
            $demand = $this->redis->hgetall($demandKey);
            $demandTotal = (int)($demand['total'] ?? 0);
            
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
