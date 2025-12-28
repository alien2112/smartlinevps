<?php

namespace Modules\DispatchManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\ZoneManagement\Entities\Zone;
use Modules\UserManagement\Entities\User;

/**
 * Honeycomb Settings Entity
 * 
 * Per-zone configuration for the H3 hexagonal grid dispatch system.
 * Settings are cached in Redis for real-time services.
 */
class HoneycombSetting extends Model
{
    use HasUuids;

    protected $table = 'dispatch_honeycomb_settings';

    protected $fillable = [
        'zone_id',
        'city_name',
        'enabled',
        'dispatch_enabled',
        'heatmap_enabled',
        'hotspots_enabled',
        'surge_enabled',
        'incentives_enabled',
        'h3_resolution',
        'search_depth_k',
        'update_interval_seconds',
        'min_drivers_to_color_cell',
        'surge_threshold',
        'surge_cap',
        'surge_step',
        'incentive_threshold',
        'max_incentive_amount',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'dispatch_enabled' => 'boolean',
        'heatmap_enabled' => 'boolean',
        'hotspots_enabled' => 'boolean',
        'surge_enabled' => 'boolean',
        'incentives_enabled' => 'boolean',
        'h3_resolution' => 'integer',
        'search_depth_k' => 'integer',
        'update_interval_seconds' => 'integer',
        'min_drivers_to_color_cell' => 'integer',
        'surge_threshold' => 'decimal:2',
        'surge_cap' => 'decimal:2',
        'surge_step' => 'decimal:2',
        'incentive_threshold' => 'decimal:2',
        'max_incentive_amount' => 'decimal:2',
    ];

    protected $attributes = [
        'enabled' => false,
        'dispatch_enabled' => false,
        'heatmap_enabled' => false,
        'hotspots_enabled' => false,
        'surge_enabled' => false,
        'incentives_enabled' => false,
        'h3_resolution' => 8,
        'search_depth_k' => 1,
        'update_interval_seconds' => 60,
        'min_drivers_to_color_cell' => 1,
        'surge_threshold' => 1.50,
        'surge_cap' => 2.00,
        'surge_step' => 0.10,
        'incentive_threshold' => 2.00,
        'max_incentive_amount' => 50.00,
    ];

    // H3 Resolution reference:
    // 7 = ~5.16 km² (city-level)
    // 8 = ~0.74 km² (neighborhood-level, recommended)
    // 9 = ~0.11 km² (block-level, high precision)
    public const H3_RESOLUTIONS = [
        7 => ['name' => 'City', 'area_km2' => 5.16, 'edge_km' => 2.6],
        8 => ['name' => 'Neighborhood', 'area_km2' => 0.74, 'edge_km' => 0.98],
        9 => ['name' => 'Block', 'area_km2' => 0.11, 'edge_km' => 0.37],
    ];

    /**
     * Zone relationship
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    /**
     * User who last updated
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get Redis cache key for this setting
     */
    public function getCacheKey(): string
    {
        return "honeycomb:settings:{$this->zone_id}";
    }

    /**
     * Get global Redis cache key (for when no zone specified)
     */
    public static function getGlobalCacheKey(): string
    {
        return "honeycomb:settings:global";
    }

    /**
     * Check if honeycomb dispatch is active for this zone
     */
    public function isDispatchActive(): bool
    {
        return $this->enabled && $this->dispatch_enabled;
    }

    /**
     * Check if heatmap is active
     */
    public function isHeatmapActive(): bool
    {
        return $this->enabled && $this->heatmap_enabled;
    }

    /**
     * Check if surge pricing is active
     */
    public function isSurgeActive(): bool
    {
        return $this->enabled && $this->surge_enabled;
    }

    /**
     * Calculate surge multiplier based on imbalance score
     */
    public function calculateSurge(float $imbalanceScore): float
    {
        if (!$this->isSurgeActive()) {
            return 1.0;
        }

        if ($imbalanceScore < $this->surge_threshold) {
            return 1.0;
        }

        // Calculate steps above threshold
        $excessRatio = $imbalanceScore - $this->surge_threshold;
        $steps = floor($excessRatio / 0.5); // Step every 0.5 increase in imbalance
        $surge = 1.0 + ($steps * $this->surge_step);

        return min($surge, (float) $this->surge_cap);
    }

    /**
     * Calculate incentive amount based on imbalance
     */
    public function calculateIncentive(float $imbalanceScore): float
    {
        if (!$this->enabled || !$this->incentives_enabled) {
            return 0;
        }

        if ($imbalanceScore < $this->incentive_threshold) {
            return 0;
        }

        // Linear scaling: more imbalance = more incentive
        $excessRatio = $imbalanceScore - $this->incentive_threshold;
        $incentive = min($excessRatio * 10, (float) $this->max_incentive_amount);

        return round($incentive, 2);
    }

    /**
     * Get resolution info
     */
    public function getResolutionInfo(): array
    {
        return self::H3_RESOLUTIONS[$this->h3_resolution] ?? self::H3_RESOLUTIONS[8];
    }

    /**
     * Serialize for Redis cache
     */
    public function toRedisCache(): array
    {
        return [
            'id' => $this->id,
            'zone_id' => $this->zone_id,
            'enabled' => $this->enabled,
            'dispatch_enabled' => $this->dispatch_enabled,
            'heatmap_enabled' => $this->heatmap_enabled,
            'hotspots_enabled' => $this->hotspots_enabled,
            'surge_enabled' => $this->surge_enabled,
            'incentives_enabled' => $this->incentives_enabled,
            'h3_resolution' => $this->h3_resolution,
            'search_depth_k' => $this->search_depth_k,
            'update_interval_seconds' => $this->update_interval_seconds,
            'surge_threshold' => (float) $this->surge_threshold,
            'surge_cap' => (float) $this->surge_cap,
            'surge_step' => (float) $this->surge_step,
        ];
    }
}
