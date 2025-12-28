<?php

namespace Modules\DispatchManagement\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\HoneycombService;

/**
 * Driver Honeycomb API Controller
 * 
 * Provides honeycomb-related data for the driver app:
 * - Hotspots (high-demand areas)
 * - Current cell stats
 * - Suggested repositioning
 */
class HoneycombController extends Controller
{
    public function __construct(
        private HoneycombService $honeycombService
    ) {}

    /**
     * GET /driver/honeycomb/hotspots
     * 
     * Get top hotspots (high-demand cells) for the driver's zone
     */
    public function getHotspots(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|uuid',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $zoneId = $request->input('zone_id');
        $limit = $request->input('limit', 5);
        $lat = $request->input('lat');
        $lng = $request->input('lng');

        $hotspots = $this->honeycombService->getHotspots($zoneId, $limit);

        // If driver location provided, add distance to each hotspot
        if ($lat && $lng) {
            $hotspots = $hotspots->map(function ($hotspot) use ($lat, $lng) {
                $hotspot['distance_km'] = $this->haversineDistance(
                    $lat, $lng,
                    $hotspot['center']['lat'],
                    $hotspot['center']['lng']
                );
                return $hotspot;
            })->sortBy('distance_km');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'hotspots' => $hotspots->values()->toArray(),
                'total' => $hotspots->count(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /driver/honeycomb/cell
     * 
     * Get driver's current cell stats and suggested move direction
     */
    public function getCellStats(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'zone_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $zoneId = $request->input('zone_id');

        $stats = $this->honeycombService->getCellStats($lat, $lng, $zoneId);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * GET /driver/honeycomb/heatmap
     * 
     * Get simplified heatmap for driver app visualization
     */
    public function getHeatmap(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|uuid',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $zoneId = $request->input('zone_id');
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radiusKm = $request->input('radius_km', 5);

        $heatmap = $this->honeycombService->getHeatmap($zoneId);

        // If location provided, filter to nearby cells only
        if ($lat && $lng) {
            $heatmap = $heatmap->filter(function ($cell) use ($lat, $lng, $radiusKm) {
                $distance = $this->haversineDistance(
                    $lat, $lng,
                    $cell['center']['lat'],
                    $cell['center']['lng']
                );
                return $distance <= $radiusKm;
            });
        }

        // Simplify data for driver app (reduce payload)
        $simplifiedHeatmap = $heatmap->map(function ($cell) {
            return [
                'h3' => $cell['h3_index'],
                'lat' => $cell['center']['lat'],
                'lng' => $cell['center']['lng'],
                'intensity' => round($cell['intensity'], 2),
                'demand' => $cell['demand'],
                'hotspot' => $cell['imbalance'] > 1.5 && $cell['demand'] >= 2,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'cells' => $simplifiedHeatmap->values()->toArray(),
                'total' => $simplifiedHeatmap->count(),
            ],
        ]);
    }

    /**
     * GET /driver/honeycomb/surge
     * 
     * Get current surge multiplier for driver's location
     */
    public function getSurge(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'zone_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $zoneId = $request->input('zone_id');

        $surge = $this->honeycombService->getSurgeMultiplier($lat, $lng, $zoneId);

        return response()->json([
            'success' => true,
            'data' => [
                'surge_multiplier' => $surge,
                'is_surge' => $surge > 1.0,
            ],
        ]);
    }

    /**
     * Calculate Haversine distance between two points
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return round($earthRadius * $c, 2);
    }
}
