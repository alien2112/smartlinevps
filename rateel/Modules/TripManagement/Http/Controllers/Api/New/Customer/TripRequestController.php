<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Customer;

use App\Events\RideRequestEvent;
use App\Jobs\SendPushNotificationJob;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\Gateways\Entities\Setting;
use Modules\BusinessManagement\Http\Requests\RideListRequest;
use Modules\FareManagement\Service\Interface\ParcelFareServiceInterface;
use Modules\FareManagement\Service\Interface\ParcelFareWeightServiceInterface;
use Modules\FareManagement\Service\Interface\TripFareServiceInterface;
use Modules\Gateways\Traits\Payment;
use Modules\PromotionManagement\Service\Interface\CouponSetupServiceInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Http\Requests\GetEstimatedFaresOrNotRequest;
use Modules\TripManagement\Http\Requests\RideRequestCreate;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interface\FareBiddingServiceInterface;
use Modules\TripManagement\Service\Interface\RecentAddressServiceInterface;
use Modules\TripManagement\Service\Interface\RejectedDriverRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TempTripNotificationServiceInterface;
use Modules\TripManagement\Service\Interface\TravelRideServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestTimeServiceInterface;
use Modules\TripManagement\Transformers\FareBiddingResource;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interface\UserServiceInterface;
use Modules\UserManagement\Transformers\LastLocationResource;
use Modules\ZoneManagement\Service\Interface\ZoneServiceInterface;

class TripRequestController extends Controller
{
    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait;
    protected $tripRequestservice;
    protected $tempTripNotificationService;
    protected $fareBiddingService;
    protected $userLastLocation;
    protected $userService;
    protected $driverDetailService;
    protected $rejectedDriverRequestService;
    protected $couponService;
    protected $zoneService;
    protected $tripFareService;
    protected $parcelFareService;
    protected $parcelFareWeightService;
    protected $recentAddressService;
    protected $tripRequestTimeService;
    protected TravelRideServiceInterface $travelRideService;

    public function __construct(
        TripRequestServiceInterface $tripRequestservice,
        TempTripNotificationServiceInterface $tempTripNotificationService,
        FareBiddingServiceInterface $fareBiddingService,
        UserLastLocationInterface $userLastLocation,
        UserServiceInterface $userService,
        DriverDetailServiceInterface $driverDetailService,
        RejectedDriverRequestServiceInterface $rejectedDriverRequestService,
        CouponSetupServiceInterface $couponService,
        ZoneServiceInterface $zoneService,
        TripFareServiceInterface $tripFareService,
        ParcelFareWeightServiceInterface $parcelFareWeightService,
        ParcelFareServiceInterface $parcelFareService,
        RecentAddressServiceInterface $recentAddressService,
        TripRequestTimeServiceInterface $tripRequestTimeService,
        TravelRideServiceInterface $travelRideService
    ) {
        $this->tripRequestservice = $tripRequestservice;
        $this->tempTripNotificationService = $tempTripNotificationService;
        $this->fareBiddingService = $fareBiddingService;
        $this->userLastLocation = $userLastLocation;
        $this->userService = $userService;
        $this->driverDetailService = $driverDetailService;
        $this->rejectedDriverRequestService = $rejectedDriverRequestService;
        $this->couponService = $couponService;
        $this->zoneService = $zoneService;
        $this->tripFareService = $tripFareService;
        $this->parcelFareWeightService = $parcelFareWeightService;
        $this->parcelFareService = $parcelFareService;
        $this->recentAddressService = $recentAddressService;
        $this->tripRequestTimeService = $tripRequestTimeService;
        $this->travelRideService = $travelRideService;
    }



    public function createRideRequest(RideRequestCreate $request): JsonResponse
    {
        $trip = $this->tripRequestservice->getCustomerIncompleteRide();
        if ($trip) {

            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }

        if (empty($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404), 403);
        }

        // Check trip type (normal or travel)
        $tripType = $request->input('trip_type', 'normal');
        $isTravel = $tripType === 'travel';

        // Handle coordinates - they might be sent as JSON string or array
        $pickupCoordinates = is_array($request['pickup_coordinates'])
            ? $request['pickup_coordinates']
            : json_decode($request['pickup_coordinates'], true);
        $destinationCoordinates = is_array($request['destination_coordinates'])
            ? $request['destination_coordinates']
            : json_decode($request['destination_coordinates'], true);
        $customer_coordinates = is_array($request['customer_coordinates'])
            ? $request['customer_coordinates']
            : json_decode($request['customer_coordinates'], true);
        $pickup_point = new Point($pickupCoordinates[1], $pickupCoordinates[0], 4326);
        $destination_point = new Point($destinationCoordinates[1], $destinationCoordinates[0], 4326);
        $customer_point = new Point($customer_coordinates[1], $customer_coordinates[0], 4326);

