<?php

namespace Modules\TripManagement\Service;

use App\Events\RideRequestEvent;
use App\Jobs\SendPushNotificationJob;
use App\Service\BaseService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Cache;
use Carbon\Factory;
use Doctrine\DBAL\Schema\View;
use Facade\FlareClient\Http\Response;
use Illuminate\Database\Eloquent\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Console\Application;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\BusinessManagement\Repository\SettingRepositoryInterface;
use Modules\Gateways\Traits\SmsGateway;
use Modules\PromotionManagement\Service\Interface\CouponSetupServiceInterface;
use Modules\ReviewModule\Repository\ReviewRepositoryInterface;
use Modules\ReviewModule\Service\Interface\ReviewServiceInterface;
use Modules\TripManagement\Repository\TripRequestRepositoryInterface;
use Modules\TripManagement\Repository\TripStatusRepositoryInterface;
use Modules\TripManagement\Service\Interface\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestFeeServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Repository\UserRepositoryInterface;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;
use Modules\ZoneManagement\Repository\ZoneRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TripRequestService extends BaseService implements TripRequestServiceInterface
{

    use LevelHistoryManagerTrait, SmsGateway;

    protected $tripRequestRepository;
    protected $zoneRepository;
    protected $tripStatusRepository;
    protected $reviewRepository;

    protected $tripRequestFeeService;
    protected $tempTripNotificationService;
    protected $userLastLocation;
    protected $driverDetailService;
    protected $couponService;
    protected $reviewService;
    protected $rejectedDriverRequestService;
    protected $userRepository;
    protected $settingRepository;

    public function __construct(
        TripRequestRepositoryInterface        $tripRequestRepository,
        ZoneRepositoryInterface               $zoneRepository,
        TripStatusRepositoryInterface         $tripStatusRepository,
        ReviewRepositoryInterface             $reviewRepository,
        TripRequestFeeServiceInterface        $tripRequestFeeService,
        TempTripNotificationServiceInterface  $tempTripNotificationService,
        UserLastLocationInterface             $userLastLocation,
        DriverDetailServiceInterface          $driverDetailService,
        CouponSetupServiceInterface           $couponService,
        ReviewServiceInterface                $reviewService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
        UserRepositoryInterface               $userRepository,
        SettingRepositoryInterface            $settingRepository
    )
    {
        parent::__construct($tripRequestRepository);
        $this->tripRequestRepository = $tripRequestRepository;
        $this->zoneRepository = $zoneRepository;
        $this->tripStatusRepository = $tripStatusRepository;
        $this->reviewRepository = $reviewRepository;
        $this->tripRequestFeeService = $tripRequestFeeService;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->userLastLocation = $userLastLocation;
        $this->driverDetailService = $driverDetailService;
        $this->couponService = $couponService;
        $this->reviewService = $reviewService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
        $this->userRepository = $userRepository;
        $this->settingRepository = $settingRepository;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        if (array_key_exists('customer_id', $criteria)) {
            $data = array_merge($data, [
                'customer_id' => $criteria['customer_id']
            ]);
        }
        if (array_key_exists('driver_id', $criteria)) {
            $data = array_merge($data, [
                'driver_id' => $criteria['driver_id']
            ]);
        }
        if (array_key_exists('type', $criteria)) {
            $data = array_merge($data, [
                'type' => $criteria['type']
            ]);
        }
        if (array_key_exists('current_status', $criteria)) {
            $data = array_merge($data, [
                'current_status' => $criteria['current_status']
            ]);
        }
        if (array_key_exists('payment_status', $criteria)) {
            $data = array_merge($data, [
                'payment_status' => $criteria['payment_status']
            ]);
        }
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['ref_id'];
            $searchData['relations'] = [
                'customer' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
                'driver' => ['full_name', 'first_name', 'last_name', 'email', 'phone'],
            ];
            $searchData['value'] = $criteria['search'];
        }
        $whereInCriteria = [];
        $whereBetweenCriteria = [];
        if (array_key_exists('filter_date', $criteria)) {
            $whereBetweenCriteria = ['created_at' => $criteria['filter_date']];
        }
        return $this->tripRequestRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends, groupBy: $groupBy);
    }

    /**
     * Get analytics data for charts
     * OPTIMIZED: Uses batch aggregation instead of per-period queries
     * Reduces query count from 7-12 queries to 1-2 queries
     */
    public function getAnalytics($dateRange): mixed
    {
        $monthlyOrder = [];
        $label = [];

        switch ($dateRange) {
            case THIS_WEEK:
                $weekStartDate = now()->startOfWeek();
                $label = [];
                for ($i = 0; $i < 7; $i++) {
                    $label[] = $weekStartDate->copy()->addDays($i)->format('"D"');
                }
                
                // Single aggregated query for the entire week
                $aggregated = $this->tripRequestRepository->getAnalyticsAggregated(
                    'daily',
                    now()->startOfWeek(),
                    now()->endOfWeek()
                );
                
                // Map to day indices (MySQL DAYOFWEEK: 1=Sunday, 2=Monday, etc.)
                for ($i = 1; $i <= 7; $i++) {
                    // Adjust index to match Laravel's week start (Monday = 1)
                    $dayIndex = ($i % 7) + 1; // Convert to MySQL DAYOFWEEK
                    $monthlyOrder[$i] = $aggregated[$dayIndex] ?? 0;
                }
                break;

            case THIS_MONTH:
                $label = [
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . now()->daysInMonth . '"',
                ];

                // Single aggregated query for the entire month
                $aggregated = $this->tripRequestRepository->getAnalyticsAggregated(
                    'weekly',
                    now()->startOfMonth(),
                    now()->endOfMonth()
                );
                
                for ($i = 1; $i <= 4; $i++) {
                    $monthlyOrder[$i] = $aggregated[$i] ?? 0;
                }
                break;

            case THIS_YEAR:
                $label = [
                    '"Jan"', '"Feb"', '"Mar"', '"Apr"', '"May"', '"Jun"',
                    '"Jul"', '"Aug"', '"Sep"', '"Oct"', '"Nov"', '"Dec"'
                ];

                // Single aggregated query for all 12 months
                $aggregated = $this->tripRequestRepository->getAnalyticsAggregated(
                    'monthly',
                    null,
                    null,
                    now()->year
                );
                
                for ($i = 1; $i <= 12; $i++) {
                    $monthlyOrder[$i - 1] = $aggregated[$i] ?? 0;
                }
                break;

            case TODAY:
                $label = [
                    '"6:00 am"', '"8:00 am"', '"10:00 am"', '"12:00 pm"',
                    '"2:00 pm"', '"4:00 pm"', '"6:00 pm"', '"8:00 pm"',
                    '"10:00 pm"', '"12:00 am"', '"2:00 am"', '"4:00 am"'
                ];

                // Single aggregated query for all time blocks
                $aggregated = $this->tripRequestRepository->getAnalyticsAggregated('hourly');
                
                // Map 2-hour blocks: block 3 = 6-8am, block 4 = 8-10am, etc.
                $blockMapping = [3, 4, 5, 6, 7, 8, 9, 10, 11, 0, 1, 2];
                for ($i = 0; $i < 12; $i++) {
                    $monthlyOrder[$i] = $aggregated[$blockMapping[$i]] ?? 0;
                }
                break;
                
            default:
                $businessStartDate = Carbon::parse(BUSINESS_START_DATE);
                $today = Carbon::today();
                if ($businessStartDate?->year < $today->year) {
                    // Single aggregated query for all years
                    $aggregated = $this->tripRequestRepository->getAnalyticsAggregated('yearly');
                    
                    for ($i = $businessStartDate?->year; $i <= $today->year; $i++) {
                        $label[] = '"' . $i . '"';
                        $monthlyOrder[] = $aggregated[$i] ?? 0;
                    }
                } else {
                    $label = [
                        '"Jan"', '"Feb"', '"Mar"', '"Apr"', '"May"', '"Jun"',
                        '"Jul"', '"Aug"', '"Sep"', '"Oct"', '"Nov"', '"Dec"'
                    ];

                    // Single aggregated query for monthly
                    $aggregated = $this->tripRequestRepository->getAnalyticsAggregated(
                        'monthly',
                        null,
                        null,
                        now()->year
                    );
                    
                    for ($i = 1; $i <= 12; $i++) {
                        $monthlyOrder[$i - 1] = $aggregated[$i] ?? 0;
                    }
                }
        }
        return [$label, $monthlyOrder];
    }

    public function couponRuleValidate($coupon, $pickupCoordinates, $vehicleCategoryId): ?array
    {
        $startDate = Carbon::parse($coupon->start_date);
        $endDate = Carbon::parse($coupon->end_date);
        $today = Carbon::now()->startOfDay();

        if ($startDate->gt($today) || $endDate->lt($today)) {
            return response()->json(responseFormatter(constant: DEFAULT_EXPIRED_200), 200); //coupon expire
        }
        if ($coupon->rules == 'area_wise') {
            $pickupCoordinates = json_decode($pickupCoordinates, true);
            $checkArea = $coupon->areas->filter(function ($area) use ($pickupCoordinates) {
                return haversineDistance(
                        latitudeFrom: $area->latitude,
                        longitudeFrom: $area->longitude,
                        latitudeTo: $pickupCoordinates[0],
                        longitudeTo: $pickupCoordinates[1]
                    ) < $area->radius && $area->is_active == 1;
            });
            if ($checkArea->isEmpty()) {
                return COUPON_AREA_NOT_VALID_403;
            }
        } elseif ($coupon->rules == 'vehicle_category_wise') {
            $checkCategory = $coupon->categories->filter(function ($query) use ($vehicleCategoryId) {
                return $query->id == $vehicleCategoryId && $query->is_active == 1;
            });

            if ($checkCategory->isEmpty()) {
                return COUPON_VEHICLE_CATEGORY_NOT_VALID_403;
            }
        }

        return null;
    }

    public function statusWiseTotalTripRecords(array $attributes)
    {
        return $this->tripRequestRepository->statusWiseTotalTripRecords(attributes: $attributes);
    }

    /**
     * Export trip data using streaming to prevent memory exhaustion
     * OPTIMIZED: Uses cursor() for memory-efficient streaming
     * 
     * @param array $criteria Filter criteria
     * @param array $relations Relations to eager load
     * @param array $orderBy Ordering
     * @param int|null $limit Optional limit
     * @param int|null $offset Optional offset
     * @param array $withCountQuery With count queries
     * @return \Generator Yields formatted trip data one row at a time
     */
    public function export(array $criteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, array $withCountQuery = [])
    {
        // Use cursor for memory-efficient streaming
        $query = $this->tripRequestRepository->getModel()
            ->with(['customer', 'driver', 'fee'])
            ->when(!empty($criteria), function ($q) use ($criteria) {
                // Handle 'from' and 'to' for date filtering
                if (isset($criteria['from']) && isset($criteria['to'])) {
                    $q->whereBetween('created_at', [$criteria['from'], $criteria['to']]);
                    unset($criteria['from'], $criteria['to']);
                }
                // Handle search
                if (isset($criteria['search'])) {
                    $search = $criteria['search'];
                    $q->where(function ($query) use ($search) {
                        $query->where('ref_id', 'like', "%{$search}%");
                    });
                    unset($criteria['search']);
                }
                // Apply remaining criteria
                foreach ($criteria as $key => $value) {
                    $q->where($key, $value);
                }
            })
            ->when(!empty($orderBy), function ($q) use ($orderBy) {
                foreach ($orderBy as $field => $direction) {
                    $q->orderBy($field, $direction);
                }
            });
        
        // Return a generator that yields formatted rows one at a time
        $generator = function () use ($query) {
            foreach ($query->cursor() as $item) {
                yield [
                    'id' => $item['id'],
                    'trip_ID' => $item['ref_id'],
                    'date' => date('d F Y', strtotime($item['created_at'])) . ' ' . date('h:i a', strtotime($item['created_at'])),
                    'customer' => $item['customer']?->first_name . ' ' . $item['customer']?->last_name,
                    'driver' => $item['driver'] ? $item['driver']?->first_name . ' ' . $item['driver']?->last_name : 'no driver assigned',
                    'trip_cost' => $item['current_status'] == 'completed' ? $item['actual_fare'] : $item['estimated_fare'],
                    'coupon_discount' => $item['coupon_amount'],
                    'additional_fee' => $item['fee'] ? ($item['fee']->waiting_fee + $item['fee']->delay_fee + $item['fee']->idle_fee + $item['fee']->cancellation_fee + $item['fee']->vat_tax) : 0,
                    'total_trip_cost' => $item['paid_fare'] - $item['tips'],
                    'admin_commission' => $item['fee'] ? $item['fee']->admin_commission : 0,
                    'trip_status' => $item['current_status']
                ];
            }
        };
        
        // Return as a Laravel LazyCollection for compatibility with FastExcel
        return \Illuminate\Support\LazyCollection::make($generator);
    }

    /**
     * Get all dashboard metrics in a single aggregated query
     * Consolidates 7+ separate queries into one
     */
    public function getDashboardAggregatedMetrics(): object
    {
        return $this->tripRequestRepository->getDashboardAggregatedMetrics();
    }

    public function getAdminZoneWiseStatistics(array $data)
    {

        $whereBetweenCriteria = [];
        if (array_key_exists('date', $data)) {
            $date = getDateRange($data['date']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        $zones = $this->zoneRepository->getBy(criteria: ['is_active' => 1]);
        $zoneIds = $zones->pluck('id')->toArray();
        
        // Single aggregated query replaces N+1 queries
        $aggregatedStats = $this->tripRequestRepository->getAggregatedZoneStatistics($zoneIds, $whereBetweenCriteria);
        
        $zoneTripsByDate = $zones->map(function ($zone) use ($aggregatedStats) {
            $stats = $aggregatedStats->get($zone->id);
            return [
                'zone_id' => $zone->id,
                'zone_name' => $zone->name,
                'completed_trips' => $stats->completed_trips ?? 0,
                'cancelled_trips' => $stats->cancelled_trips ?? 0,
                'ongoing_trips' => $stats->ongoing_trips ?? 0,
                'total_trips' => $stats->total_trips ?? 0,
            ];
        });
        
        $totalTrips = $aggregatedStats->sum('total_trips');

        return [
            'totalTrips' => $totalTrips,
            'zoneTripsByDate' => $zoneTripsByDate,
        ];
    }

    public function getAdminZoneWiseEarning(array $data)
    {
        $criteria = [];
        if (array_key_exists('zone', $data) && $data['zone'] != 'all') {
            $criteria = [
                'zone_id' => $data['zone']
            ];
        }
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $criteriaForCommission = array_merge($criteria, [
            'payment_status' => PAID
        ]);

        $totalTripRequest = [];
        $totalAdminCommission = [];
        $label = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        switch ($data['date']) {
            case TODAY:
                $label = [
                    '"6:00 am"',
                    '"8:00 am"',
                    '"10:00 am"',
                    '"12:00 pm"',
                    '"2:00 pm"',
                    '"4:00 pm"',
                    '"6:00 pm"',
                    '"8:00 pm"',
                    '"10:00 pm"',
                    '"12:00 am"',
                    '"2:00 am"',
                    '"4:00 am"'
                ];

                $startTime = strtotime('6:00 AM');
                $startDate = Carbon::parse(now())->startOfDay();

                for ($i = 0; $i < 12; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, startTime: $startTime)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, startTime: $startTime)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $startTime = strtotime('+2 hours', $startTime);
                }
                break;
            case PREVIOUS_DAY:
                $label = [
                    '"6:00 am"',
                    '"8:00 am"',
                    '"10:00 am"',
                    '"12:00 pm"',
                    '"2:00 pm"',
                    '"4:00 pm"',
                    '"6:00 pm"',
                    '"8:00 pm"',
                    '"10:00 pm"',
                    '"12:00 am"',
                    '"2:00 am"',
                    '"4:00 am"'
                ];

                $startTime = strtotime('6:00 AM');
                $startDate = Carbon::yesterday()->startOfDay();
                for ($i = 0; $i < 12; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, startTime: $startTime)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, startTime: $startTime)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $startTime = strtotime('+2 hours', $startTime);
                }
                break;

            case THIS_WEEK:
                $weekStartDate = now()->startOfWeek();
                for ($i = 1; $i <= 7; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $weekStartDate, endDate: $weekStartDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $weekStartDate, endDate: $weekStartDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $label[] = $weekStartDate->format('"D"');
                    $weekStartDate = $weekStartDate->addDays(1);
                }
                break;
            case LAST_7_DAYS:
                $lastStartDate = now()->subDays(7)->startOfDay();
                for ($i = 1; $i <= 7; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $lastStartDate, endDate: $lastStartDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $lastStartDate, endDate: $lastStartDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $label[] = $lastStartDate->format('"D"');
                    $lastStartDate = $lastStartDate->addDays(1);
                }
                break;

            case THIS_MONTH:
                $label = [
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . now()->daysInMonth . '"',
                ];

                $start = now()->startOfMonth();
                $end = now()->startOfMonth()->addDays(6);
                $remainingDays = now()->daysInMonth - 28;

                for ($i = 1; $i <= 4; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
                }
                break;
            case LAST_MONTH:
                $label = [
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . now()->subMonth()->daysInMonth . '"',
                ];

                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->startOfMonth()->addDays(6);
                $remainingDays = now()->subMonth()->daysInMonth - 28;

                for ($i = 1; $i <= 4; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
                }
                break;

            case THIS_YEAR:
                $label = [
                    '"Jan"',
                    '"Feb"',
                    '"Mar"',
                    '"Apr"',
                    '"May"',
                    '"Jun"',
                    '"Jul"',
                    '"Aug"',
                    '"Sep"',
                    '"Oct"',
                    '"Nov"',
                    '"Dec"'
                ];

                for ($i = 1; $i <= 12; $i++) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, month: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], month: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                }
                break;
            default:
                $businessStartDate = Carbon::parse(BUSINESS_START_DATE);
                $today = Carbon::today();
                if ($businessStartDate?->year < $today->year) {
                    for ($i = $businessStartDate?->year; $i <= $today->year; $i++) {
                        $label[] = '"' . $i . '"';
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, year: $i)->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], year: $i)->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    }
                } else {
                    $label = [
                        '"Jan"',
                        '"Feb"',
                        '"Mar"',
                        '"Apr"',
                        '"May"',
                        '"Jun"',
                        '"Jul"',
                        '"Aug"',
                        '"Sep"',
                        '"Oct"',
                        '"Nov"',
                        '"Dec"'
                    ];

                    for ($i = 1; $i <= 12; $i++) {
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, month: $i)->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], month: $i)->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    }
                }
        }
        return [
            'label' => $label,
            'totalTripRequest' => $totalTripRequest,
            'totalAdminCommission' => $totalAdminCommission
        ];
    }

    public function getDateZoneWiseEarningStatistics(array $data)
    {
        $zones = $this->zoneRepository->getBy(withTrashed: true);

        $totalTripRequest = [];
        $totalAdminCommission = [];
        $totalTax = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        foreach ($zones as $zone) {
            $criteria = [
                'zone_id' => $zone->id
            ];
            $criteriaForStatistics = [
                'zone_id' => $zone->id,
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $totalTripRequest[$zone->name] = $this->tripRequestRepository->getBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->count();
            $adminCommission = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.admin_commission');
            $vatTax = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.vat_tax');
            $totalAdminCommission[$zone->name] = number_format($adminCommission - $vatTax, $points, '.', '');
            $totalTax[$zone->name] = number_format($vatTax, $points, '.', '');
        }
        return [
            'label' => $zones->pluck('name')->toArray(),
            'totalTripRequest' => $totalTripRequest,
            'totalAdminCommission' => $totalAdminCommission,
            'totalVatTax' => $totalTax
        ];
    }

    public function getDateRideTypeWiseEarningStatistics(array $data)
    {
        $totalAdminCommission = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $rideType = [PARCEL, RIDE_REQUEST];
        for ($i = 0; $i <= 1; $i++) {
            $criteriaForStatistics = [
                'type' => $rideType[$i],
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $adminCommission = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.admin_commission');
            $vatTax = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: ['fee'])->sum('fee.vat_tax');
            $totalAdminCommission[$rideType[$i]] = number_format($adminCommission, $points, '.', '');
        }
        return [
            'label' => $rideType,
            'totalAdminCommission' => $totalAdminCommission,
        ];
    }

    public function getDateZoneWiseExpenseStatistics(array $data)
    {
        $zones = $this->zoneRepository->getBy(withTrashed: true);
        $totalExpense = [];
        $totalTripRequest = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        foreach ($zones as $zone) {
            $criteria = [
                'zone_id' => $zone->id
            ];
            $criteriaForStatistics = [
                'zone_id' => $zone->id,
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $totalTripRequest[$zone->name] = $this->tripRequestRepository->getBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->count();
            $adminCouponExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('coupon_amount');
            $adminDiscountExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('discount_amount');
            $totalExpense[$zone->name] = number_format($adminCouponExpense + $adminDiscountExpense, $points, '.', '');
        }
        return [
            'label' => $zones->pluck('name')->toArray(),
            'totalTripRequest' => $totalTripRequest,
            'totalExpense' => $totalExpense,
        ];
    }

    public function getDateRideTypeWiseExpenseStatistics(array $data)
    {
        $totalExpense = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        $date = getDateRange($data['date'] ?? ALL_TIME);
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $rideType = [PARCEL, RIDE_REQUEST];
        for ($i = 0; $i <= 1; $i++) {
            $criteriaForStatistics = [
                'type' => $rideType[$i],
                'payment_status' => PAID
            ];
            $whereHasRelations = [];

            // Add criteria for the `fee` relationship to filter by `cancelled_by` being either `null` or `CUSTOMER`
            $whereHasRelations['fee'] = function ($query) {
                $query->whereNull('cancelled_by')
                    ->orWhere('cancelled_by', '=', 'CUSTOMER'); // Handle `null` or `CUSTOMER`
            };
            $adminCouponExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('coupon_amount');
            $adminDiscountExpense = $this->tripRequestRepository->getBy(criteria: $criteriaForStatistics, whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations)->sum('discount_amount');
            $totalExpense[$rideType[$i]] = number_format($adminCouponExpense + $adminDiscountExpense, $points, '.', '');
        }
        return [
            'label' => $rideType,
            'totalExpense' => $totalExpense,
        ];
    }

    public function getLeaderBoard(array $data, $limit = null, $offset = null)
    {
        if ($data['user_type'] == CUSTOMER) {
            $userIdColumn = 'customer_id';
        } else {
            $userIdColumn = 'driver_id';
        }
        $criteria = [];
        if (array_key_exists('zone', $data) && $data['zone'] != 'all') {
            $criteria = array_merge($criteria, ['zone_id' => $data['zone']]);
        }
        if (array_key_exists('driver_id', $data)) {
            $criteria = array_merge($criteria, ['driver_id' => $data['driver_id']]);

        }
        $whereBetweenCriteria = [];
        if (array_key_exists('data', $data) && $data['data'] != ALL_TIME) {
            $date = getDateRange($data['data']);
            $whereBetweenCriteria = [
                'created_at' => [$date['start'], $date['end']],
            ];
        }
        return $this->tripRequestRepository->getLeaderBoard(userType: $userIdColumn, criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, relations: [$data['user_type']], limit: $limit, offset: $offset);
    }


    public function storeTrip(array $attributes): Model
    {
        try {

            DB::beginTransaction();
            $tripData = [];
            $tripData['customer_id'] = $attributes['customer_id'] ?? null;
            $tripData['vehicle_category_id'] = $attributes['vehicle_category_id'] ?? null;
            $tripData['zone_id'] = $attributes['zone_id'] ?? null;
            $tripData['area_id'] = $attributes['area_id'] ?? null;
            $tripData['actual_fare'] = $attributes['estimated_fare'];
            $tripData['estimated_fare'] = $attributes['estimated_fare'] ?? 0;
            $tripData['estimated_distance'] = $attributes['estimated_distance'] ?? null;
            $tripData['payment_method'] = $attributes['payment_method'] ?? null;
            $tripData['note'] = $attributes['note'] ?? null;
            $tripData['type'] = $attributes['type'];
            $tripData['entrance'] = $attributes['entrance'] ?? null;
            $tripData['encoded_polyline'] = $attributes['encoded_polyline'] ?? null;

            // Travel mode fields
            if (($attributes['trip_type'] ?? 'normal') === 'travel' || ($attributes['is_travel'] ?? false)) {
                $tripData['is_travel'] = true;
                $tripData['min_price'] = $attributes['min_price'] ?? null;
                $tripData['offer_price'] = $attributes['offer_price'] ?? null;
                $tripData['fixed_price'] = $attributes['fixed_price'] ?? $attributes['offer_price'] ?? null;
                $tripData['travel_date'] = $attributes['scheduled_at'] ?? $attributes['travel_date'] ?? null;
                $tripData['travel_passengers'] = $attributes['seats_requested'] ?? $attributes['travel_passengers'] ?? null;
                $tripData['seats_requested'] = $attributes['seats_requested'] ?? null;
                $tripData['travel_radius_km'] = $attributes['travel_radius_km'] ?? null;
                $tripData['travel_status'] = $attributes['travel_status'] ?? 'pending';
            }

            $trip = $this->tripRequestRepository->create($tripData);

            $trip->tripStatus()->create([
                'customer_id' => $attributes['customer_id'],
                'pending' => now()
            ]);

            $coordinates = [
                'pickup_coordinates' => $attributes['pickup_coordinates'],
                'start_coordinates' => $attributes['pickup_coordinates'],
                'destination_coordinates' => $attributes['destination_coordinates'],
                'pickup_address' => $attributes['pickup_address'],
                'destination_address' => $attributes['destination_address'],
                'customer_request_coordinates' => $attributes['customer_request_coordinates']
            ];
            $int_coordinates = null;

            if (!is_null($int_coordinates)) {
                foreach ($int_coordinates as $key => $ic) {
                    if ($key == 0) {
                        $coordinates['int_coordinate_1'] = new Point($ic[1], $ic[0]);
                    } elseif ($key == 1) {
                        $coordinates['int_coordinate_2'] = new Point($ic[1], $ic[0]);
                    }
                }
            }
            $coordinates['intermediate_coordinates'] = $attributes['intermediate_coordinates'] ?? null;
            $coordinates['intermediate_addresses'] = $attributes['intermediate_addresses'] ?? null;

            $trip->coordinate()->create($coordinates);
            $trip->fee()->create();
            $delay_time = $trip->time()->create([
                'estimated_time' => $attributes['estimated_time']
            ]);

            if ($attributes['type'] == 'parcel') {
                $trip->parcel()->create([
                    'payer' => $attributes['payer'],
                    'weight' => $attributes['weight'],
                    'parcel_category_id' => $attributes['parcel_category_id'],
                ]);

                $sender = [
                    'name' => $attributes['sender_name'],
                    'contact_number' => $attributes['sender_phone'],
                    'address' => $attributes['sender_address'],
                    'user_type' => 'sender'
                ];
                $receiver = [
                    'name' => $attributes['receiver_name'],
                    'contact_number' => $attributes['receiver_phone'],
                    'address' => $attributes['receiver_address'],
                    'user_type' => 'receiver'
                ];
                $trip->parcelUserInfo()->createMany([$sender, $receiver]);
            }

            DB::commit();
        } catch (\Exception $e) {
            //throw $th;
            DB::rollback();
            abort(403, message: $e->getMessage());
        }

        return $trip;
    }


    public function pendingParcelList(array $attributes)
    {
        return $this->tripRequestRepository->pendingParcelList($attributes);
    }

    public function updateRelationalTable($attributes)
    {
        return $this->tripRequestRepository->updateRelationalTable($attributes);
    }


    public function findOneWithAvg(array $criteria = [], array $relations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false, array $withAvgRelation = []): ?Model
    {

        return $this->tripRequestRepository->findOneWithAvg($criteria, $relations, $withCountQuery, $withTrashed, $onlyTrashed, $withAvgRelation);
    }


    public function getWithAvg(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $relations = [], array $orderBy = [], int $limit = null, int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $withAvgRelation = [], array $whereBetweenCriteria = [], array $whereNotNullCriteria = []): Collection|LengthAwarePaginator
    {
        return $this->tripRequestRepository->getWithAvg($criteria, $searchCriteria, $whereInCriteria, $relations, $orderBy, $limit, $offset, $onlyTrashed, $withTrashed, $withCountQuery, $withAvgRelation, $whereBetweenCriteria, $whereNotNullCriteria);
    }

    public function getPendingRides($attributes): mixed
    {
        return $this->tripRequestRepository->getPendingRides($attributes);
    }


    public function makeRideRequest($request, $pickupCoordinates): mixed
    {
        $save_trip = $this->storeTrip(attributes: $request->request->all());

        $search_radius = (float)get_cache('search_radius') ?? (float)5;

        $customer = auth('api')->user();
        $femaleOnly = false;

        if ($customer->gender === 'female' && $request->input('female_driver_only', false)) {
            $femaleOnly = true;
        }

        // Find drivers list based on pickup locations
        $find_drivers = $this->findNearestDriver(
            latitude: $pickupCoordinates[0],
            longitude: $pickupCoordinates[1],
            zoneId: $request->header('zoneId'),
            radius: $search_radius,
            vehicleCategoryId: $request->vehicle_category_id,
            femaleOnly: $femaleOnly
        );
        //Send notifications to drivers
        if (!empty($find_drivers)) {
            $push = getNotification('new_' . $save_trip->type);
            $notification = [
                'title' => translate($push['title']),
                'description' => translate($push['description']),
                'status' => $push['status'],
                'ride_request_id' => $save_trip->id,
                'type' => $save_trip->type,
                'action' => 'new_ride_request_notification'
            ];
            $notify = [];
            foreach ($find_drivers as $key => $value) {
                broadcast(new RideRequestEvent(user: $value, data: $notification));
                if ($value->user?->fcm_token) {
                    $notify[$key]['user_id'] = $value->user->id;
                    $notify[$key]['trip_request_id'] = $save_trip->id;
                }
            }

            if (!empty($notify)) {

                dispatch(new SendPushNotificationJob($notification, $find_drivers))->onQueue('high');
                $this->tempTripNotificationService->create(['data' => $notify]);
            }
        }
        //Send notifications to admins
        if (!is_null(businessConfig('server_key', NOTIFICATION_SETTINGS))) {
            sendTopicNotification(
                'admin_notification',
                translate('new_request_notification'),
                translate('new_request_has_been_placed'),
                'null',
                $save_trip->id,
                $request->type
            );
        }

        $trip = new TripRequestResource($save_trip);
        return $trip;
    }


    public function findNearestDriver($latitude, $longitude, $zoneId, $radius = 5, $vehicleCategoryId = null, $femaleOnly = false): mixed
    {
        /*
         * replace 6371000 with 6371 for kilometer and 3956 for miles
         */
        $attributes = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
            'zone_id' => $zoneId,
        ];
        if ($vehicleCategoryId) {
            $attributes['vehicle_category_id'] = $vehicleCategoryId;
        }

        $drivers = $this->userLastLocation->getNearestDrivers($attributes);

        if ($femaleOnly) {
            $drivers = $drivers->filter(function ($driver) {
                return $driver->gender === 'female';
            });
        }

        return $drivers;
    }


    public function validateDiscount($trip, $response, $tripId, $cuponId)
    {
        $admin_trip_commission = (float)get_cache('trip_commission') ?? 0;
        $vat_percent = (float)get_cache('vat_percent') ?? 1;
        $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount'];
        $vat = ($vat_percent * $final_fare_without_tax) / 100;
        $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
        $updateTrip = $this->findOne(id: $tripId);
        $updateTrip->coupon_id = $cuponId;
        $updateTrip->coupon_amount = $response['discount'];
        $updateTrip->paid_fare = $final_fare_without_tax + $vat + $trip->fee->tips;
        $updateTrip->fee()->update([
            'vat_tax' => $vat,
            'admin_commission' => $admin_commission
        ]);
        $updateTrip->save();

        $push = getNotification('coupon_applied');
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate($push['description']) . ' ' . $response['discount'],
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'coupon_applied',
            user_id: $trip->driver->id
        );

        $trip = new TripRequestResource($trip->append('distance_wise_fare'));

        return $trip;
    }


    public function handleCancelledTrip($trip, $attributes, $tripId)
    {
        $data = $this->tempTripNotificationService->findOneBy(criteria: [
            'trip_request_id' => $tripId
        ], relations: ['user']);
        $push = getNotification('ride_cancelled');
        if (!empty($data)) {
            if ($trip->driver_id) {
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(
                        fcm_token: $trip->driver->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $tripId,
                        type: $trip->type,
                        action: 'ride_cancelled',
                        user_id: $trip->driver->id
                    );
                }
                $this->driverDetailService->updateBy(criteria: ['user_id' => $trip->driver_id], data: ['availability_status' => 'available']);
                $attributes['driver_id'] = $trip->driver_id;
            } else {
                $notification = [
                    'title' => translate($push['title']),
                    'description' => translate($push['description']),
                    'status' => $push['status'],
                    'ride_request_id' => $trip->id,
                    'type' => $trip->type,
                    'action' => 'ride_cancelled'
                ];
                dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
            }
            $this->tempTripNotificationService->delete(id: $trip->id);
        }
    }


    public function handleCompletedTrip($trip, $request, $attributes)
    {
        if ($request->status == 'cancelled') {
            $attributes['fee']['cancelled_by'] = 'customer';
        }
        $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->longitude, $trip->driver->lastLocations->latitude);

        $this->driverDetailService->updateBy(criteria: ['user_id' => $trip->driver_id], data: ['availability_status' => 'available']);
        //Get status wise notification message
        $push = getNotification('ride_' . $request->status);
        if (!is_null($trip->driver->fcm_token)) {
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                action: 'ride_completed',
                user_id: $trip->driver->id
            );
        }
    }


    public function handleCustomerRideStatusUpdate($trip, $request, $attributes)
    {
        DB::beginTransaction();
        if ($request->status == 'cancelled' && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->updateRelationalTable($attributes);
            $this->cancellationPercentChecker(auth('api')->user());
            $this->completedRideChecker($trip->driver);
        } elseif ($request->status == 'completed' && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->updateRelationalTable($attributes);
            $this->completedRideChecker(auth('api')->user());
            $this->completedRideChecker($trip->driver);
        } else {
            $this->updateRelationalTable($attributes);
        }
        DB::commit();
        
        // Refresh trip to get updated current_status
        $trip->refresh();
        return $trip;
    }


    public function removeCouponData($trip)
    {
        $coupon = $this->couponService->findOne(id: $trip->coupon_id);
        $coupon->decrement('total_used');
        $coupon->total_amount -= $trip->coupon_amount;
        $coupon->save();


        $trip = $this->findOne(id: $trip->id);
        $vat_percent = (float)get_cache('vat_percent') ?? 1;
        $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) + $trip->coupon_amount;
        $vat = ($vat_percent * $final_fare_without_tax) / 100;
        $trip->coupon_id = null;
        $trip->coupon_amount = 0;
        $trip->paid_fare = $final_fare_without_tax + $vat + $trip->fee->tips;
        $trip->fee()->update([
            'vat_tax' => $vat
        ]);
        $trip->save();
    }


    public function getCustomerIncompleteRide(): mixed
    {
        $trip = $this->tripRequestRepository->findOneBy(
            criteria: ['customer_id' => auth()->id()],
            relations: [
                'customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
                'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'
            ],
            orderBy: ['created_at' => 'desc']
        );

        if (
            !$trip || $trip->type != 'ride_request' ||
            $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)
        ) {

            return null;
        }
        return $trip;
    }


    public function getDriverIncompleteRide(): mixed
    {
        $trip = $this->tripRequestRepository->findOneBy(
            criteria: ['driver_id' => auth()->guard('api')->id()],
            relations: ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'fee'],
            orderBy: ['created_at' => 'desc']
        );

        if (
            !$trip || $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)
        ) {
            return null;
        }
        return $trip;
    }


    public function handleDriverStatusUpdate($request, $trip)
    {
        $attributes = [
            'column' => 'id',
            'value' => $request['trip_request_id'],
            'trip_status' => $request['status']
        ];
        DB::beginTransaction();
        if ($request->status == 'completed' || $request->status == 'cancelled') {
            if ($request->status == 'cancelled') {
                $attributes['fee']['cancelled_by'] = 'driver';
            }
            $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->longitude, $trip->driver->lastLocations->latitude);

            $this->driverDetailService->updateBy(criteria: ['user_id' => auth('api')->id()], data: ['availability_status' => 'available']);
        }

        $data = $this->updateRelationalTable($attributes);


        if ($request->status == 'cancelled') {
            $this->cancellationPercentChecker(auth('api')->user());
            $this->completedRideChecker($trip->customer);
        } elseif ($request->status == 'completed') {
            $this->completedRideChecker(auth('api')->user());
            $this->completedRideChecker($trip->customer);
        }

        DB::commit();
        //Get status wise notification message
        if ($trip->type == 'parcel') {
            $action = 'parcel_' . $request->status;
        } else {
            $action = 'ride_' . $request->status;
        }
        $push = getNotification($action);
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $request['trip_request_id'],
            type: $trip->type,
            action: $action,
            user_id: $trip->customer->id
        );


        return $data;
    }


    public function handleRequestActionPushNotification($trip, $user)
    {
        DB::beginTransaction();
        Cache::put($trip->id, ACCEPTED, now()->addHour());
        $driverArrivalTime = getRoutes(
            originCoordinates: [
                $trip->coordinate->pickup_coordinates->getLat(),
                $trip->coordinate->pickup_coordinates->getLng()
            ],
            destinationCoordinates: [
                $user->lastLocations->latitude,
                $user->lastLocations->longitude
            ],
        );
        $attributes['driver_arrival_time'] = (float)($driverArrivalTime[0]['duration']) / 60;
        $this->driverDetailService->update(id: $user->id, data: ['availability_status' => 'on_trip']);

        $data = $this->tempTripNotificationService->getBy(criteria: [
            ['trip_request_id' => $trip->id],
            ['user_id', '!=', auth('api')->id()]
        ], relations: ['user']);

        if (!empty($data)) {
            $push = getNotification('ride_is_started');
            $notification = [
                'title' => translate($push['title']),
                'description' => translate($push['description']),
                'status' => $push['status'],
                'ride_request_id' => $trip->id,
                'type' => $trip->type,
                'action' => 'ride_started'
            ];
            dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
            $this->tempTripNotificationService->delete(id: $trip->id);
            $this->tempTripNotificationService->deleteBy(criteria: ['user_id', $user->id]);
        }
        //Trip update
        $this->update(data: $attributes, id: $trip->id);
        //deleting exiting rejected driver request for this trip
        $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id', $trip->id]);

        return getNotification('driver_is_on_the_way');
    }


    public function getTripOverview($data)
    {

        if ($data['filter'] == THIS_WEEK) {
            $dateRange = THIS_WEEK;
        }
        if ($data['filter'] == LAST_WEEK) {
            $dateRange = LAST_WEEK;
        }

        switch ($dateRange) {
            case LAST_WEEK:
                $startDate = Carbon::now()->startOfWeek();
                $endDate = Carbon::now()->endOfWeek();
                break;
            default:
                $startDate = Carbon::now()->subWeek()->startOfWeek();
                $endDate = Carbon::now()->subWeek()->endOfWeek();
        }
        $period = CarbonPeriod::create($startDate, $endDate);

        $whereBetweenCriteria = [
            'created_at' => [$startDate, $endDate],
        ];
        $trips = $this->tripRequestRepository->getBy(criteria: ['driver_id' => auth()->id()], whereBetweenCriteria: $whereBetweenCriteria);
        $day = ['Mon', 'Tues', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $incomeStat = [];
        foreach ($period as $key => $p) {
            $incomeStat[$day[$key]] = $trips
                ->whereBetween('created_at', [$p->copy()->startOfDay(), $p->copy()->endOfDay()])
                ->sum('estimated_fare');
        }
        $totalReviews = $this->reviewRepository->getBy(whereBetweenCriteria: $whereBetweenCriteria);
        return [
            'totalReviews' => $totalReviews,
            'trips' => $trips,
            'incomeStat' => $incomeStat
        ];

    }

    public function getPopularTips()
    {
        return $this->tripRequestRepository->getPopularTips();
    }

    public function sendParcelTrackingLinkToReceiver($user, $message): bool
    {
        $dataValues = $this->settingRepository->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isNotEmpty()) {
            try {
                self::send($user->phone, $message);
                return true;
            } catch (\Exception $exception) {
                return false;
            }
        }
        return false;
    }

    public function getTripHeatMapCompareDataBy(array $data)
    {
        $criteria = [
            'zone_id' => $data['zone_id']
        ];

        $date = getCustomDateRange($data['date_range']);
        $startDate = $date['start'];
        $endDate = $date['end'];
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];

        return $this->tripRequestRepository->getTripHeatMapCompareDataBy(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $endDate);
    }


    public function getTripHeatMapCompareZoneDateWiseEarningStatistics(array $data)
    {
        $criteria = [
            'zone_id' => $data['zone_id']
        ];
        $date = getCustomDateRange($data['date_range']);
        $startDate = $date['start'];
        $endDate = $date['end'];
        $whereBetweenCriteria = [
            'created_at' => [$date['start'], $date['end']],
        ];
        $criteriaForCommission = array_merge($criteria, [
            'payment_status' => PAID
        ]);

        $totalTripRequest = [];
        $totalAdminCommission = [];
        $label = [];
        $points = (int)getSession('currency_decimal_point') ?? 0;
        // Adjust to the beginning of the week (Monday)
        $startOfWeek = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $endDate->copy()->startOfWeek(Carbon::MONDAY);
        // Determine if the custom date range is for today, this month, or this year
        if ($startDate->isSameDay($endDate)) {
            // Logic for TODAY
            $label = [
                "6:00 am",
                "8:00 am",
                "10:00 am",
                "12:00 pm",
                "2:00 pm",
                "4:00 pm",
                "6:00 pm",
                "8:00 pm",
                "10:00 pm",
                "12:00 am",
                "2:00 am",
                "4:00 am"
            ];
            $startTime = strtotime('6:00 AM');
            for ($i = 0; $i < 12; $i++) {
                $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, startTime: $startTime)->count();
                $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, startTime: $startTime)->sum('fee.admin_commission');
                $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                $startTime = strtotime('+2 hours', $startTime);
            }
        } elseif ($startOfWeek->isSameWeek($endOfWeek)) {
            $start = $startOfWeek->copy()->startOfWeek(CarbonInterface::MONDAY);
            for ($i = 1; $i <= 7; $i++) {
                if ($start >= $startDate && $start <= $endDate) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $start)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $start)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } else {
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }

                $label[] = $start->format("D");
                $start = $start->addDays(1);
            }
        } elseif ($startDate->isSameMonth($endDate)) {
            // Logic for THIS_MONTH
            $label = [
                "Day 1-7",
                "Day 8-14",
                "Day 15-21",
                "Day 22-" . now()->daysInMonth . "",
            ];
            $start = $startDate->copy()->startOfMonth();
            $end = $start->copy()->addDays(6);
            $remainingDays = $startDate->daysInMonth - 28;
            for ($i = 1; $i <= 4; $i++) {
                if ($start < $startDate && $end <= $endDate) {
                    // Current range starts before and ends within the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start >= $startDate && $end > $endDate) {
                    // Current range starts within and ends after the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $endDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $endDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start >= $startDate && $end <= $endDate) {
                    // Current range is completely within the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start < $startDate && $end > $startDate) {
                    // Current range overlaps the start of the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $end)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $end)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($start < $endDate && $end > $endDate) {
                    // Current range overlaps the end of the given range
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $start, endDate: $endDate)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $start, endDate: $endDate)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } else {
                    // If none of the conditions are met, set totals to zero
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }
                $start = $start->addDays(7);
                $end = $i == 3 ? $end->addDays(7 + $remainingDays) : $end->addDays(7);
            }
        } elseif ($startDate->isSameYear($endDate)) {
            $year = $startDate->year;
            $startDateMonth = $startDate->month;
            $endDateMonth = $endDate->month;
            $label = [
                "Jan", "Feb", "Mar", "Apr", "May", "Jun",
                "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
            ];
            for ($i = 1; $i <= 12; $i++) {
                if ($i >= $startDateMonth && $i <= $endDateMonth) {
                    if ($startDateMonth == $i && $endDateMonth == $i) {
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDate,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDate,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    } elseif ($startDateMonth == $i && $endDateMonth > $i) {
                        $endDateOfMonth = Carbon::create($year, $i, 1)->endOfMonth();
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDate,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDate,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    } elseif ($startDateMonth < $i && $endDateMonth > $i) {
                        $startDateOfMonth = Carbon::create($year, $i, 1)->startOfMonth();
                        $endDateOfMonth = Carbon::create($year, $i, 1)->endOfMonth();
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDateOfMonth,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDateOfMonth,
                            endDate: $endDateOfMonth,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    } elseif ($startDateMonth < $i && $endDateMonth == $i) {
                        $startDateOfMonth = Carbon::create($year, $i, 1)->startOfMonth();
                        $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteria,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            startDate: $startDateOfMonth,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->count();
                        $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(
                            criteria: $criteriaForCommission,
                            whereBetweenCriteria: $whereBetweenCriteria,
                            relations: ['fee'],
                            startDate: $startDateOfMonth,
                            endDate: $endDate,
                            month: $i,
                            year: $year
                        )->sum('fee.admin_commission');
                        $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                    }

                } else {
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }
            }
        } elseif ($startDate->year <= $endDate->year) {
            $startYear = $startDate->year;
            $endYear = $endDate->year;
            for ($i = $startYear; $i <= $endYear; $i++) {
                $label[] = $i;
                if ($i == $startYear && $i == $endYear) {
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $endDate, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $endDate, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($i == $startYear && $i < $endYear) {
                    $endDay = Carbon::create($i, 12, 31)->endOfDay();
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDate, endDate: $endDay, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDate, endDate: $endDay, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($i > $startYear && $i < $endYear) {
                    $startDay = Carbon::create($i, 11, 11)->startOfDay();
                    $endDay = Carbon::create($i, 12, 31)->endOfDay();
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDay, endDate: $endDay, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDay, endDate: $endDay, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } elseif ($i > $startYear && $i == $endYear) {
                    $startDay = Carbon::create($i, 1, 1)->startOfDay();
                    $totalTripRequest[$i] = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteria, whereBetweenCriteria: $whereBetweenCriteria, startDate: $startDay, endDate: $endDate, year: $i)->count();
                    $adminCommission = $this->tripRequestRepository->getZoneWiseEarning(criteria: $criteriaForCommission, whereBetweenCriteria: $whereBetweenCriteria, relations: ['fee'], startDate: $startDay, endDate: $endDate, year: $i)->sum('fee.admin_commission');
                    $totalAdminCommission[$i] = number_format($adminCommission, $points, '.', '');
                } else {
                    $totalTripRequest[$i] = 0;
                    $totalAdminCommission[$i] = 0;
                }
            }

        }


        return [
            'label' => $label,
            'totalTripRequest' => $totalTripRequest,
            'totalAdminCommission' => $totalAdminCommission
        ];
    }


}
