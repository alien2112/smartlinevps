<?php

namespace Modules\AdminModule\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Modules\BusinessManagement\Service\Interface\SupportSavedReplyServiceInterface;
use Modules\ChattingManagement\Service\Interface\ChannelConversationServiceInterface;
use Modules\ChattingManagement\Service\Interface\ChannelListServiceInterface;
use Modules\ChattingManagement\Service\Interface\ChannelUserServiceInterface;
use Modules\ChattingManagement\Transformers\ChannelListResource;
use Modules\TransactionManagement\Service\Interface\TransactionServiceInterface;
use Modules\TripManagement\Service\Interface\SafetyAlertServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\UserManagement\Service\Interface\CustomerServiceInterface;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;
use Modules\UserManagement\Service\Interface\EmployeeServiceInterface;
use Modules\UserManagement\Service\Interface\UserAccountServiceInterface;
use Modules\ZoneManagement\Service\Interface\ZoneServiceInterface;

class DashboardController extends BaseController
{
    use AuthorizesRequests;

    protected $zoneService;
    protected $tripRequestService;
    protected $transactionService;
    protected $userAccountService;
    protected $driverService;
    protected $customerService;
    protected $employeeService;

    protected $channelListService;

    protected $channelUserService;

    protected $supportSavedReplyService;

    protected $channelConversationService;

    public function __construct(ZoneServiceInterface              $zoneService, TripRequestServiceInterface $tripRequestService,
                                TransactionServiceInterface       $transactionService, UserAccountServiceInterface $userAccountService,
                                DriverServiceInterface            $driverService, CustomerServiceInterface $customerService, EmployeeServiceInterface $employeeService,
                                ChannelListServiceInterface       $channelListService, ChannelUserServiceInterface $channelUserService,
                                SupportSavedReplyServiceInterface $supportSavedReplyService, ChannelConversationServiceInterface $channelConversationService,

    )
    {
        parent::__construct($zoneService);
        $this->zoneService = $zoneService;
        $this->tripRequestService = $tripRequestService;
        $this->transactionService = $transactionService;
        $this->userAccountService = $userAccountService;
        $this->driverService = $driverService;
        $this->customerService = $customerService;
        $this->employeeService = $employeeService;
        $this->channelListService = $channelListService;
        $this->channelUserService = $channelUserService;
        $this->supportSavedReplyService = $supportSavedReplyService;
        $this->channelConversationService = $channelConversationService;
    }