        // Travel mode validations
        if ($isTravel) {
            $validator = Validator::make($request->all(), [
                'scheduled_at' => 'required|date|after:now',
                'seats_requested' => 'nullable|integer|min:1|max:10',
                'offer_price' => 'required|numeric|min:0',
                'estimated_distance' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
            }

            // Auto-calculate min_price based on admin settings
            $distanceKm = $request->input('estimated_distance', 0);
            $baseFare = $request->estimated_fare ?? 0;

            // Method 1: Use travel price per km from admin settings
            $perKmRate = (float)(get_cache('travel_price_per_km'));
            if ($perKmRate > 0 && $distanceKm > 0) {
                $minPrice = round($distanceKm * $perKmRate, 2);
            } else {
                // Method 2: Use travel multiplier on base fare
                $travelMultiplier = (float)(get_cache('travel_price_multiplier') ?? 1.0);
                $minPrice = round($baseFare * $travelMultiplier, 2);
            }

            // Validate offer_price >= auto-calculated min_price
            if ($request->offer_price < $minPrice) {
                return response()->json(responseFormatter(constant: DEFAULT_400, errors: [[
                    'code' => 'offer_price',
                    'message' => "Offer price must be at least {$minPrice} (minimum calculated based on distance)"
                ]]), 400);
            }
        }

        $mergeData = [
            'customer_id' => auth('api')->id(),
            'zone_id' => $request->header('zoneId'),
            'pickup_coordinates' => $pickup_point,
            'destination_coordinates' => $destination_point,
            'estimated_fare' => $request->estimated_fare,
            'actual_fare' => (get_cache('bid_on_fare') ?? 0) ? $request->actual_fare : $request->estimated_fare,
            'customer_request_coordinates' => $customer_point,
            'trip_type' => $tripType,
        ];

        // Add travel-specific fields
        if ($isTravel) {
            $mergeData['scheduled_at'] = $request->scheduled_at;
            $mergeData['seats_requested'] = $request->input('seats_requested', 1);
            $mergeData['min_price'] = $minPrice;  // Auto-calculated above
            $mergeData['offer_price'] = $request->offer_price;
            $mergeData['travel_radius_km'] = (float)(get_cache('travel_search_radius') ?? 50);

            // Backward compatibility: also set old travel fields
            $mergeData['is_travel'] = true;
            $mergeData['fixed_price'] = $request->offer_price;
            $mergeData['travel_date'] = $request->scheduled_at;
            $mergeData['travel_passengers'] = $request->input('seats_requested', 1);
            $mergeData['travel_status'] = 'pending';
        }

        $request->merge($mergeData);

        $trip = $this->tripRequestservice->makeRideRequest($request, $pickupCoordinates);


        return response()->json(responseFormatter(TRIP_REQUEST_STORE_200, $trip));
    }

