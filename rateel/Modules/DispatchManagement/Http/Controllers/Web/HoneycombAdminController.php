<?php

namespace Modules\DispatchManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\HoneycombService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\DispatchManagement\Entities\HoneycombSetting;
use Modules\ZoneManagement\Entities\Zone;

/**
 * Admin Controller for Honeycomb Dispatch Management
 * 
 * Web-based admin panel for managing honeycomb settings
 */
class HoneycombAdminController extends Controller
{
    public function __construct(
        private HoneycombService $honeycombService
    ) {}

    /**
     * Display honeycomb settings page
     */
    public function index(Request $request)
    {
        $zoneId = $request->get('zone_id');
        
        // Get all zones for dropdown
        $zones = Zone::whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        // Get settings for selected zone or global
        $settings = HoneycombSetting::query()
            ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId))
            ->when(!$zoneId, fn($q) => $q->whereNull('zone_id'))
            ->with('zone', 'updatedBy')
            ->first();
        
        if (!$settings) {
            $settings = new HoneycombSetting([
                'zone_id' => $zoneId,
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
            ]);
        }
        
        // Get zone statistics
        $zoneStats = [];
        foreach ($zones as $zone) {
            $zoneSettings = HoneycombSetting::where('zone_id', $zone->id)->first();
            $zoneStats[$zone->id] = [
                'name' => $zone->name,
                'enabled' => $zoneSettings?->enabled ?? false,
                'dispatch_enabled' => $zoneSettings?->dispatch_enabled ?? false,
                'heatmap_enabled' => $zoneSettings?->heatmap_enabled ?? false,
            ];
        }
        
        return view('dispatchmanagement::admin.honeycomb.index', [
            'settings' => $settings,
            'zones' => $zones,
            'zoneStats' => $zoneStats,
            'selectedZoneId' => $zoneId,
            'resolutions' => HoneycombSetting::H3_RESOLUTIONS,
        ]);
    }

    /**
     * Update honeycomb settings
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'nullable|uuid',
            'city_name' => 'nullable|string|max:100',
            'enabled' => 'nullable',
            'dispatch_enabled' => 'nullable',
            'heatmap_enabled' => 'nullable',
            'hotspots_enabled' => 'nullable',
            'surge_enabled' => 'nullable',
            'incentives_enabled' => 'nullable',
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
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            DB::beginTransaction();

            $zoneId = $request->input('zone_id') ?: null;
            
            HoneycombSetting::updateOrCreate(
                ['zone_id' => $zoneId],
                [
                    'city_name' => $request->input('city_name'),
                    'enabled' => $request->has('enabled'),
                    'dispatch_enabled' => $request->has('dispatch_enabled'),
                    'heatmap_enabled' => $request->has('heatmap_enabled'),
                    'hotspots_enabled' => $request->has('hotspots_enabled'),
                    'surge_enabled' => $request->has('surge_enabled'),
                    'incentives_enabled' => $request->has('incentives_enabled'),
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

            return redirect()->back()->with('success', 'تم تحديث إعدادات الخلية بنجاح');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'فشل تحديث الإعدادات: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Toggle a specific honeycomb feature
     */
    public function toggle(Request $request)
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

        $zoneId = $request->input('zone_id') ?: null;
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
            'message' => 'تم تحديث الحالة بنجاح',
        ]);
    }

    /**
     * Display heatmap page
     */
    public function heatmap(Request $request)
    {
        $zoneId = $request->get('zone_id');
        
        $zones = Zone::whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $heatmapData = [];
        $stats = [
            'total_cells' => 0,
            'total_supply' => 0,
            'total_demand' => 0,
            'hotspot_count' => 0,
        ];
        
        if ($zoneId) {
            $heatmap = $this->honeycombService->getHeatmap($zoneId);
            $heatmapData = $heatmap->values()->toArray();
            $stats = [
                'total_cells' => $heatmap->count(),
                'total_supply' => $heatmap->sum('supply'),
                'total_demand' => $heatmap->sum('demand'),
                'hotspot_count' => $heatmap->where('imbalance', '>', 1.5)->count(),
                'average_imbalance' => round($heatmap->avg('imbalance') ?? 0, 2),
            ];
        }
        
        return view('dispatchmanagement::admin.honeycomb.heatmap', [
            'zones' => $zones,
            'selectedZoneId' => $zoneId,
            'heatmapData' => $heatmapData,
            'stats' => $stats,
        ]);
    }

    /**
     * Get heatmap data via AJAX
     */
    public function getHeatmapData(Request $request)
    {
        $zoneId = $request->get('zone_id');
        
        if (!$zoneId) {
            return response()->json(['success' => false, 'message' => 'Zone required']);
        }
        
        $heatmap = $this->honeycombService->getHeatmap($zoneId);
        
        return response()->json([
            'success' => true,
            'data' => [
                'cells' => $heatmap->values()->toArray(),
                'stats' => [
                    'total_cells' => $heatmap->count(),
                    'total_supply' => $heatmap->sum('supply'),
                    'total_demand' => $heatmap->sum('demand'),
                    'hotspot_count' => $heatmap->where('imbalance', '>', 1.5)->count(),
                ],
            ],
        ]);
    }
}
