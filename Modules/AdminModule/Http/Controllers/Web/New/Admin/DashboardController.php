<?php

namespace Modules\AdminModule\Http\Controllers\Web\New\Admin;

use App\Http\Controllers\BaseController;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
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

    public function index(?Request $request, string $type = null): View|Collection|LengthAwarePaginator|null|callable|RedirectResponse
    {
        // Cache dashboard statistics for 5 minutes to reduce database load
        $cacheKey = 'admin_dashboard_stats';
        $cacheTTL = 300; // 5 minutes

        $dashboardStats = Cache::remember($cacheKey, $cacheTTL, function () {
            // Use efficient raw DB queries instead of loading entire collections
            $customerCount = \DB::table('users')
                ->where('user_type', CUSTOMER)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count();

            $driverCount = \DB::table('users')
                ->where('user_type', DRIVER)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count();

            // Efficient aggregated query for trip stats
            $tripStats = \DB::table('trip_requests')
                ->selectRaw('
                    SUM(CASE WHEN payment_status = "paid" THEN coupon_amount ELSE 0 END) as total_coupon,
                    SUM(CASE WHEN payment_status = "paid" THEN discount_amount ELSE 0 END) as total_discount,
                    SUM(CASE WHEN type = "ride_request" THEN 1 ELSE 0 END) as total_trips,
                    SUM(CASE WHEN type = "parcel" THEN 1 ELSE 0 END) as total_parcels
                ')
                ->first();

            // Earning stats with fee join
            $earningStats = \DB::table('trip_requests')
                ->leftJoin('trip_request_fees', 'trip_requests.id', '=', 'trip_request_fees.trip_request_id')
                ->whereRaw('trip_requests.payment_status = "paid"')
                ->whereRaw('(trip_request_fees.cancelled_by IS NULL OR trip_request_fees.cancelled_by = "CUSTOMER")')
                ->selectRaw('
                    SUM(trip_request_fees.admin_commission) as total_earning,
                    SUM(CASE WHEN trip_requests.type = "ride_request" THEN trip_request_fees.admin_commission ELSE 0 END) as trips_earning,
                    SUM(CASE WHEN trip_requests.type = "parcel" THEN trip_request_fees.admin_commission ELSE 0 END) as parcels_earning
                ')
                ->first();

            return [
                'customers' => $customerCount,
                'drivers' => $driverCount,
                'totalCouponAmountGiven' => $tripStats->total_coupon ?? 0,
                'totalDiscountAmountGiven' => $tripStats->total_discount ?? 0,
                'totalTrips' => $tripStats->total_trips ?? 0,
                'totalParcels' => $tripStats->total_parcels ?? 0,
                'totalEarning' => $earningStats->total_earning ?? 0,
                'totalTripsEarning' => $earningStats->trips_earning ?? 0,
                'totalParcelsEarning' => $earningStats->parcels_earning ?? 0,
            ];
        });

        // Cache zones for longer (15 minutes) as they change less frequently
        $zones = Cache::remember('admin_dashboard_zones', 900, function () {
            return $this->zoneService->getBy(criteria: ['is_active' => 1]);
        });

        // User-specific data should not be cached
        $transactions = $this->transactionService->getBy(criteria: ['user_id' => \auth()->user()->id], orderBy: ['created_at' => 'desc'])->take(7);

        // Cache super admin account for 15 minutes
        $superAdminAccount = Cache::remember('admin_dashboard_super_admin_account', 900, function () {
            $superAdmin = $this->employeeService->findOneBy(criteria: ['user_type' => 'super-admin']);
            return $this->userAccountService->findOneBy(criteria: ['user_id' => $superAdmin?->id]);
        });

        // Extract cached values
        $customers = $dashboardStats['customers'];
        $drivers = $dashboardStats['drivers'];
        $totalCouponAmountGiven = $dashboardStats['totalCouponAmountGiven'];
        $totalDiscountAmountGiven = $dashboardStats['totalDiscountAmountGiven'];
        $totalTrips = $dashboardStats['totalTrips'];
        $totalParcels = $dashboardStats['totalParcels'];
        $totalEarning = $dashboardStats['totalEarning'];
        $totalTripsEarning = $dashboardStats['totalTripsEarning'];
        $totalParcelsEarning = $dashboardStats['totalParcelsEarning'];

        return view('adminmodule::dashboard', compact('zones', 'transactions', 'superAdminAccount', 'customers',
            'drivers', 'totalDiscountAmountGiven', 'totalCouponAmountGiven', 'totalTrips', 'totalParcels', 'totalEarning', 'totalTripsEarning', 'totalParcelsEarning'));
    }

    public function recentTripActivity()
    {
        // Cache recent trip activity for 2 minutes
        $trips = Cache::remember('admin_recent_trip_activity', 120, function () {
            return $this->tripRequestService->getBy(relations: ['customer', 'vehicle', 'vehicleCategory'], orderBy: ['created_at' => 'desc'], limit: 5, offset: 1);
        });
        return response()->json(view('adminmodule::partials.dashboard._recent-trip-activity', compact('trips'))->render());
    }

    public function leaderBoardDriver(Request $request)
    {
        $request->merge(['user_type' => DRIVER]);
        // Cache driver leaderboard for 5 minutes with request params
        $cacheKey = 'admin_leaderboard_driver_' . md5(serialize($request->all()));
        $leadDriver = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->tripRequestService->getLeaderBoard($request->all(), limit: 20);
        });
        return response()->json(view('adminmodule::partials.dashboard._leader-board-driver', compact('leadDriver'))->render());
    }

    public function leaderBoardCustomer(Request $request)
    {
        $request->merge(['user_type' => CUSTOMER]);
        // Cache customer leaderboard for 5 minutes with request params
        $cacheKey = 'admin_leaderboard_customer_' . md5(serialize($request->all()));
        $leadCustomer = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->tripRequestService->getLeaderBoard($request->all(), limit: 20);
        });
        return response()->json(view('adminmodule::partials.dashboard._leader-board-customer', compact('leadCustomer'))->render());
    }

    public function adminEarningStatistics(Request $request)
    {
        // Cache earning statistics for 5 minutes with request params
        $cacheKey = 'admin_earning_stats_' . md5(serialize($request->all()));
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->tripRequestService->getAdminZoneWiseEarning($request->all());
        });
        return response()->json($data);
    }


    public function zoneWiseStatistics(Request $request)
    {
        // Cache zone-wise statistics for 5 minutes with request params
        $cacheKey = 'admin_zone_stats_' . md5(serialize($request->all()));
        $data = Cache::remember($cacheKey, 300, function () use ($request) {
            return $this->tripRequestService->getAdminZoneWiseStatistics(data: $request->all());
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
        $totalRideRequests = $zones->sum('ride_request');
        $totalParcelRequests = $zones->sum('parcel_request');
        $tripWhereInCriteria = [
            'zone_id' => $zones->pluck('id')->toArray(),
        ];
        $trips = $this->tripRequestService->getBy(whereInCriteria: $tripWhereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, relations: ['coordinate', 'zone']);
        $markers = $trips->map(function ($trip) {
            return [
                'position' => [
                    'lat' => $trip?->coordinate?->pickup_coordinates?->latitude ?? 0, // Default to 0 if not defined
                    'lng' => $trip?->coordinate?->pickup_coordinates?->longitude ?? 0, // Default to 0 if not defined
                ],
                'title' => "Trip Id #" . $trip?->ref_id,
            ];
        });
        $polygons = json_encode(formatZoneCoordinates($zones));

        $markers = json_encode($markers);
        // Calculate center lat/lng
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = 0;

        foreach ($zones as $zone) {
            $latSum += trim(explode(' ', $zone->center)[1], 'POINT()');
            $lngSum += trim(explode(' ', $zone->center)[0], 'POINT()');
            $totalPoints++;
        }

        $centerLat = $latSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $centerLng = $lngSum / ($totalPoints == 0 ? 1 : $totalPoints);
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
        $tripWhereInCriteria = [
            'zone_id' => $zones->pluck('id')->toArray(),
        ];
        $trips = $this->tripRequestService->getBy(whereInCriteria: $tripWhereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, relations: ['coordinate', 'zone']);
        $markers = $trips->map(function ($trip) {
            return [
                'position' => [
                    'lat' => $trip?->coordinate?->pickup_coordinates?->latitude ?? 0, // Default to 0 if not defined
                    'lng' => $trip?->coordinate?->pickup_coordinates?->longitude ?? 0, // Default to 0 if not defined
                ],
                'title' => "Trip Id #" . $trip?->ref_id,
            ];
        });
        $polygons = json_encode(formatZoneCoordinates($zones));
        $markers = json_encode($markers);
        // Calculate center lat/lng
        $latSum = 0;
        $lngSum = 0;
        $totalPoints = 0;
        foreach ($zones as $zone) {
            $latSum += trim(explode(' ', $zone->center)[1], 'POINT()');
            $lngSum += trim(explode(' ', $zone->center)[0], 'POINT()');
            $totalPoints++;
        }

        $centerLat = $latSum / ($totalPoints == 0 ? 1 : $totalPoints);
        $centerLng = $lngSum / ($totalPoints == 0 ? 1 : $totalPoints);
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

        $tripCount = $this->tripRequestService->getBy(criteria: ['zone_id' => $zone?->id], whereBetweenCriteria: $whereBetweenCriteria, relations: ['coordinate', 'zone'])->count();
        $dateWiseTrips = $this->tripRequestService->getTripHeatMapCompareDataBy(data: ['zone_id' => $zone?->id, 'date_range' => $dateRange]);
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
                $trips = $this->tripRequestService->getBy(criteria: ['zone_id' => $zone?->id], whereBetweenCriteria: $whereMarkerBetweenCriteria, relations: ['coordinate', 'zone']);
                $mappedMarkers = $trips->map(function ($trip) {
                    return [
                        'position' => [
                            'lat' => $trip?->coordinate?->pickup_coordinates?->latitude ?? 0, // Default to 0 if not defined
                            'lng' => $trip?->coordinate?->pickup_coordinates?->longitude ?? 0, // Default to 0 if not defined
                        ],
                        'title' => "Trip Id #" . $trip?->ref_id,
                    ];
                });
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

}