    public function getEstimatedFare(GetEstimatedFaresOrNotRequest $request): JsonResponse
    {

        $trip = $this->tripRequestservice->getCustomerIncompleteRide();
        if ($trip) {
            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }

        $zoneId = $request->header('zoneId');
        $zone = $this->zoneService->findOne(id: $zoneId);
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        // Check if this is a travel mode request
        $tripType = $request->input('trip_type', 'normal');
        $isTravelMode = $tripType === 'travel';

        $user = auth('api')->user();
        $pickupCoordinates = is_array($request->pickup_coordinates)
            ? $request->pickup_coordinates
            : json_decode($request->pickup_coordinates, true);
        $destinationCoordinates = is_array($request->destination_coordinates)
            ? $request->destination_coordinates
            : json_decode($request->destination_coordinates, true);

        $intermediate_coordinates = [];
        if (!is_null($request['intermediate_coordinates'])) {
            $intermediate_coordinates = is_array($request['intermediate_coordinates'])
                ? $request['intermediate_coordinates']
                : json_decode($request->intermediate_coordinates, true);
            $maximum_intermediate_point = 2;
            if (count($intermediate_coordinates) > $maximum_intermediate_point) {

                return response()->json(responseFormatter(MAXIMUM_INTERMEDIATE_POINTS_403), 403);
            }
        }

        $pickupCoordinatesPoints = new Point($pickupCoordinates[1], $pickupCoordinates[0], 4326);
        $pickup_location_coverage = $this->zoneService->getByPoints($pickupCoordinatesPoints)->whereId($zoneId)->first();

        $destinationCoordinatesPoints = new Point($destinationCoordinates[1], $destinationCoordinates[0], 4326);
        $destination_location_coverage = $this->zoneService->getByPoints($destinationCoordinatesPoints)->whereId($zoneId)->first();

        if (!$pickup_location_coverage || !$destination_location_coverage) {
            return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
        }
        if ($request->type == 'ride_request') {
            $tripFare = $this->tripFareService->getBy(criteria: ['zone_id' => $zoneId], relations: ['zone', 'vehicleCategory'], limit: 1000, offset: 1);
            //Get to know in zone's vehicle category car and motorcycle available or not
            $available_categories = $tripFare->map(function ($query) {
                return $query->vehicleCategory->type;
            })->unique()
                ->toArray();

            if (empty($available_categories)) {

                return response()->json(responseFormatter(NO_ACTIVE_CATEGORY_IN_ZONE_404), 403);
            }
        }

        if ($request->type == 'parcel') {
            $parcelWeights = $this->parcelFareWeightService->getAll(limit: 99999, offset: 1);
            $parcel_weight_id = null;

            $parcel_category_id = $request->parcel_category_id;

            foreach ($parcelWeights as $pw) {
                if ($request->parcel_weight >= $pw->min_weight && $request->parcel_weight <= $pw->max_weight) {
                    $parcel_weight_id = $pw['id'];
                }
            }
            if (is_null($parcel_weight_id)) {

                return response()->json(responseFormatter(PARCEL_WEIGHT_400), 403);
            }

            $tripFare = $this->parcelFareService->getBy(criteria: [
                'zone_id' => $zoneId,
                'parcel_weight_id' => $parcel_weight_id,
                'parcel_category_id' => $parcel_category_id,
            ]);
        }

        $getRoutes = getRoutes(
            originCoordinates: $pickupCoordinates,
            destinationCoordinates: $destinationCoordinates,
            intermediateCoordinates: $intermediate_coordinates,
            drivingMode: $request->type == 'ride_request' ? (count($available_categories) == 2 ? ["DRIVE", 'TWO_WHEELER'] : ($available_categories[0] == 'car' ? ['DRIVE'] : ['TWO_WHEELER'])) : ['TWO_WHEELER'],
        );

        // Check if getRoutes returned an error (integer status code) instead of array
        if (!is_array($getRoutes)) {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, 'Unable to find route. API returned status: ' . $getRoutes), 404);
        }

        if ($getRoutes[1]['status'] !== "OK") {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $getRoutes[1]['error_detail'] ?? 'Route not found'), 404);
        }
        $estimated_fare = $this->estimatedFare(
            tripRequest: $request->all(),
            routes: $getRoutes,
            zone_id: $zoneId,
            tripFare: $tripFare,
        );

        // Apply normal trip pricing overrides (if enabled in admin settings)
        if (!$isTravelMode) {
            $distanceKm = is_array($estimated_fare) ? ($estimated_fare['distance'] ?? 0) : 0;
            $calculatedFare = is_array($estimated_fare) ? ($estimated_fare['estimated_fare'] ?? 0) : $estimated_fare;

            // Get admin settings
            $normalPerKmRate = (float)(get_cache('normal_price_per_km'));
            $normalPerKmEnabled = (bool)(businessConfig('normal_price_per_km', TRIP_SETTINGS)?->value['status'] ?? false);
            $normalMinPrice = (float)(get_cache('normal_min_price'));
            $normalMinPriceEnabled = (bool)(businessConfig('normal_min_price', TRIP_SETTINGS)?->value['status'] ?? false);

            // Step 1: Calculate fare (per-km or use existing)
            $perKmFare = 0;
            if ($normalPerKmEnabled && $normalPerKmRate > 0 && $distanceKm > 0) {
                $perKmFare = round($distanceKm * $normalPerKmRate, 2);
            }

            // Step 2: Determine minimum price (floor)
            $minPriceFloor = 0;
            if ($normalMinPriceEnabled && $normalMinPrice > 0) {
                $minPriceFloor = $normalMinPrice;
            }

            // Step 3: Apply priority logic - min_price is the floor, per-km only if higher
            $finalFare = $calculatedFare; // Start with existing engine result
            $pricingMethod = 'default_engine';

            if ($normalPerKmEnabled && $perKmFare > 0) {
                // Use per-km if enabled, but respect minimum floor
                if ($normalMinPriceEnabled && $minPriceFloor > 0) {
                    // Both enabled: max(per_km, min_price)
                    $finalFare = max($perKmFare, $minPriceFloor);
                    $pricingMethod = $perKmFare >= $minPriceFloor ? 'per_km' : 'min_price_floor';
                } else {
                    // Only per-km enabled
                    $finalFare = $perKmFare;
                    $pricingMethod = 'per_km';
                }
            } elseif ($normalMinPriceEnabled && $minPriceFloor > 0) {
                // Only minimum enabled: max(default_engine, min_price)
                $finalFare = max($calculatedFare, $minPriceFloor);
                $pricingMethod = $calculatedFare >= $minPriceFloor ? 'default_engine' : 'min_price_floor';
            }

            // Update the response
            if (is_array($estimated_fare)) {
                $estimated_fare['estimated_fare'] = $finalFare;
                $estimated_fare['pricing_method'] = $pricingMethod;
                $estimated_fare['breakdown'] = [
                    'default_engine_fare' => $calculatedFare,
                    'per_km_rate' => $normalPerKmRate,
                    'per_km_fare' => $perKmFare > 0 ? $perKmFare : null,
                    'min_price_floor' => $minPriceFloor > 0 ? $minPriceFloor : null,
                    'final_fare' => $finalFare,
                ];
            } else {
                $estimated_fare = $finalFare;
            }
        }

        $debugRoutes = filter_var($request->input('debug_routes'), FILTER_VALIDATE_BOOLEAN);
        if ($debugRoutes) {
            return response()->json(responseFormatter(DEFAULT_200, [
                'estimated_fare' => $estimated_fare,
                'routes' => $getRoutes,
            ]), 200);
        }
        //Recent address store
        $this->recentAddressService->create(data: [
            'user_id' => $user->id,
            'zone_id' => $zoneId,
            'pickup_coordinates' => $pickupCoordinatesPoints,
            'destination_coordinates' => $destinationCoordinatesPoints,
            'pickup_address' => $request->pickup_address,
            'destination_address' => $request->destination_address,
        ]);

        // For travel mode, return min_price and suggested offer range
        if ($isTravelMode) {
            // Extract the base fare from estimated_fare array
            $baseFare = is_array($estimated_fare) ? ($estimated_fare['estimated_fare'] ?? 0) : $estimated_fare;
            $distanceKm = is_array($estimated_fare) ? ($estimated_fare['distance'] ?? 0) : 0;

            // Calculate min_price based on admin settings (price per km)
            // Get base per-km rate from trip fare settings or use fare from estimation
            $travelMultiplier = (float)(get_cache('travel_price_multiplier') ?? 1.0); // Default no markup
            $minPrice = round($baseFare * $travelMultiplier, 2);

            // Alternative: Use distance-based calculation if per_km_rate is set
            $perKmRate = (float)(get_cache('travel_price_per_km'));
            if ($perKmRate > 0 && $distanceKm > 0) {
                $minPrice = round($distanceKm * $perKmRate, 2);
            }

            // Suggest offer price range (min to 1.5x min)
            $maxSuggestedOffer = round($minPrice * 1.5, 2);

            $travelFare = [
                'trip_type' => 'travel',
                'min_price' => $minPrice,  // Auto-calculated from admin settings
                'suggested_offer_range' => [
                    'min' => $minPrice,
                    'max' => $maxSuggestedOffer,
                ],
                'distance_km' => $distanceKm,
                'duration_min' => is_array($estimated_fare) ? ($estimated_fare['duration'] ?? 0) : 0,
                'currency' => get_cache('currency_code') ?? 'USD',
                'note' => 'Minimum price calculated automatically. Set your offer_price (must be >= min_price). Higher offers may attract drivers faster.',
                'pricing_info' => [
                    'base_fare' => $baseFare,
                    'travel_multiplier' => $travelMultiplier,
                    'per_km_rate' => $perKmRate ?? null,
                ],
            ];

            // Include original estimated_fare details if array
            if (is_array($estimated_fare)) {
                $travelFare = array_merge($estimated_fare, $travelFare);
            }

            return response()->json(responseFormatter(DEFAULT_200, $travelFare), 200);
        }

        return response()->json(responseFormatter(DEFAULT_200, $estimated_fare), 200);
    }

    public function rideList(RideListRequest $request): JsonResponse
    {

        if (!is_null($request->filter) && $request->filter != 'custom_date') {
            $date = getDateRange($request->filter);
        } elseif (!is_null($request->filter)) {
            $date = getDateRange([
                'start' => $request->start,
                'end' => $request->end
            ]);
        }
        $criteria = ['customer_id' => auth('api')->id()];
        $whereBetweenCriteria = [];
        if (!empty($date)) {
            $whereBetweenCriteria = ['created_at', [$date['start'], $date['end']]];
        }
        if (!is_null($request->status)) {
            $criteria['current_status'] = [$request->status];
        }

        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee'];
        $data = $this->tripRequestservice->getWithAvg(criteria: $criteria, limit: $request['limit'], offset: $request['offset'], relations: $relations, withAvgRelation: ['driverReceivedReviews', 'rating'], whereBetweenCriteria: $whereBetweenCriteria);
        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }

    public function rideDetails($trip_request_id): JsonResponse
    {


        $data = $this->tripRequestservice->findOneWithAvg(criteria: ['id' => $trip_request_id], relations: [
            'driver', 'vehicle.model', 'vehicleCategory', 'tripStatus',
            'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo'
        ], withAvgRelation: ['customerReceivedReviews', 'rating']);
        if (!$data) {

            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        $resource = TripRequestResource::make($data->append('distance_wise_fare'));
        return response()->json(responseFormatter(DEFAULT_200, $resource));
    }

    public function biddingList($trip_request_id, Request $request): JsonResponse
    {

        $bidding = $this->fareBiddingService->getWithAvg(
            criteria: ['trip_request_id' => $trip_request_id],
            limit: $request['limit'],
            offset: $request['offset'],
            relations: ['driver_last_location', 'driver', 'trip_request', 'driver.vehicle.model'],
            withAvgRelation: ['customerReceivedReviews', 'rating']
        );
        $bidding = FareBiddingResource::collection($bidding);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $bidding, limit: $request['limit'], offset: $request['offset']));
    }


    public function driversNearMe(Request $request): JsonResponse
    {
        if (is_null($request->header('zoneId'))) {
            return response()->json(responseFormatter(ZONE_404));
        }

        $tripType = $request->input('trip_type', 'normal');
        $isTravel = $tripType === 'travel';

        if ($isTravel) {
            // Travel mode: Search for VIP drivers with approved travel status
            // Uses larger radius (default 50km) and filters for travel-approved VIP drivers only
            $travelRadius = (float)(get_cache('travel_search_radius') ?? 50);
            
            $driverList = $this->travelRideService->getAvailableVipDrivers(
                lat: (float)$request->latitude,
                lng: (float)$request->longitude,
                radiusKm: $travelRadius
            );
            
            // Transform to match LastLocationResource format
            $lastLocationDriver = $driverList->map(function ($location) {
                return [
                    'id' => $location->id,
                    'user_id' => $location->user_id,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'distance_km' => $location->distance_km ?? null,
                    'is_travel_approved' => true,
                    'user' => $location->user ? [
                        'id' => $location->user->id,
                        'first_name' => $location->user->first_name,
                        'last_name' => $location->user->last_name,
                        'profile_image' => $location->user->profile_image,
                        'phone' => $location->user->phone,
                        'vehicle' => $location->user->vehicle ? [
                            'id' => $location->user->vehicle->id,
                            'brand' => $location->user->vehicle->brand?->name ?? null,
                            'model' => $location->user->vehicle->model?->name ?? null,
                            'licence_plate_number' => $location->user->vehicle->licence_plate_number,
                        ] : null,
                    ] : null,
                ];
            });

            return response()->json(responseFormatter(constant: DEFAULT_200, content: [
                'trip_type' => 'travel',
                'search_radius_km' => $travelRadius,
                'drivers_count' => $driverList->count(),
                'drivers' => $lastLocationDriver,
                'note' => 'Only showing VIP drivers with approved travel status',
            ]));
        }

        // Normal mode: Use standard driver search
        $driverList = $this->tripRequestservice->findNearestDriver(
            latitude: $request->latitude,
            longitude: $request->longitude,
            zoneId: $request->header('zoneId'),
            radius: (float)(get_cache('search_radius') ?? 5)
        );
        $lastLocationDriver = LastLocationResource::collection($driverList);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $lastLocationDriver));
    }

    public function finalFareCalculation(Request $request): JsonResponse
    {
        $trip = $this->tripRequestservice->findOne(
            id: $request['trip_request_id'],
            relations: ['vehicleCategory.tripFares', 'coupon', 'time', 'coordinate', 'fee', 'tripStatus']
        );

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->current_status != 'completed' && $trip->current_status != 'cancelled' && $trip->type == 'ride_request') {

            return response()->json(responseFormatter(constant: TRIP_STATUS_NOT_COMPLETED_200));
        }

        if ($trip->paid_fare != 0 || ($trip->paid_fare == 0 && $trip->coupon_amount != null)) {

            $trip = new TripRequestResource($trip->append('distance_wise_fare'));
            return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
        }

        $fare = $trip->vehicle_category->tripFares->where('zone_id', $request->header('zoneId'))->first();
        if (!$fare) {

            return response()->json(responseFormatter(ZONE_404), 403);
        }

        //final fare calculation trait
        $calculated_data = $this->calculateFinalFare($trip, $fare);

        $attributes = [
            'paid_fare' => round($calculated_data['final_fare'], 2),
            'actual_fare' => round($calculated_data['actual_fare'], 2),
            'column' => 'id',
            'actual_distance' => $calculated_data['actual_distance'],
        ];
        $this->tripRequestservice->update(id: $request['trip_request_id'], data: $attributes);

        $trip = $this->tripRequestservice->findOne(id: $request['trip_request_id'], relations: ['vehicleCategory.tripFares', 'customer', 'driver', 'coupon', 'time', 'coordinate', 'fee', 'tripStatus']);
        $trip = new TripRequestResource($trip->append('distance_wise_fare'));
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }


    public function requestAction(Request $request): JsonResponse
    {


        $trip = $this->tripRequestservice->findOne(id: $request['trip_request_id'], relations: ['coordinate']);
        $driver = $this->userService->findOne(id: $request['driver_id'], relations: ['vehicle', 'driverDetails', 'lastLocations']);
        if (Cache::get($request['trip_request_id']) == ACCEPTED && $trip->driver_id == $driver->id) {

            return response()->json(responseFormatter(DEFAULT_UPDATE_200));
        }

        $user_status = $driver->driverDetails->availability_status;
        if ($user_status != 'on_bidding' && $user_status != 'available') {

            return response()->json(responseFormatter(constant: DRIVER_403), 403);
        }
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if (!$driver->vehicle) {

            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }
        if (get_cache('bid_on_fare') ?? 0) {
            $checkBid = $this->fareBiddingService->getBy(criteria: ['trip_request_id' => $request['trip_request_id'], 'driver_id' => $request['driver_id']]);

            if (!$checkBid) {
                return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
            }
        }

        $env = env('APP_MODE');
        $smsConfig = Setting::where('settings_type', SMS_CONFIG)->where('live_values->status', 1)->exists();
        $otp = ($env == "live" && $smsConfig) ? random_int(1000, 9999) : '0000';

        $assignedVehicleCategoryId = $trip->vehicle_category_id;
        if (empty($assignedVehicleCategoryId)) {
            $assignedVehicleCategoryId = $driver->vehicle->category_id ?? null;
            if (is_string($assignedVehicleCategoryId)) {
                $decodedCategoryIds = json_decode($assignedVehicleCategoryId, true);
                if (is_array($decodedCategoryIds) && !empty($decodedCategoryIds)) {
                    $assignedVehicleCategoryId = $decodedCategoryIds[0];
                }
            } elseif (is_array($assignedVehicleCategoryId)) {
                $assignedVehicleCategoryId = $assignedVehicleCategoryId[0] ?? null;
            }
        }

        $attributes = [
            'column' => 'id',
            'driver_id' => $driver->id,
            'otp' => $otp,
            'vehicle_id' => $driver->vehicle->id,
            'current_status' => ACCEPTED,
            'vehicle_category_id' => $assignedVehicleCategoryId,
        ];

        if ($request['action'] == ACCEPTED) {
            DB::beginTransaction();
            Cache::put($trip->id, ACCEPTED, now()->addHour());

            //set driver availability_status as on_trip
            $this->driverDetailService->update(id: $driver->id, data: ['column' => 'user_id', 'availability_status' => 'on_trip']);

            //deleting exiting rejected driver request for this trip
            $this->rejectedDriverRequestService->deleteBy(criteria: ['trip_request_id' => $trip->id,]);
            if (get_cache('bid_on_fare') ?? 0) {
                $allBidding = $this->fareBiddingService->getBy(criteria: [
                    'trip_request_id' => $request['trip_request_id']
                ], limit: 200, offset: 1);

                if (count($allBidding) > 0) {
                    $actual_fare = $allBidding
                        ->where('driver_id', $request['driver_id'])
                        ->firstWhere('trip_request_id', $request['trip_request_id'])
                        ->bid_fare;
                    $attributes['actual_fare'] = $actual_fare;
                }
            }


            $data = $this->tripRequestservice->findOneBy(criteria: [
                'trip_request_id' => $request['trip_request_id'],
                'user_id' => [$driver->id]
            ], relations: ['user']);

            $push = getNotification('driver_assigned');
            if (!empty($data)) {
                $notification['title'] = translate($push['title']);
                $notification['description'] = translate($push['description']);
                $notification['status'] = $push['status'];
                $notification['ride_request_id'] = $trip->id;
                $notification['type'] = $trip->type;
                $notification['action'] = 'ride_started';

                dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
                $this->tripRequestservice->delete(id: $trip->id);
            }
            $driverArrivalTime = getRoutes(
                originCoordinates: [
                    $trip->coordinate->pickup_coordinates->getLat(),
                    $trip->coordinate->pickup_coordinates->getLng()
                ],
                destinationCoordinates: [
                    $driver->lastLocations->latitude,
                    $driver->lastLocations->longitude
                ],
            );

            // Check if getRoutes returned an error (integer status code) instead of array
            if (!is_array($driverArrivalTime)) {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, 'Unable to calculate driver arrival time. API returned status: ' . $driverArrivalTime), 404);
            }

            if ($driverArrivalTime[1]['status'] !== "OK") {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $driverArrivalTime[1]['error_detail'] ?? 'Route not found'), 404);
            }
            if ($trip->type == 'ride_request') {
                $attributes['driver_arrival_time'] = (float)($driverArrivalTime[0]['duration']) / 60;
            }

            //Trip update
            $this->tripRequestservice->update(id: $request['trip_request_id'], data: $attributes);
            DB::commit();

            $push = getNotification('bid_accepted');
            sendDeviceNotification(
                fcm_token: $driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'ride_' . $request->action,
                user_id: $driver->id
            );
        } else {
            if (get_cache('bid_on_fare') ?? 0) {
                $allBidding = $this->fareBiddingService->index(criteria: [
                    'trip_request_id' => $request['trip_request_id'],
                ], limit: 200, offset: 1);

                if (count($allBidding) > 0) {
                    foreach ($allBidding->pluck('id') as $bidId) {
                        $this->tripRequestservice->delete(id: $bidId);
                    }
                }
            }
        }

        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }


    public function rideResumeStatus(): JsonResponse
    {
        $trip = $this->tripRequestservice->getCustomerIncompleteRide();
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        $trip = TripRequestResource::make($trip);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    public function pendingParcelList(Request $request): JsonResponse
    {

        $attributes = [
            'limit' => $request->limit,
            'offset' => $request->offset,
            'column' => 'customer_id',
            'value' => auth()->id(),
            'whereNotNull' => 'customer_id',
        ];

        $trips = $this->tripRequestservice->pendingParcelList($attributes);
        $trips = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    public function applyCoupon(Request $request): JsonResponse
    {

        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['driver', 'fee']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->coupon_id) {

            return response()->json(responseFormatter(constant: COUPON_APPLIED_403), 403);
        }
        $user = auth('api')->user();
        $date = date('Y-m-d');

        $criteria = [
            ['coupon_code', $request->coupon_code],
            ['min_trip_amount', '<=', $trip->paid_fare],
            ['start_date', '<=', $date],
            ['end_date', '>=', $date]
        ];
        $coupon = $this->couponService->findOneBy($criteria);
        if (!$coupon) {

            return response()->json(responseFormatter(constant: COUPON_404, content: ['discount' => 0]), 403);
        }
        $response = $this->getCouponDiscount($user, $trip, $coupon);

        if ($response['discount'] != 0) {

            $trip = $this->tripRequestservice->validateDiscount(trip: $trip, response: $response, tripId: $request->trip_request_id, cuponId: $coupon->id);

            return response()->json(responseFormatter(constant: $response['message'], content: $trip));
        }

        return response()->json(responseFormatter(constant: $response['message'], content: $trip), 403);
    }

    public function rideStatusUpdate($trip_request_id, Request $request): JsonResponse
    {

        $trip = $this->tripRequestservice->findOne(id: $trip_request_id, relations: ['driver.lastLocations', 'time', 'coordinate', 'fee']);
        //
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        // Idempotent cancellation: if already cancelled and customer is trying to cancel, return success
        if ($trip->current_status == 'cancelled') {
            if ($request->status == 'cancelled') {
                $data = TripRequestResource::make($trip);
                return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
            }
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        // Idempotent completion: if already completed and trying to complete, return success
        if ($trip->current_status == 'completed') {
            if ($request->status == 'completed') {
                $data = TripRequestResource::make($trip);
                return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
            }
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }

        $attributes = [
            'column' => 'id',
            'value' => $trip_request_id,
            'current_status' => $request['status'],
        ];

        if ($request->status == 'cancelled' && ($trip->current_status == ACCEPTED || $trip->current_status == PENDING)) {
            $this->tripRequestservice->handleCancelledTrip($trip, $attributes, $request['trip_request_id']);
        }
        if ($trip->is_paused) {

            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        if ($trip->driver_id && ($request->status == 'completed' || $request->status == 'cancelled') && $trip->current_status == ONGOING) {

            $this->handleCompletedOrCancelledTrip($trip, $request, $attributes);
        }

        $updatedTrip =  $this->tripRequestservice->handleCustomerRideStatusUpdate($trip, $request, $attributes);


        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($updatedTrip)));
    }

    public function cancelCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->tripRequestservice->findOne(id: $request->trip_request_id, relations: ['driver']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if (is_null($trip->coupon_id)) {
            return response()->json(responseFormatter(constant: COUPON_404), 403);
        }

        DB::beginTransaction();
        $this->tripRequestservice->removeCouponData($trip);
        DB::commit();

        $push = getNotification('coupon_removed');
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'coupon_removed',
            user_id: $trip->driver->id
        );

        $trip = new TripRequestResource($trip->append('distance_wise_fare'));
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200, content: $trip));
    }

    public function ignoreBidding(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bidding_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $this->fareBiddingService->update(id: $request->bidding_id, data: ['is_ignored' => 1]);

        return response()->json(responseFormatter(constant: DEFAULT_200));
    }

    public function arrivalTime(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $time = $this->tripRequestTimeService->findOneBy(criteria: ['trip_request_id' => $request->trip_request_id]);

        if (!$time) {

            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        $time->customer_arrives_at = now();
        $time->save();

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function storeScreenshot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'file' => 'required|mimes:jpg,png,webp'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $this->tripRequestservice->update(id: $request->trip_request_id, data: [
            'map_screenshot' => $request->file,
        ],);

        return response()->json(responseFormatter(DEFAULT_200));
    }

    public function unpaidParcelRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $relation = [
            'parcel' => ['payer', 'sender'], 'customer', 'driver', 'vehicle_category', 'vehicle_category.tripFares', 'vehicle', 'coupon',  'time',
            'coordinate', 'fee', 'trip_status', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo'
        ];

        $criteria = [
            'type' => 'parcel',
            'customer_id' => auth()->id(),
            'payment_status' => UNPAID
        ];

        $whereNotNullCriteria = ['driver_id'];

        $trips = $this->tripRequestservice->getWithAvg(
            criteria: $criteria,
            relations: $relation,
            whereNotNullCriteria: $whereNotNullCriteria,
            limit: $request->limit,
            offset: $request->offset,
        );
        $trips = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    private function handleCompletedOrCancelledTrip($trip, $request, &$attributes): void
    {
        if ($request->status == 'cancelled') {
            $attributes['fee']['cancelled_by'] = 'customer';
        }

        $attributes['coordinate']['drop_coordinates'] = new Point(
            $trip->driver->lastLocations->longitude,
            $trip->driver->lastLocations->latitude
        );

        // Set driver availability_status back to available
        $driverDetails = $this->driverDetailService->findOneBy(criteria: ['user_id' => $trip->driver_id]);
        if ($driverDetails) {
            if ($trip->type == RIDE_REQUEST) {
                $driverDetails->ride_count = 0;
            } elseif ($request->status == 'completed') {
                --$driverDetails->parcel_count;
            }
            $driverDetails->availability_status = 'available';
            $driverDetails->save();
        }

        // Send notification to driver
        $push = getNotification('ride_' . $request->status);
        if (!is_null($trip->driver->fcm_token)) {
            $action = ($request->status == 'cancelled' && $trip->type == PARCEL) ? 'parcel_cancelled' : 'ride_completed';
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: $action,
                user_id: $trip->driver->id
            );
        }
    }

    /**
     * Create a travel ride request (VIP only, fixed price, scheduled)
     */
    public function createTravelRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_category_id' => 'required|uuid|exists:vehicle_categories,id',
            'pickup_coordinates' => 'required',
            'destination_coordinates' => 'required',
            'pickup_address' => 'required|string',
            'destination_address' => 'required|string',
            'estimated_distance' => 'required|numeric|min:0',
            'travel_date' => 'nullable|date|after:now',
            'travel_passengers' => 'nullable|integer|min:1|max:10',
            'travel_luggage' => 'nullable|integer|min:0|max:10',
            'travel_notes' => 'nullable|string|max:500',
            'payment_method' => 'nullable|string|in:cash,wallet,card',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        // Check for incomplete rides
        $existingTrip = $this->tripRequestservice->getCustomerIncompleteRide();
        if ($existingTrip) {
            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }

        if (empty($request->header('zoneId'))) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        // Handle coordinates
        $pickupCoordinates = is_array($request->pickup_coordinates)
            ? $request->pickup_coordinates
            : json_decode($request->pickup_coordinates, true);

        $request->merge([
            'zone_id' => $request->header('zoneId'),
        ]);

        try {
            $trip = $this->travelRideService->createTravelRequest($request, $pickupCoordinates);

            return response()->json(responseFormatter(TRIP_REQUEST_STORE_200, TripRequestResource::make($trip)));
        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => $e->getMessage()]]), 400);
        }
    }

    /**
     * Get travel ride status
     */
    public function getTravelStatus(string $tripId): JsonResponse
    {
        $trip = $this->tripRequestservice->findOne(
            id: $tripId,
            relations: ['driver', 'vehicle.model', 'vehicleCategory', 'coordinate', 'fee', 'time', 'tripStatus']
        );

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        // Verify ownership
        if ($trip->customer_id !== auth('api')->id()) {
            return response()->json(responseFormatter(constant: DEFAULT_403), 403);
        }

        // Verify it's a travel request
        if (!$trip->isTravel()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => 'Not a travel request']]), 400);
        }

        return response()->json(responseFormatter(DEFAULT_200, TripRequestResource::make($trip)));
    }

    /**
     * Cancel a travel ride request
     */
    public function cancelTravelRequest(Request $request, string $tripId): JsonResponse
    {
        $trip = $this->tripRequestservice->findOne(id: $tripId);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        // Verify ownership
        if ($trip->customer_id !== auth('api')->id()) {
            return response()->json(responseFormatter(constant: DEFAULT_403), 403);
        }

        // Verify it's a travel request
        if (!$trip->isTravel()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => 'Not a travel request']]), 400);
        }

        // Can only cancel pending or expired travel requests
        if (!in_array($trip->travel_status, ['pending', 'expired'])) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => 'Cannot cancel travel request in current status']]), 400);
        }

        $reason = $request->input('reason', 'Cancelled by customer');

        try {
            $this->travelRideService->cancelTravelRequest($tripId, $reason);
            $trip->refresh();

            return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => $e->getMessage()]]), 400);
        }
    }

    /**
     * Get travel price estimate
     */
    public function getTravelPriceEstimate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_category_id' => 'required|uuid|exists:vehicle_categories,id',
            'estimated_distance' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $zoneId = $request->header('zoneId');
        if (empty($zoneId)) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        try {
            $price = $this->travelRideService->calculateTravelPrice(
                $request->estimated_distance,
                $request->vehicle_category_id,
                $zoneId
            );

            return response()->json(responseFormatter(DEFAULT_200, [
                'fixed_price' => $price,
                'currency' => get_cache('currency_code') ?? 'EGP',
                'is_travel' => true,
                'note' => 'This is a fixed price. No surge pricing applies to travel rides.',
            ]));
        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => $e->getMessage()]]), 400);
        }
    }
}