    /**
     * Get feature toggle states for dashboard
     */
    public function getFeatureToggles()
    {
        // Get AI Chatbot status
        $aiChatbot = \Modules\BusinessManagement\Entities\BusinessSetting::where([
            'key_name' => 'ai_chatbot_enable',
            'settings_type' => 'ai_config'
        ])->first();

        // Get Honeycomb status (global settings)
        $honeycomb = \Modules\DispatchManagement\Entities\HoneycombSetting::whereNull('zone_id')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'ai_chatbot' => [
                    'enabled' => $aiChatbot ? (bool)$aiChatbot->value : false,
                    'url' => route('admin.chatbot.index'),
                ],
                'honeycomb' => [
                    'enabled' => $honeycomb ? (bool)$honeycomb->enabled : false,
                    'dispatch_enabled' => $honeycomb ? (bool)$honeycomb->dispatch_enabled : false,
                    'url' => route('admin.dispatch.honeycomb.index'),
                ],
            ],
        ]);
    }

    /**
     * Toggle AI Chatbot from dashboard
     */
    public function toggleAiChatbot(Request $request)
    {
        $enabled = $request->input('enabled', false);

        \Modules\BusinessManagement\Entities\BusinessSetting::updateOrCreate(
            ['key_name' => 'ai_chatbot_enable', 'settings_type' => 'ai_config'],
            ['value' => $enabled ? 1 : 0]
        );

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'AI Chatbot enabled successfully' : 'AI Chatbot disabled successfully',
            'enabled' => $enabled,
        ]);
    }

    /**
     * Toggle Honeycomb features from dashboard
     */
    public function toggleHoneycomb(Request $request)
    {
        $feature = $request->input('feature', 'enabled');
        $enabled = $request->input('enabled', false);

        $validFeatures = ['enabled', 'dispatch_enabled'];
        if (!in_array($feature, $validFeatures)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid feature',
            ], 400);
        }

        $settings = \Modules\DispatchManagement\Entities\HoneycombSetting::firstOrCreate(
            ['zone_id' => null],
            [
                'enabled' => false,
                'dispatch_enabled' => false,
                'heatmap_enabled' => false,
                'hotspots_enabled' => false,
                'surge_enabled' => false,
                'incentives_enabled' => false,
                'updated_by' => auth()->id(),
            ]
        );

        $settings->$feature = $enabled;
        $settings->updated_by = auth()->id();
        $settings->save();

        // Clear cache if HoneycombService exists
        try {
            $honeycombService = app(\App\Services\HoneycombService::class);
            $honeycombService->clearSettingsCache(null);
        } catch (\Exception $e) {
            // Service might not exist, continue
        }

        return response()->json([
            'success' => true,
            'message' => $enabled ? ucfirst(str_replace('_', ' ', $feature)) . ' successfully' : ucfirst(str_replace('_', ' ', $feature)) . ' disabled successfully',
            'enabled' => $enabled,
        ]);
    }

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        // Cache zones for 30 minutes since they don't change frequently
        $zones = \Cache::remember('admin_dashboard_zones', 1800, function () {
            return $this->zoneService->getBy(criteria: ['is_active' => 1]);
        });

        // Cache trip metrics for 5 minutes to balance freshness and performance
        $tripMetrics = \Cache::remember('admin_dashboard_trip_metrics', 300, function () {
            return $this->tripRequestService->getDashboardAggregatedMetrics();
        });

        // Cache user transactions for 2 minutes
        $userId = \auth()->user()->id;
        $transactions = \Cache::remember("admin_dashboard_transactions_{$userId}", 120, function () use ($userId) {
            return $this->transactionService->getBy(criteria: ['user_id' => $userId], orderBy: ['created_at' => 'desc'], limit: 7);
        });

        $superAdmin = $this->employeeService->findOneBy(criteria: ['user_type' => 'super-admin']);
        $superAdminAccount = $this->userAccountService->findOneBy(criteria: ['user_id' => $superAdmin?->id]);

        // Cache customer and driver counts for 10 minutes using efficient direct COUNT queries
        $customers = \Cache::remember('admin_dashboard_customer_count', 600, function () {
            return \DB::table('users')
                ->where('user_type', CUSTOMER)
                ->whereNull('deleted_at')
                ->count();
        });

        $drivers = \Cache::remember('admin_dashboard_driver_count', 600, function () {
            return \DB::table('users')
                ->where('user_type', DRIVER)
                ->whereNull('deleted_at')
                ->count();
        });

        // Use aggregated metrics instead of separate queries
        $totalCouponAmountGiven = $tripMetrics->total_coupon ?? 0;
        $totalDiscountAmountGiven = $tripMetrics->total_discount ?? 0;
        $totalTrips = $tripMetrics->total_trips ?? 0;
        $totalParcels = $tripMetrics->total_parcels ?? 0;
        $totalEarning = $tripMetrics->total_earning ?? 0;
        $totalTripsEarning = $tripMetrics->trips_earning ?? 0;
        $totalParcelsEarning = $tripMetrics->parcels_earning ?? 0;

        return view('adminmodule::dashboard', compact('zones', 'transactions', 'superAdminAccount', 'customers',
            'drivers', 'totalDiscountAmountGiven', 'totalCouponAmountGiven', 'totalTrips', 'totalParcels', 'totalEarning', 'totalTripsEarning', 'totalParcelsEarning'));
    }

    public function recentTripActivity()
    {
        // Cache recent trip activity for 2 minutes
        $trips = \Cache::remember('admin_dashboard_recent_trips', 120, function () {
            return $this->tripRequestService->getBy(relations: ['customer', 'vehicle', 'vehicleCategory'], orderBy: ['created_at' => 'desc'], limit: 5, offset: 1);
        });
        return response()->json(view('adminmodule::partials.dashboard._recent-trip-activity', compact('trips'))->render());
    }

    public function leaderBoardDriver(Request $request)
    {
        $request->merge(['user_type' => DRIVER]);
        $params = $request->all();

        // Cache leader board for 5 minutes with unique key per date filter
        $cacheKey = 'admin_dashboard_leaderboard_driver_' . md5(json_encode($params));
        $leadDriver = \Cache::remember($cacheKey, 300, function () use ($params) {
            return $this->tripRequestService->getLeaderBoard($params, limit: 20);
        });

        return response()->json(view('adminmodule::partials.dashboard._leader-board-driver', compact('leadDriver'))->render());
    }

    public function leaderBoardCustomer(Request $request)
    {
        $request->merge(['user_type' => CUSTOMER]);
        $params = $request->all();

        // Cache leader board for 5 minutes with unique key per date filter
        $cacheKey = 'admin_dashboard_leaderboard_customer_' . md5(json_encode($params));
        $leadCustomer = \Cache::remember($cacheKey, 300, function () use ($params) {
            return $this->tripRequestService->getLeaderBoard($params, limit: 20);
        });

        return response()->json(view('adminmodule::partials.dashboard._leader-board-customer', compact('leadCustomer'))->render());
    }

    public function adminEarningStatistics(Request $request)
    {
        $params = $request->all();

        // Cache earning statistics for 5 minutes with unique key per zone and date filter
        $cacheKey = 'admin_dashboard_earning_stats_' . md5(json_encode($params));
        $data = \Cache::remember($cacheKey, 300, function () use ($params) {
            return $this->tripRequestService->getAdminZoneWiseEarning($params);
        });

        return response()->json($data);
    }


    public function zoneWiseStatistics(Request $request)
    {
        $params = $request->all();

        // Cache zone wise statistics for 5 minutes with unique key per date filter
        $cacheKey = 'admin_dashboard_zone_stats_' . md5(json_encode($params));
        $data = \Cache::remember($cacheKey, 300, function () use ($params) {
            return $this->tripRequestService->getAdminZoneWiseStatistics(data: $params);
        });

        return response()
            ->json(view('adminmodule::partials.dashboard._areawise-statistics', ['trips' => $data['zoneTripsByDate'], 'totalCount' => $data['totalTrips']])
                ->render());
    }

    public function heatMap(?Request $request)
    {
        $whereBetweenCriteria = [];
        if (array_key_exists('date_range', $request->all()) && $request['date_range']) {
            $date = getCustomDateRange($request['date_range']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
            $withCountQuery = [
                'tripRequest as ride_request' => [
                    ['type', '=', RIDE_REQUEST],
                    ['created_at', '>=', $date['start']], // Add your date range start
                    ['created_at', '<=', $date['end']],
                ],
                'tripRequest as parcel_request' => [
                    ['type', '=', PARCEL],
                    ['created_at', '>=', $date['start']], // Add your date range start
                    ['created_at', '<=', $date['end']],
                ]];
        } else {
            $withCountQuery = [
                'tripRequest as ride_request' => [
                    ['type', '=', RIDE_REQUEST]
                ],
                'tripRequest as parcel_request' => [
                    ['type', '=', PARCEL]
                ]];
        }

        $zones = $this->zoneService->index(criteria: $request?->all(), withCountQuery: $withCountQuery);
        $totalRideRequests = $zones->sum('ride_request') ?? 0;
        $totalParcelRequests = $zones->sum('parcel_request') ?? 0;
        $zoneIds = $zones->pluck('id')->toArray();
        
        // PERFORMANCE FIX: Add max limit to prevent loading excessive data
        // For heat maps, we don't need all trips - a representative sample is sufficient
        $maxHeatMapPoints = config('app.max_heatmap_points', 5000);
        
        // Use raw query for efficiency - only fetch needed columns
        // Handle empty zone array to prevent SQL errors
        $trips = collect([]);
        if (!empty($zoneIds)) {
            $trips = \DB::table('trip_requests')
                ->join('trip_request_coordinates', 'trip_requests.id', '=', 'trip_request_coordinates.trip_request_id')
                ->whereIn('trip_requests.zone_id', $zoneIds)
                ->when(!empty($whereBetweenCriteria), function ($query) use ($whereBetweenCriteria) {
                    foreach ($whereBetweenCriteria as $column => $range) {
                        $query->whereBetween('trip_requests.' . $column, $range);
                    }
                })
                ->select(
                    'trip_requests.ref_id',
                    \DB::raw('ST_AsText(trip_request_coordinates.pickup_coordinates) as pickup_coordinates')
                )
                ->limit($maxHeatMapPoints)
                ->get();
        }
        
        $markers = $trips->map(function ($trip) {
            // Handle spatial data - pickup_coordinates might be stored as WKB or JSON
            $lat = 0;
            $lng = 0;
            if ($trip->pickup_coordinates) {
                // Try to parse coordinates depending on storage format
                if (is_string($trip->pickup_coordinates)) {
                    // If stored as POINT(lng lat) or similar
                    if (preg_match('/POINT\s*\(\s*([0-9.-]+)\s+([0-9.-]+)\s*\)/i', $trip->pickup_coordinates, $matches)) {
                        $lng = (float)$matches[1];
                        $lat = (float)$matches[2];
                    }
                }
            }
            return [
                'position' => [
                    'lat' => $lat,
                    'lng' => $lng,
                ],
                'title' => "Trip Id #" . $trip->ref_id,
            ];
        })->filter(fn($m) => $m['position']['lat'] != 0 || $m['position']['lng'] != 0); // Filter out invalid coords
        
        $polygons = json_encode(formatZoneCoordinates($zones));

        $markers = json_encode($markers->values());

        // Calculate map center from zone centroids (production-grade)
        $latSum = 0;
        $lngSum = 0;
        $zoneCount = 0;

        foreach ($zones as $zone) {
            if ($zone->coordinates) {
                [$lat, $lng] = $this->calculatePolygonCentroid($zone->coordinates);
                if ($lat !== 0 || $lng !== 0) {
                    $latSum += $lat;
                    $lngSum += $lng;
                    $zoneCount++;
                }
            }
        }

        $centerLat = $zoneCount > 0 ? $latSum / $zoneCount : 30.0444; // Default to Cairo
        $centerLng = $zoneCount > 0 ? $lngSum / $zoneCount : 31.2357; // Default to Cairo

        return view('adminmodule::heat-map', compact('zones', 'totalRideRequests', 'totalParcelRequests', 'markers', 'polygons', 'centerLat', 'centerLng'));
    }

    public function heatMapOverview(Request $request)
    {
        $whereBetweenCriteria = [];
        if (array_key_exists('date_range', $request->all()) && $request['date_range']) {
            $date = getCustomDateRange($request['date_range']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        $whereInCriteria = [
            'id' => $request['zone_ids'] ?? []
        ];
        $zones = $this->zoneService->getBy(whereInCriteria: $whereInCriteria);
        $zoneIds = $zones->pluck('id')->toArray();
        
        // PERFORMANCE FIX: Use raw query with limit instead of loading all trips via Eloquent
        $maxHeatMapPoints = config('app.max_heatmap_points', 5000);
        
        // Handle empty zone array to prevent SQL errors
        $trips = collect([]);
        if (!empty($zoneIds)) {
            $trips = \DB::table('trip_requests')
                ->join('trip_request_coordinates', 'trip_requests.id', '=', 'trip_request_coordinates.trip_request_id')
                ->whereIn('trip_requests.zone_id', $zoneIds)
                ->when(!empty($whereBetweenCriteria), function ($query) use ($whereBetweenCriteria) {
                    foreach ($whereBetweenCriteria as $column => $range) {
                        $query->whereBetween('trip_requests.' . $column, $range);
                    }
                })
                ->select(
                    'trip_requests.ref_id',
                    \DB::raw('ST_AsText(trip_request_coordinates.pickup_coordinates) as pickup_coordinates')
                )
                ->limit($maxHeatMapPoints)
                ->get();
        }
        
        $markers = $trips->map(function ($trip) {
            $lat = 0;
            $lng = 0;
            if ($trip->pickup_coordinates) {
                if (is_string($trip->pickup_coordinates)) {
                    if (preg_match('/POINT\s*\(\s*([0-9.-]+)\s+([0-9.-]+)\s*\)/i', $trip->pickup_coordinates, $matches)) {
                        $lng = (float)$matches[1];
                        $lat = (float)$matches[2];
                    }
                }
            }
            return [
                'position' => [
                    'lat' => $lat,
                    'lng' => $lng,
                ],
                'title' => "Trip Id #" . $trip->ref_id,
            ];
        })->filter(fn($m) => $m['position']['lat'] != 0 || $m['position']['lng'] != 0)->values();
        
        $polygons = json_encode(formatZoneCoordinates($zones));
        $markers = json_encode($markers);

        // Calculate map center from zone centroids (production-grade)
        $latSum = 0;
        $lngSum = 0;
        $zoneCount = 0;

        foreach ($zones as $zone) {
            if ($zone->coordinates) {
                [$lat, $lng] = $this->calculatePolygonCentroid($zone->coordinates);
                if ($lat !== 0 || $lng !== 0) {
                    $latSum += $lat;
                    $lngSum += $lng;
                    $zoneCount++;
                }
            }
        }

        $centerLat = $zoneCount > 0 ? $latSum / $zoneCount : 30.0444; // Default to Cairo
        $centerLng = $zoneCount > 0 ? $lngSum / $zoneCount : 31.2357; // Default to Cairo

        return response()
            ->json(view('adminmodule::partials.heat-map._overview-map', compact('polygons', 'markers', 'centerLat', 'centerLng'))
                ->render());
    }

    public function heatMapCompare(Request $request)
    {
        $allZones = $this->zoneService->getAll();
        if (array_key_exists('zone_id', $request->all()) && $request['zone_id']) {
            $zone = $this->zoneService->findOne(id: $request['zone_id']);
        } else {
            $zone = count($allZones) ? $this->zoneService->findOne(id: $allZones[0]->id) : null;
        }
        if (array_key_exists('date_range', $request->all()) && $request['date_range']) {
            $dateRange = $request['date_range'];
            $date = getCustomDateRange($request['date_range']);
        } else {
            $firstTripRequest = $this->tripRequestService->findOneBy(criteria: ['zone_id' => $zone?->id], orderBy: ['created_at' => 'asc']);
            $todayStart = $zone ? Carbon::parse($firstTripRequest?->created_at)->format('m/d/Y') : Carbon::today()->format('m/d/Y'); // Start of today
            $todayEnd = Carbon::today()->format('m/d/Y');
            $dateRange = "{$todayStart} - {$todayEnd}";
            $date = getCustomDateRange("{$todayStart} - {$todayEnd}");
        }
        $startDate = $date['start'];
        $endDate = $date['end'];
        $whereBetweenCriteria = [
            'created_at' => [$startDate, $endDate],
        ];

        // PERFORMANCE FIX: Use count query instead of loading all trips just to count
        $tripCount = \DB::table('trip_requests')
            ->where('zone_id', $zone?->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
            
        $dateWiseTrips = $this->tripRequestService->getTripHeatMapCompareDataBy(data: ['zone_id' => $zone?->id, 'date_range' => $dateRange]);
        
        // PERFORMANCE FIX: Cap markers per time period to prevent browser crash
        $maxMarkersPerPeriod = config('app.max_heatmap_points_per_period', 1000);
        
        if ($dateWiseTrips->isNotEmpty()) {
            $markers = [];
            foreach ($dateWiseTrips as $dateWiseTrip) {
                if (isset($dateWiseTrip->month) && isset($dateWiseTrip->year)) {
                    $markerKey = $dateWiseTrip->month;
                    if ($startDate->month < $dateWiseTrip->month && $endDate->month == $dateWiseTrip->month) {
                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay();
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay(), Carbon::create($endDate)],
                        ];
                    } elseif ($startDate->month < $dateWiseTrip->month && $endDate->month > $dateWiseTrip->month) {
                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay();
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->startOfDay(), Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay()],
                        ];
                    } elseif ($startDate->month == $dateWiseTrip->month && $endDate->month > $dateWiseTrip->month) {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::createFromDate($dateWiseTrip->year, $dateWiseTrip->month, 1)->endOfMonth()->endOfDay()],
                        ];
                    } else {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::create($endDate)],
                        ];
                    }
                } elseif (isset($dateWiseTrip->year)) {
                    $markerKey = $dateWiseTrip->year;

                    if ($startDate->year < $dateWiseTrip->year && $endDate->year == $dateWiseTrip->year) {
                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay();
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay(), Carbon::create($endDate)],
                        ];
                    } elseif ($startDate->year < $dateWiseTrip->year && $endDate->year > $dateWiseTrip->year) {

                        $showStartDate = Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay();
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::createFromDate($dateWiseTrip->year, 1, 1)->startOfDay(), Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay()],
                        ];

                    } elseif ($startDate->year == $dateWiseTrip->year && $endDate->year > $dateWiseTrip->year) {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay();
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::createFromDate($dateWiseTrip->year, 12, 31)->endOfDay()],
                        ];
                    } else {
                        $showStartDate = Carbon::create($startDate);
                        $showEndDate = Carbon::create($endDate);
                        $whereMarkerBetweenCriteria = [
                            'created_at' => [Carbon::create($startDate), Carbon::create($endDate)],
                        ];
                    }
                } elseif (isset($dateWiseTrip->hour)) {
                    $showStartDate = Carbon::create($dateWiseTrip->date)->setTime($dateWiseTrip->hour, 0);
                    $showEndDate = $showStartDate->copy()->addMinutes(59)->addSeconds(59);
                    $markerKey = $dateWiseTrip->hour;
                    $whereMarkerBetweenCriteria = [
                        'created_at' => [$showStartDate, $showEndDate],
                    ];
                } else {
                    $markerKey = $dateWiseTrip->date;
                    $showStartDate = Carbon::create($dateWiseTrip->date)->startOfDay();
                    $showEndDate = Carbon::create($dateWiseTrip->date)->endOfDay();
                    $whereMarkerBetweenCriteria = [
                        'created_at' => [Carbon::create($dateWiseTrip->date)->startOfDay(), Carbon::create($dateWiseTrip->date)->endOfDay()],
                    ];

                }
                $dateWiseTrip->startDate = $showStartDate;
                $dateWiseTrip->endDate = $showEndDate;
                $dateWiseTrip->markerKey = $markerKey;
                
                // PERFORMANCE FIX: Use raw query with limit instead of loading all trips via Eloquent
                $periodStart = $whereMarkerBetweenCriteria['created_at'][0];
                $periodEnd = $whereMarkerBetweenCriteria['created_at'][1];
                
                $trips = \DB::table('trip_requests')
                    ->join('trip_request_coordinates', 'trip_requests.id', '=', 'trip_request_coordinates.trip_request_id')
                    ->where('trip_requests.zone_id', $zone?->id)
                    ->whereBetween('trip_requests.created_at', [$periodStart, $periodEnd])
                    ->select(
                        'trip_requests.ref_id',
                        \DB::raw('ST_AsText(trip_request_coordinates.pickup_coordinates) as pickup_coordinates')
                    )
                    ->limit($maxMarkersPerPeriod)
                    ->get();
                
                $mappedMarkers = $trips->map(function ($trip) {
                    $lat = 0;
                    $lng = 0;
                    if ($trip->pickup_coordinates) {
                        if (is_string($trip->pickup_coordinates)) {
                            if (preg_match('/POINT\s*\(\s*([0-9.-]+)\s+([0-9.-]+)\s*\)/i', $trip->pickup_coordinates, $matches)) {
                                $lng = (float)$matches[1];
                                $lat = (float)$matches[2];
                            }
                        }
                    }
                    return [
                        'position' => [
                            'lat' => $lat,
                            'lng' => $lng,
                        ],
                        'title' => "Trip Id #" . $trip->ref_id,
                    ];
                })->filter(fn($m) => $m['position']['lat'] != 0 || $m['position']['lng'] != 0)->values();
                
                $markers[$markerKey] = $mappedMarkers;
            }
        } else {
            $markers = [];
        }
        // Calculate center lat/lng
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = 0;
        $polygons = $zone ? json_encode([formatCoordinates(json_decode($zone?->coordinates[0]->toJson(), true)['coordinates'])]) : json_encode([[]]);
        if ($zone) {
            foreach (formatCoordinates(json_decode($zone?->coordinates[0]->toJson(), true)['coordinates']) as $point) {
                $latSum += $point->lat;
                $lngSum += $point->lng;
                $totalPoints++;
            }
        }
        $centerLat = $latSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $centerLng = $lngSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $tripStatisticsData = $this->tripRequestService->getTripHeatMapCompareZoneDateWiseEarningStatistics(data: ['zone_id' => $zone?->id, 'date_range' => $dateRange]);
        return view('adminmodule::heat-map-compare',
            compact('allZones', 'zone',
                'dateRange', 'tripCount', 'polygons', 'markers', 'centerLat',
                'centerLng', 'dateWiseTrips', 'tripStatisticsData'));

    }

    public function chatting(Request $request)
    {
        $this->authorize('chatting_view');
        $driverList = $this->driverService->getChattingDriverList(data: $request->all());
        if ($driverList->isEmpty() && (!isset($request->search) || $request->search == '')) {
            $driverList = $this->driverService->getBy(criteria: ['user_type' => DRIVER, 'is_active' => 1], limit: 30);
        }
        $savedReplies = $this->supportSavedReplyService->getBy(criteria: ['is_active' => 1]);

        return view('adminmodule::chatting', compact('driverList', 'savedReplies'));

    }

    public function getDriverConversation($channelId, Request $request)
    {
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $this->channelConversationService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $conversations = $this->channelConversationService->getBy(criteria: ['channel_id' => $channelId], relations: ['user', 'conversation_files'], orderBy: ['id' => 'desc']);
        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->driverId, 'user_type' => DRIVER], withTrashed: true);
        return response()
            ->json(view('adminmodule::partials.chatting._conversation', compact('conversations', 'driver', 'channelId'))
                ->render());
    }

    public function searchDriversList(Request $request)
    {
        $driverList = $this->driverService->getChattingDriverList(data: $request->all());
        if ($driverList->isEmpty() && isset($request->search) && $request->search != '') {
            $driverList = $this->driverService->getBy(
                criteria: ['user_type' => DRIVER, 'is_active' => 1],
                searchCriteria: [
                    'fields' => ['full_name', 'first_name', 'last_name', 'phone'],
                    'value' => $request->search
                ],
                limit: 30
            );
        }

        return response()
            ->json(view('adminmodule::partials.chatting._search-drivers', compact('driverList'))
                ->render());
    }

    public function searchSavedTopicAnswer(Request $request)
    {

        $searchCriteria = [];
        if (array_key_exists('search', $request->all())) {
            $searchCriteria = [
                'fields' => ['topic'],
                'value' => $request->search,
            ];
        }

        $savedReplies = $this->supportSavedReplyService->getBy(criteria: ['is_active' => 1], searchCriteria: $searchCriteria);

        return response()
            ->json(view('adminmodule::partials.chatting._saved-answer', compact('savedReplies'))
                ->render());
    }

    public function sendMessageToDriver(Request $request)
    {
        $fileImage = [];
        if ($request->has('file')) {
            $fileImage = array_merge($fileImage, $request->file('file'));
        }
        if ($request->has('image')) {
            $fileImage = array_merge($fileImage, $request->file('image'));
        }
        $data = [
            'channel_id' => $request->channelId,
            'user_id' => auth()->user()->id,
            'message' => $request->message,
            'is_read' => 0,
            'files' => $fileImage,
        ];
        $this->channelConversationService->create(data: $data);
        $channelDriver = $this->channelUserService->findOneBy(criteria: ['channel_id' => $request->channelId, 'user_id' => $request->driverId], relations: ['user']);
        sendDeviceNotification(fcm_token: $channelDriver?->user?->fcm_token,
            title: translate("New Message"),
            description: translate("You have a new message from Admin"),
            status: 1,
            ride_request_id: $request->driverId,
            type: $request->channelId,
            action: 'admin_message',
            user_id: $request->driverId
        );

        $channelId = $request->channelId;
        $this->channelUserService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $this->channelConversationService->updatedBy(criteria: ['channel_id' => $channelId, 'user_id' => $request->driverId, 'is_read' => 0], data: ['is_read' => 1]);
        $conversations = $this->channelConversationService->getBy(criteria: ['channel_id' => $channelId], relations: ['user', 'conversation_files'], orderBy: ['created_at' => 'desc']);
        $driver = $this->driverService->findOneBy(criteria: ['id' => $request->driverId, 'user_type' => DRIVER], withTrashed: true);


        return response()
            ->json(view('adminmodule::partials.chatting._conversation', compact('conversations', 'driver', 'channelId'))
                ->render());

    }

    public function createChannelWithAdmin(Request $request)
    {
        $channelIds = $this->channelUserService->getBy(criteria: ['user_id' => $request->driverId]);
        $channelIds = $channelIds->pluck('channel_id')->toArray();

        $whereInCriteria = [
            'channel_id' => $channelIds
        ];
        $criteria = [
            'user_id' => auth()->user()->id,
        ];

        $channelUser = $this->channelUserService->findOneBy(criteria: $criteria, whereInCriteria: $whereInCriteria);
        if ($channelUser) {
            $findChannel = $this->channelListService->findOne($channelUser?->channel_id);
            if ($findChannel) {
                $findChannel = $this->channelListService->update(id: $findChannel?->id, data: $request->all());
                return response()->json(responseFormatter(DEFAULT_200, ['user' => auth()->user(), 'channel' => ChannelListResource::make($findChannel)]), 200);
            }
        }
        $channel = $this->channelListService->createChannelWithAdmin(data: ['to' => $request->driverId]);
        return response()->json(responseFormatter(DEFAULT_STORE_200, ['user' => auth()->user(), 'channel' => ChannelListResource::make($channel)]), 200);
    }

    /**
     * Clear dashboard cache manually
     */
    public function clearCache()
    {
        $result = \App\Services\AdminDashboardCacheService::clearDashboardCache();

        if ($result['success']) {
            return redirect()->back()->with('success', $result['message']);
        }

        return redirect()->back()->with('error', $result['message']);
    }

    /**
     * Production-grade helper to extract [lat, lng] from any geometry point format
     *
     * Supports:
     * - MySQL Spatial arrays: [lng, lat]
     * - GeoJSON objects: { latitude: X, longitude: Y }
     * - Alternative formats: { lat: X, lng: Y }
     * - Point objects with properties
     *
     * @param mixed $point The point data in any supported format
     * @return array|null Returns [lat, lng] or null if invalid
     */
    private function getLatLng($point): ?array
    {
        // Handle null/empty
        if (!$point) {
            return null;
        }

        // Case 1: Array format [lng, lat] or [lat, lng]
        if (is_array($point)) {
            // MySQL spatial returns [longitude, latitude]
            if (count($point) >= 2 && is_numeric($point[0]) && is_numeric($point[1])) {
                // Assume [lng, lat] format (MySQL standard)
                return [(float)$point[1], (float)$point[0]];
            }
            return null;
        }

        // Case 2: Object with latitude/longitude properties
        if (is_object($point)) {
            // Try latitude/longitude
            if (isset($point->latitude) && isset($point->longitude)) {
                return [(float)$point->latitude, (float)$point->longitude];
            }
            // Try lat/lng
            if (isset($point->lat) && isset($point->lng)) {
                return [(float)$point->lat, (float)$point->lng];
            }
        }

        return null;
    }

    /**
     * Calculate centroid from zone polygon coordinates
     *
     * @param mixed $coordinates The zone coordinates (Polygon object or array)
     * @return array Returns [lat, lng] or [0, 0] if invalid
     */
    private function calculatePolygonCentroid($coordinates): array
    {
        if (!$coordinates) {
            return [0, 0];
        }

        $latSum = 0;
        $lngSum = 0;
        $pointCount = 0;

        // Extract points from Polygon object
        $points = [];
        if (is_object($coordinates) && method_exists($coordinates, 'getCoordinates')) {
            $points = $coordinates->getCoordinates()[0] ?? [];
        } elseif (is_array($coordinates)) {
            $points = $coordinates[0] ?? $coordinates;
        }

        // Process each point
        foreach ($points as $point) {
            $latLng = $this->getLatLng($point);
            if ($latLng) {
                [$lat, $lng] = $latLng;
                $latSum += $lat;
                $lngSum += $lng;
                $pointCount++;
            }
        }

        // Return centroid or [0, 0] if no valid points
        if ($pointCount === 0) {
            return [0, 0];
        }

        return [$latSum / $pointCount, $lngSum / $pointCount];
    }

}
