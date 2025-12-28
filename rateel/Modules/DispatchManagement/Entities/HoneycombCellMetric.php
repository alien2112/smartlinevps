<?php

namespace Modules\DispatchManagement\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Modules\ZoneManagement\Entities\Zone;

/**
 * Honeycomb Cell Metrics Entity
 * 
 * Stores supply/demand metrics for H3 hexagonal cells.
 * Used for heatmaps, surge pricing, and driver hotspot recommendations.
 */
class HoneycombCellMetric extends Model
{
    use HasUuids;

    protected $table = 'honeycomb_cell_metrics';

    protected $fillable = [
        'zone_id',
        'h3_index',
        'window_start',
        'window_minutes',
        'supply_total',
        'supply_budget',
        'supply_pro',
        'supply_vip',
        'demand_total',
        'demand_budget',
        'demand_pro',
        'demand_vip',
        'imbalance_score',
        'surge_multiplier',
        'incentive_amount',
        'center_lat',
        'center_lng',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_minutes' => 'integer',
        'supply_total' => 'integer',
        'supply_budget' => 'integer',
        'supply_pro' => 'integer',
        'supply_vip' => 'integer',
        'demand_total' => 'integer',
        'demand_budget' => 'integer',
        'demand_pro' => 'integer',
        'demand_vip' => 'integer',
        'imbalance_score' => 'decimal:4',
        'surge_multiplier' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
    ];

    /**
     * Zone relationship
     */
    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    /**
     * Calculate imbalance score
     */
    public function calculateImbalance(): float
    {
        $supply = max($this->supply_total, 1);
        return round($this->demand_total / $supply, 4);
    }

    /**
     * Update imbalance score
     */
    public function updateImbalance(): self
    {
        $this->imbalance_score = $this->calculateImbalance();
        return $this;
    }

    /**
     * Check if cell has high demand (imbalance > threshold)
     */
    public function isHighDemand(float $threshold = 1.5): bool
    {
        return $this->imbalance_score > $threshold;
    }

    /**
     * Check if cell is a hotspot (good for drivers)
     */
    public function isHotspot(): bool
    {
        return $this->isHighDemand(2.0) && $this->demand_total >= 3;
    }

    /**
     * Get supply by category
     */
    public function getSupplyByCategory(?string $category = null): int
    {
        return match (strtolower($category ?? '')) {
            'budget' => $this->supply_budget,
            'pro' => $this->supply_pro,
            'vip' => $this->supply_vip,
            default => $this->supply_total,
        };
    }

    /**
     * Get demand by category
     */
    public function getDemandByCategory(?string $category = null): int
    {
        return match (strtolower($category ?? '')) {
            'budget' => $this->demand_budget,
            'pro' => $this->demand_pro,
            'vip' => $this->demand_vip,
            default => $this->demand_total,
        };
    }

    /**
     * Increment supply counter
     */
    public function incrementSupply(string $category = 'budget'): void
    {
        $this->supply_total++;
        
        match (strtolower($category)) {
            'budget' => $this->supply_budget++,
            'pro' => $this->supply_pro++,
            'vip' => $this->supply_vip++,
            default => null,
        };
    }

    /**
     * Decrement supply counter
     */
    public function decrementSupply(string $category = 'budget'): void
    {
        $this->supply_total = max(0, $this->supply_total - 1);
        
        match (strtolower($category)) {
            'budget' => $this->supply_budget = max(0, $this->supply_budget - 1),
            'pro' => $this->supply_pro = max(0, $this->supply_pro - 1),
            'vip' => $this->supply_vip = max(0, $this->supply_vip - 1),
            default => null,
        };
    }

    /**
     * Increment demand counter
     */
    public function incrementDemand(string $category = 'budget'): void
    {
        $this->demand_total++;
        
        match (strtolower($category)) {
            'budget' => $this->demand_budget++,
            'pro' => $this->demand_pro++,
            'vip' => $this->demand_vip++,
            default => null,
        };
    }

    /**
     * Get heatmap intensity (0-1 scale)
     */
    public function getHeatmapIntensity(): float
    {
        // Normalize imbalance to 0-1 scale (cap at 5.0 imbalance)
        return min($this->imbalance_score / 5.0, 1.0);
    }

    /**
     * Format for API response
     */
    public function toApiResponse(): array
    {
        return [
            'h3_index' => $this->h3_index,
            'center' => [
                'lat' => (float) $this->center_lat,
                'lng' => (float) $this->center_lng,
            ],
            'supply' => $this->supply_total,
            'demand' => $this->demand_total,
            'imbalance' => (float) $this->imbalance_score,
            'surge_multiplier' => (float) $this->surge_multiplier,
            'incentive_amount' => (float) $this->incentive_amount,
            'intensity' => $this->getHeatmapIntensity(),
            'is_hotspot' => $this->isHotspot(),
            'supply_breakdown' => [
                'budget' => $this->supply_budget,
                'pro' => $this->supply_pro,
                'vip' => $this->supply_vip,
            ],
            'demand_breakdown' => [
                'budget' => $this->demand_budget,
                'pro' => $this->demand_pro,
                'vip' => $this->demand_vip,
            ],
        ];
    }

    /**
     * Scope: Get hotspots for a zone
     */
    public function scopeHotspots($query, string $zoneId)
    {
        return $query
            ->where('zone_id', $zoneId)
            ->where('imbalance_score', '>', 2.0)
            ->where('demand_total', '>=', 3)
            ->orderByDesc('imbalance_score');
    }

    /**
     * Scope: Get recent metrics within time window
     */
    public function scopeRecent($query, int $minutes = 5)
    {
        return $query->where('window_start', '>=', now()->subMinutes($minutes));
    }
}
