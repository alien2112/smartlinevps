<?php

namespace Modules\TripManagement\Traits;

use App\Services\HoneycombService;
use Illuminate\Support\Facades\Log;

/**
 * Honeycomb Dispatch Integration Trait
 * 
 * Integrates honeycomb cell-based dispatch into TripRequestService.
 * When honeycomb is enabled for a zone:
 * 1. Uses cell + neighbor search for candidates
 * 2. Falls back to regular geo-radius if no candidates found
 * 3. Records demand for heatmap analytics
 */
trait HoneycombDispatchTrait
{
    /**
     * Find nearest drivers with honeycomb acceleration
     * 
     * This method wraps the regular findNearestDriver method
     * with honeycomb cell-based filtering when enabled.
     * 
     * @param float $latitude
     * @param float $longitude
     * @param string $zoneId
     * @param float $radius
     * @param string|null $vehicleCategoryId
     * @param bool $femaleOnly
     * @return mixed
     */
    public function findNearestDriverWithHoneycomb(
        float $latitude,
        float $longitude,
        string $zoneId,
        float $radius = 5,
        ?string $vehicleCategoryId = null,
        bool $femaleOnly = false
    ): mixed {
        $honeycombService = app(HoneycombService::class);
        
        // Record demand for heatmap (regardless of honeycomb dispatch status)
        $category = $this->getCategoryName($vehicleCategoryId);
        $honeycombService->recordDemand($latitude, $longitude, $zoneId, $category);
        
        // Try honeycomb cell-based search first
        $candidateDriverIds = $honeycombService->getCandidateDrivers(
            $latitude,
            $longitude,
            $zoneId,
            $category
        );
        
        if (!empty($candidateDriverIds)) {
            Log::info('Honeycomb dispatch: using cell-filtered candidates', [
                'pickup' => [$latitude, $longitude],
                'zone_id' => $zoneId,
                'candidate_count' => count($candidateDriverIds),
            ]);
            
            // Query only the candidate drivers
            return $this->getDriversByIds(
                $candidateDriverIds,
                $latitude,
                $longitude,
                $vehicleCategoryId,
                $femaleOnly
            );
        }
        
        // Fallback to regular geo-radius search
        Log::info('Honeycomb dispatch: falling back to geo-radius', [
            'pickup' => [$latitude, $longitude],
            'zone_id' => $zoneId,
            'radius' => $radius,
        ]);
        
        return $this->findNearestDriver(
            $latitude,
            $longitude,
            $zoneId,
            $radius,
            $vehicleCategoryId,
            $femaleOnly
        );
    }

    /**
     * Get drivers by ID list with distance calculation
     * 
     * Used after honeycomb filtering to fetch and sort the candidate drivers.
     */
    private function getDriversByIds(
        array $driverIds,
        float $latitude,
        float $longitude,
        ?string $vehicleCategoryId = null,
        bool $femaleOnly = false
    ): mixed {
        if (empty($driverIds)) {
            return collect([]);
        }
        
        $drivers = $this->userLastLocation
            ->selectRaw("*, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance",
                [$latitude, $longitude, $latitude]
            )
            ->whereIn('user_id', $driverIds)
            ->where('type', 'driver')
            ->with(['user.vehicle.category', 'driverDetails', 'user'])
            ->whereHas('user', fn($query) => $query->where('is_active', true))
            ->whereHas('driverDetails', fn($query) => $query
                ->where('is_online', true)
                ->whereNotIn('availability_status', ['unavailable', 'on_trip'])
            )
            ->when($vehicleCategoryId, function ($query) use ($vehicleCategoryId) {
                $query->whereHas('user.vehicle', fn($q) => 
                    $q->ofStatus(1)->where('category_id', $vehicleCategoryId)
                );
            })
            ->whereHas('user.vehicle', fn($query) => $query->where('is_active', true))
            ->orderBy('distance')
            ->get();
        
        if ($femaleOnly) {
            $drivers = $drivers->filter(fn($driver) => $driver->gender === 'female');
        }
        
        return $drivers;
    }

    /**
     * Get category name for honeycomb tracking
     */
    private function getCategoryName(?string $categoryId): string
    {
        if (!$categoryId) {
            return 'budget';
        }
        
        // Check cache first
        $cacheKey = "vehicle_category_level:{$categoryId}";
        $level = cache()->remember($cacheKey, 300, function () use ($categoryId) {
            $category = \DB::table('vehicle_categories')
                ->where('id', $categoryId)
                ->value('category_level');
            return $category ?? 1;
        });
        
        return match ((int)$level) {
            3 => 'vip',
            2 => 'pro',
            default => 'budget',
        };
    }
}
