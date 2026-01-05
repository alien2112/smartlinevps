<?php

namespace Modules\DispatchManagement\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\HoneycombService;
use Modules\DispatchManagement\Entities\HoneycombSetting;
use Modules\DispatchManagement\Entities\HoneycombCellMetric;

/**
 * Admin Honeycomb Controller
 * 
 * Manages honeycomb dispatch settings, heatmaps, and analytics.
 */
class HoneycombController extends Controller
{
    public function __construct(
        private HoneycombService $honeycombService
    ) {}

    /**
     * GET /admin/dispatch/honeycomb/settings
     * 
     * Get honeycomb settings for a zone
     */
    public function getSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $zoneId = $request->input('zone_id');
        
        $settings = HoneycombSetting::query()
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId))
            ->when(!$zoneId, fn($q) => $q->whereNull('zone_id'))
            ->with('zone', 'updatedBy')
            ->first();

        if (!$settings) {
            // Return defaults
            $settings = new HoneycombSetting([
                'zone_id' => $zoneId,
                'enabled' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'settings' => $settings,
                'resolutions' => HoneycombSetting::H3_RESOLUTIONS,
            ],
        ]);
    }

    /**
     * PUT /admin/dispatch/honeycomb/settings
     * 
     * Update honeycomb settings for a zone
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'nullable|uuid',
            'city_name' => 'nullable|string|max:100',
            'enabled' => 'required|boolean',
            'dispatch_enabled' => 'required|boolean',
            'heatmap_enabled' => 'required|boolean',
            'hotspots_enabled' => 'required|boolean',
            'surge_enabled' => 'required|boolean',
            'incentives_enabled' => 'required|boolean',
            'h3_resolution' => 'required|integer|in:7,8,9',
            'search_depth_k' => 'required|integer|min:1|max:3',
            'update_interval_seconds' => 'required|integer|min:30|max:300',
            'min_drivers_to_color_cell' => 'required|integer|min:1|max:10',
            'surge_threshold' => 'required|numeric|min:1|max:5',
            'surge_cap' => 'required|numeric|min:1|max:3',
            'surge_step' => 'required|numeric|min:0.05|max:0.5',
            'incentive_threshold' => 'required|numeric|min:1|max:5',
            'max_incentive_amount' => 'required|numeric|min:0|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            DB::beginTransaction();

            $zoneId = $request->input('zone_id');
            
            $settings = HoneycombSetting::updateOrCreate(
                [
                    'zone_id' => $zoneId,
                ],
                [
                    'city_name' => $request->input('city_name'),
                    'enabled' => $request->input('enabled'),
                    'dispatch_enabled' => $request->input('dispatch_enabled'),
                    'heatmap_enabled' => $request->input('heatmap_enabled'),
                    'hotspots_enabled' => $request->input('hotspots_enabled'),
                    'surge_enabled' => $request->input('surge_enabled'),
                    'incentives_enabled' => $request->input('incentives_enabled'),
                    'h3_resolution' => $request->input('h3_resolution'),
                    'search_depth_k' => $request->input('search_depth_k'),
                    'update_interval_seconds' => $request->input('update_interval_seconds'),
                    'min_drivers_to_color_cell' => $request->input('min_drivers_to_color_cell'),
                    'surge_threshold' => $request->input('surge_threshold'),
                    'surge_cap' => $request->input('surge_cap'),
                    'surge_step' => $request->input('surge_step'),
                    'incentive_threshold' => $request->input('incentive_threshold'),
                    'max_incentive_amount' => $request->input('max_incentive_amount'),
                    'updated_by' => auth()->id(),
                ]
            );

            // Clear Redis cache and notify Node.js
            $this->honeycombService->clearSettingsCache($zoneId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Honeycomb settings updated successfully',
                'data' => $settings,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update settings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /admin/dispatch/honeycomb/heatmap
     * 
     * Get current heatmap data for a zone
     */
    public function getHeatmap(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|uuid',
            'window' => 'nullable|string|in:5m,10m,15m,30m',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $zoneId = $request->input('zone_id');
        $window = $request->input('window', '5m');
        $windowMinutes = (int)str_replace('m', '', $window);

        $heatmap = $this->honeycombService->getHeatmap($zoneId, $windowMinutes);

        return response()->json([
            'success' => true,
            'data' => [
                'cells' => $heatmap->values()->toArray(),
                'total_cells' => $heatmap->count(),
                'total_supply' => $heatmap->sum('supply'),
                'total_demand' => $heatmap->sum('demand'),
                'average_imbalance' => round($heatmap->avg('imbalance') ?? 0, 2),
                'hotspot_count' => $heatmap->where('imbalance', '>', 1.5)->count(),
                'window_minutes' => $windowMinutes,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /admin/dispatch/honeycomb/analytics
     * 
     * Get historical honeycomb analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|uuid',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $zoneId = $request->input('zone_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get aggregated metrics from database
        $metrics = HoneycombCellMetric::query()
            ->where('zone_id', $zoneId)
            ->whereBetween('window_start', [$startDate, $endDate])
            ->selectRaw('
                DATE(window_start) as date,
                AVG(supply_total) as avg_supply,
                AVG(demand_total) as avg_demand,
                AVG(imbalance_score) as avg_imbalance,
                MAX(imbalance_score) as max_imbalance,
                COUNT(DISTINCT h3_index) as active_cells
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Get top hotspots over the period
        $hotspots = HoneycombCellMetric::query()
            ->where('zone_id', $zoneId)
            ->whereBetween('window_start', [$startDate, $endDate])
            ->selectRaw('
                h3_index,
                center_lat,
                center_lng,
                AVG(imbalance_score) as avg_imbalance,
                SUM(demand_total) as total_demand,
                SUM(supply_total) as total_supply
            ')
            ->groupBy('h3_index', 'center_lat', 'center_lng')
            ->orderByDesc('avg_imbalance')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'daily_metrics' => $metrics,
                'top_hotspots' => $hotspots,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
            ],
        ]);
    }

    /**
     * GET /admin/dispatch/honeycomb/zones
     * 
     * List all zones with their honeycomb status
     */
    public function listZones(Request $request): JsonResponse
    {
        $zones = DB::table('zones')
            ->leftJoin('dispatch_honeycomb_settings', 'zones.id', '=', 'dispatch_honeycomb_settings.zone_id')
            ->select([
                'zones.id',
                'zones.name',
                'dispatch_honeycomb_settings.enabled',
                'dispatch_honeycomb_settings.dispatch_enabled',
                'dispatch_honeycomb_settings.heatmap_enabled',
                'dispatch_honeycomb_settings.surge_enabled',
                'dispatch_honeycomb_settings.h3_resolution',
            ])
            ->whereNull('zones.deleted_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * POST /admin/dispatch/honeycomb/toggle
     * 
     * Quick toggle for enabling/disabling honeycomb
     */
    public function toggle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'nullable|uuid',
            'feature' => 'required|string|in:enabled,dispatch_enabled,heatmap_enabled,hotspots_enabled,surge_enabled,incentives_enabled',
            'value' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $zoneId = $request->input('zone_id');
        $feature = $request->input('feature');
        $value = $request->input('value');

        $settings = HoneycombSetting::firstOrCreate(
            ['zone_id' => $zoneId],
            ['updated_by' => auth()->id()]
        );

        $settings->$feature = $value;
        $settings->updated_by = auth()->id();
        $settings->save();

        // Clear cache and notify
        $this->honeycombService->clearSettingsCache($zoneId);

        return response()->json([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $feature)) . ' ' . ($value ? 'enabled' : 'disabled'),
            'data' => $settings,
        ]);
    }

    /**
     * GET /admin/dispatch/honeycomb/preview
     * 
     * Preview what the honeycomb grid would look like for given coordinates
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'resolution' => 'required|integer|in:7,8,9',
            'search_depth_k' => 'required|integer|min:1|max:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $resolution = $request->input('resolution');
        $searchDepth = $request->input('search_depth_k');

        $centerCell = $this->honeycombService->latLngToH3($lat, $lng, $resolution);
        $searchCells = $this->honeycombService->kRing($centerCell, $searchDepth);

        $cells = collect($searchCells)->map(function ($h3Index) {
            $center = $this->honeycombService->h3ToLatLng($h3Index);
            return [
                'h3_index' => $h3Index,
                'center' => $center,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'center_cell' => $centerCell,
                'cells' => $cells->toArray(),
                'total_cells' => count($searchCells),
                'resolution_info' => HoneycombSetting::H3_RESOLUTIONS[$resolution] ?? null,
            ],
        ]);
    }
}
