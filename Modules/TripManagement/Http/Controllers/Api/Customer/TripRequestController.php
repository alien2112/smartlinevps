<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use App\Broadcasting\CustomerTripCanceledAfterOngoingChannel;
use App\Events\CustomerCouponAppliedEvent;
use App\Events\CustomerCouponRemovedEvent;
use App\Events\CustomerTripCancelledAfterOngoingEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Events\CustomerTripRequestEvent;
use App\Jobs\SendPushNotificationJob;
use App\Services\RealtimeEventPublisher;
use Carbon\Carbon;
use Exception;
use Illuminate\Validation\Rule;
use MatanYadaev\EloquentSpatial\Enums\Srid;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\FareManagement\Interfaces\ParcelFareInterface;
use Modules\FareManagement\Interfaces\TripFareInterface;
use Modules\Gateways\Traits\Payment;
use Modules\ParcelManagement\Repositories\ParcelWeightRepository;
use Modules\PromotionManagement\Interfaces\CoupounInterface;
use Modules\TripManagement\Entities\FareBidding;
use Modules\TripManagement\Entities\RecentAddress;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Entities\TripRequestCoordinate;
use Modules\TripManagement\Entities\TripRequestTime;
use Modules\TripManagement\Interfaces\FareBiddingInterface;
use Modules\TripManagement\Interfaces\FareBiddingLogInterface;
use Modules\TripManagement\Interfaces\RecentAddressInterface;
use Modules\TripManagement\Interfaces\RejectedDriverRequestInterface;
use Modules\TripManagement\Interfaces\TempTripNotificationInterface;
use Modules\TripManagement\Interfaces\TripRequestInterfaces;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Lib\DiscountCalculationTrait;
use Modules\TripManagement\Transformers\FareBiddingResource;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Interfaces\DriverDetailsInterface;
use Modules\UserManagement\Interfaces\DriverInterface;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;
use Modules\UserManagement\Transformers\LastLocationResource;
use Modules\ZoneManagement\Interfaces\ZoneInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\ZoneManagement\Service\Interface\ZoneServiceInterface;

class TripRequestController extends Controller
{
    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, DiscountCalculationTrait, LevelUpdateCheckerTrait;

    public function __construct(
        private TripRequestInterfaces          $trip,
        private TripFareInterface              $tripFare,
        private ZoneInterface                  $zone,
        private RecentAddressInterface         $address,
        private FareBiddingInterface           $bidding,
        private UserLastLocationInterface      $lastLocation,
        private DriverInterface                $driver,
        private DriverDetailsInterface         $driverDetails,
        private RejectedDriverRequestInterface $rejected_request,
        private FareBiddingLogInterface        $bidding_log,
        private TempTripNotificationInterface  $temp_notification,
        private ParcelFareInterface            $parcel_fare,
        private ParcelWeightRepository         $parcel_weight,
        private CoupounInterface               $coupon,
        private ZoneServiceInterface           $zoneService,
    )
    {
    }

    /**
     * Summary of rideResumeStatus
     * @return JsonResponse
     */
    public function rideResumeStatus(): JsonResponse
    {
        $trip = $this->getResumeRide();
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        $trip = TripRequestResource::make($trip);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    /**
     * Show estimate fare calculation trip and parcel.
     *
     * @param Request $request
     * @return JsonResponse
     */
     
     
       public function getEstimatedFare(Request $request): JsonResponse
    {

        $trip = $this->getResumeRide();
        if ($request->type == "ride_request" && $trip) {
            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }
        $validator = Validator::make($request->all(), [
            'pickup_coordinates' => 'required',
            'destination_coordinates' => 'required',
            'pickup_address' => 'required',
            'destination_address' => 'required',
            'type' => 'required|in:parcel,ride_request',
            'parcel_weight' => 'required_if:type,parcel',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $user = auth('api')->user();

        $pickup_coordinates = json_decode($request['pickup_coordinates'], true);
        $destination_coordinates = json_decode($request['destination_coordinates'], true);
        $pickup_point = new Point($pickup_coordinates[0], $pickup_coordinates[1], 4326);
        $destination_point = new Point($destination_coordinates[0], $destination_coordinates[1], 4326);


        $intermediate_coordinates = [];
        if (!is_null($request['intermediate_coordinates'])) {
            $intermediate_coordinates = json_decode($request->intermediate_coordinates, true);
            $maximum_intermediate_point = 2;
            if (count($intermediate_coordinates) > $maximum_intermediate_point) {

                return response()->json(responseFormatter(MAXIMUM_INTERMEDIATE_POINTS_403), 403);
            }
        }
        $zone = $this->zoneService->getByPoints($pickup_point)->where('is_active', 1)->first();
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        $pickup_location_coverage = $this->zone->getByPoints($pickup_point)->whereId($zone->id)->first();
        $destination_location_coverage = $this->zone->getByPoints($destination_point)->whereId($zone->id)->first();

        if (!$pickup_location_coverage || !$destination_location_coverage) {
            return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
        }

        if ($request->type == 'ride_request') {
            $trip_fare = $this->tripFare->get(limit: 1000, offset: 1, attributes: [
                'query' => 'zone_id',
                'value' => $zone->id
            ], relations: ['zone', 'vehicleCategory']);
            // Filter out entries where vehicleCategory is null or is_active is 0
            $trip_fare = $trip_fare->filter(function ($item) {
                return $item->vehicleCategory !== null && $item->vehicleCategory->is_active != 0;
            });

            // Convert back to object and reset keys
            $trip_fare = $trip_fare->values();

            //Get to know in zone's vehicle category car and motorcycle available or not
            $available_categories = $trip_fare->map(function ($query) {
                return $query->vehicleCategory->type;
            })->unique()
                ->toArray();

            if (empty($available_categories)) {

                return response()->json(responseFormatter(NO_ACTIVE_CATEGORY_IN_ZONE_404), 403);
            }
        } else {
            $parcel_weights = $this->parcel_weight->get(limit: 99999, offset: 1);
            $parcel_weight_id = null;

            $parcel_category_id = $request->parcel_category_id;

            foreach ($parcel_weights as $pw) {
                if ($request->parcel_weight >= $pw->min_weight && $request->parcel_weight <= $pw->max_weight) {
                    $parcel_weight_id = $pw['id'];
                }
            }
            if (is_null($parcel_weight_id)) {

                return response()->json(responseFormatter(PARCEL_WEIGHT_400), 403);
            }

            $trip_fare = $this->parcel_fare->getZoneFare([
                'column' => 'zone_id',
                'value' => $zone->id,
                'parcel_weight_id' => $parcel_weight_id,
                'parcel_category_id' => $parcel_category_id,
            ]);

        }

        $get_routes = getRoutes(
            originCoordinates: $pickup_coordinates,
            destinationCoordinates: $destination_coordinates,
            intermediateCoordinates: $intermediate_coordinates,
            drivingMode: $request->type == 'ride_request' ? (count($available_categories) == 2 ? ["DRIVE", 'TWO_WHEELER'] : ($available_categories[0] == 'car' ? ['DRIVE'] : ['TWO_WHEELER'])) : ['TWO_WHEELER'],
        );

        // Check if getRoutes returned an error (integer status code) instead of array
        if (!is_array($get_routes)) {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, 'Unable to find route. API returned status: ' . $get_routes), 404);
        }

        if ($get_routes[1]['status'] !== "OK") {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $get_routes[1]['error_detail'] ?? 'Route not found'), 404);
        }
        $estimated_fare = $this->estimatedFare(
            tripRequest: $request->all(),
            routes: $get_routes,
            zone_id: $zone->id,
            zone: $zone,
            tripFare: $trip_fare,
            beforeCreate: true
        );
        $pickup_point = DB::raw("ST_GeomFromText('POINT({$pickup_coordinates[0]} {$pickup_coordinates[1]})', 4326)");
        $destination_point = DB::raw("ST_GeomFromText('POINT({$destination_coordinates[0]} {$destination_coordinates[1]})', 4326)");

        //Recent address store
        $this->address->store(attributes: [
            'user_id' => $user?->id,
            'zone_id' => $zone->id,
            'pickup_coordinates' => $pickup_point,
            'destination_coordinates' => $destination_point,
            'pickup_address' => $request->pickup_address,
            'destination_address' => $request->destination_address,
        ]);

        return response()->json(responseFormatter(DEFAULT_200, $estimated_fare), 200);
    }
     
    // public function getEstimatedFare(Request $request): JsonResponse
    // {

    //     $trip = $this->getResumeRide();
    //     if ($request->type == "ride_request" && $trip) {
    //         return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
    //     }
    //     $validator = Validator::make($request->all(), [
    //         'pickup_coordinates' => 'required',
    //         'destination_coordinates' => 'required',
    //         'pickup_address' => 'required',
    //         'destination_address' => 'required',
    //         'type' => 'required|in:parcel,ride_request',
    //         'parcel_weight' => 'required_if:type,parcel',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
    //     }

    //     $user = auth('api')->user();

    //     $pickup_coordinates = json_decode($request['pickup_coordinates'], true);
    //     $destination_coordinates = json_decode($request['destination_coordinates'], true);
    //     $pickup_point = new Point($pickup_coordinates[0], $pickup_coordinates[1]);
    //     $destination_point = new Point($destination_coordinates[0], $destination_coordinates[1]);


    //     $intermediate_coordinates = [];
    //     if (!is_null($request['intermediate_coordinates'])) {
    //         $intermediate_coordinates = json_decode($request->intermediate_coordinates, true);
    //         $maximum_intermediate_point = 2;
    //         if (count($intermediate_coordinates) > $maximum_intermediate_point) {

    //             return response()->json(responseFormatter(MAXIMUM_INTERMEDIATE_POINTS_403), 403);
    //         }
    //     }
    //     $zone = $this->zoneService->getByPoints($pickup_point)->where('is_active', 1)->first();
    //     if (!$zone) {
    //         return response()->json(responseFormatter(ZONE_404), 403);
    //     }

    //     $pickup_location_coverage = $this->zone->getByPoints($pickup_point)->whereId($zone->id)->first();
    //     $destination_location_coverage = $this->zone->getByPoints($destination_point)->whereId($zone->id)->first();

    //     if (!$pickup_location_coverage || !$destination_location_coverage) {
    //         return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
    //     }

    //     if ($request->type == 'ride_request') {
    //         $trip_fare = $this->tripFare->get(limit: 1000, offset: 1, attributes: [
    //             'query' => 'zone_id',
    //             'value' => $zone->id
    //         ], relations: ['zone', 'vehicleCategory']);
    //         // Filter out entries where vehicleCategory is null or is_active is 0
    //         $trip_fare = $trip_fare->filter(function ($item) {
    //             return $item->vehicleCategory !== null && $item->vehicleCategory->is_active != 0;
    //         });

    //         // Convert back to object and reset keys
    //         $trip_fare = $trip_fare->values();

    //         //Get to know in zone's vehicle category car and motorcycle available or not
    //         $available_categories = $trip_fare->map(function ($query) {
    //             return $query->vehicleCategory->type;
    //         })->unique()
    //             ->toArray();

    //         if (empty($available_categories)) {

    //             return response()->json(responseFormatter(NO_ACTIVE_CATEGORY_IN_ZONE_404), 403);
    //         }
    //     } else {
    //         $parcel_weights = $this->parcel_weight->get(limit: 99999, offset: 1);
    //         $parcel_weight_id = null;

    //         $parcel_category_id = $request->parcel_category_id;

    //         foreach ($parcel_weights as $pw) {
    //             if ($request->parcel_weight >= $pw->min_weight && $request->parcel_weight <= $pw->max_weight) {
    //                 $parcel_weight_id = $pw['id'];
    //             }
    //         }
    //         if (is_null($parcel_weight_id)) {

    //             return response()->json(responseFormatter(PARCEL_WEIGHT_400), 403);
    //         }

    //         $trip_fare = $this->parcel_fare->getZoneFare([
    //             'column' => 'zone_id',
    //             'value' => $zone->id,
    //             'parcel_weight_id' => $parcel_weight_id,
    //             'parcel_category_id' => $parcel_category_id,
    //         ]);

    //     }

    //     $get_routes = getRoutes(
    //         originCoordinates: $pickup_coordinates,
    //         destinationCoordinates: $destination_coordinates,
    //         intermediateCoordinates: $intermediate_coordinates,
    //         drivingMode: $request->type == 'ride_request' ? (count($available_categories) == 2 ? ["DRIVE", 'TWO_WHEELER'] : ($available_categories[0] == 'car' ? ['DRIVE'] : ['TWO_WHEELER'])) : ['TWO_WHEELER'],
    //     );
    //     if ($get_routes[1]['status'] !== "OK") {
    //         return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $get_routes[1]['error_detail']), 403);
    //     }
        
    //     $distance_km = $get_routes[0]['distance'] ?? 0; // Adjust based on actual $get_routes structure
    //     // $distance_km = is_numeric($distance) ? $distance / 1000 : 0;
    
    //     // Retrieve threshold fields from defaultFare relationship
    //     $default_fare = $zone->defaultFare;
    //     $minimum_distance_threshold = $default_fare->minimum_distance_threshold ?? 0;
    //     $fixed_price_below_threshold = $default_fare->fixed_price_below_threshold ?? 0;
        
    //     $estimated_fare = $this->estimatedFare(
    //         tripRequest: $request->all(),
    //         routes: $get_routes,
    //         zone_id: $zone->id,
    //         zone: $zone,
    //         tripFare: $trip_fare,
    //         beforeCreate: true
    //     );
        
    //     if ($distance_km > 0 && $distance_km <= $minimum_distance_threshold) {
    //         $points = (int)getSession('currency_decimal_point') ?? 0;
    //         if ($request->type == 'parcel') {
    //             // For parcel: Update fare-related fields in the single array
    //             $estimated_fare['estimated_fare'] = round($fixed_price_below_threshold, $points);
    //             $estimated_fare['discount_fare'] = round($fixed_price_below_threshold, $points); // Assume no discount for fixed price
    //             $estimated_fare['discount_amount'] = 0; // No discount applied
    //             $estimated_fare['extra_estimated_fare'] = 0; // No extra fare
    //             $estimated_fare['extra_discount_fare'] = 0;
    //             $estimated_fare['extra_discount_amount'] = 0;
    //             $estimated_fare['extra_fare_amount'] = 0;
    //             $estimated_fare['return_fee'] = 0; // Optional: Adjust based on requirements
    //             $estimated_fare['cancellation_fee'] = 0; // Optional: Adjust based on requirements
    //         } else {
    //             // For ride_request: Update fare-related fields for each vehicle category
    //             $estimated_fare = $estimated_fare->map(function ($fare) use ($fixed_price_below_threshold, $points) {
    //                 $fare['estimated_fare'] = round($fixed_price_below_threshold, $points);
    //                 $fare['discount_fare'] = round($fixed_price_below_threshold, $points); // Assume no discount
    //                 $fare['discount_amount'] = 0; // No discount applied
    //                 $fare['extra_estimated_fare'] = 0; // No extra fare
    //                 $fare['extra_discount_fare'] = 0;
    //                 $fare['extra_discount_amount'] = 0;
    //                 $fare['extra_fare_amount'] = 0;
    //                 $fare['return_fee'] = 0; // Optional: Adjust based on requirements
    //                 return $fare;
    //             });
    //         }
    //     }
        
    //     $pickup_point = DB::raw("ST_GeomFromText('POINT({$pickup_coordinates[0]} {$pickup_coordinates[1]})', 4326)");
    //     $destination_point = DB::raw("ST_GeomFromText('POINT({$destination_coordinates[0]} {$destination_coordinates[1]})', 4326)");

    //     //Recent address store
    //     $this->address->store(attributes: [
    //         'user_id' => $user?->id,
    //         'zone_id' => $zone->id,
    //         'pickup_coordinates' => $pickup_point,
    //         'destination_coordinates' => $destination_point,
    //         'pickup_address' => $request->pickup_address,
    //         'destination_address' => $request->destination_address,
    //     ]);

    //     return response()->json(responseFormatter(DEFAULT_200, $estimated_fare), 200);
    // }


    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return JsonResponse
     */
    public function createRideRequest(Request $request): JsonResponse
    {
        $trip = $this->getResumeRide();
        if ($request->type == "ride_request" && $trip && $request->trip_request_id == null) {
            return response()->json(responseFormatter(INCOMPLETE_RIDE_403), 403);
        }
        $validator = Validator::make($request->all(), [
            // Coordinates and Address Information
            'pickup_coordinates' => 'required_if:trip_request_id,null',
            'destination_coordinates' => 'required_if:trip_request_id,null',
            'customer_coordinates' => 'required_if:trip_request_id,null',
            'customer_request_coordinates' => 'required_if:trip_request_id,null',
            'pickup_address' => 'required_if:trip_request_id,null',
            'destination_address' => 'required_if:trip_request_id,null',

            // Trip and Fare Information
            'estimated_distance' => 'required_if:trip_request_id,null',
            'estimated_time' => 'required_if:trip_request_id,null',
            'estimated_fare' => 'required_if:trip_request_id,null|numeric',
            'bid' => 'required|bool',
            'actual_fare' => ['nullable',
                Rule::requiredIf(function () use ($request) {
                    return $request->bid;
                }), 'numeric'],

            // Return and Cancellation Fees
            'return_fee' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->type === 'parcel' && is_null($request->trip_request_id);
                })
            ],
            'cancellation_fee' => [
                Rule::requiredIf(function () use ($request) {
                    return $request->type === 'parcel' && is_null($request->trip_request_id);
                })
            ],

            // Vehicle Category
            'vehicle_category_id' => [
                'nullable',
                Rule::requiredIf(function () use ($request) {
                    return $request->type === 'ride_request' && is_null($request->trip_request_id);
                }), 'uuid'
            ],

            // Other Fields
            'note' => 'sometimes',
            'type' => 'required|in:parcel,ride_request',

            // Sender and Receiver Information (required only for parcel type)
            'sender_name' => 'required_if:type,parcel',
            'sender_phone' => 'required_if:type,parcel',
            'sender_address' => 'required_if:type,parcel',
            'receiver_name' => 'required_if:type,parcel',
            'receiver_phone' => 'required_if:type,parcel',
            'receiver_address' => 'required_if:type,parcel',

            // Parcel Information (required for parcel type)
            'parcel_category_id' => 'required_if:type,parcel',
            'weight' => 'required_if:type,parcel',
            'payer' => 'required_if:type,parcel',

            // Additional Fare Fields
            'extra_estimated_fare' => 'sometimes|numeric',
            'extra_discount_fare' => 'sometimes|numeric',
            'extra_discount_amount' => 'sometimes|numeric',
            'extra_return_fee' => 'sometimes|numeric',
            'extra_cancellation_fee' => 'sometimes|numeric',
            'extra_fare_amount' => 'sometimes|numeric',
            'extra_fare_fee' => 'sometimes|numeric',

            // Encoded Polyline and Zone Information
            'encoded_polyline' => 'sometimes',
            'zone_id' => 'required|uuid|exists:zones,id',

        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        if ($request->trip_request_id) {
            $save_trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']);
            $pickup_point = $save_trip->coordinate->pickup_coordinates;
            $destination_point = $save_trip->coordinate->destination_coordinates;
        } else {
            $pickup_coordinates = json_decode($request['pickup_coordinates'], true);
            $destination_coordinates = json_decode($request['destination_coordinates'], true);
            $pickup_point = new Point($pickup_coordinates[0], $pickup_coordinates[1], 4326);
            $destination_point = new Point($destination_coordinates[0], $destination_coordinates[1], 4326);
        }
//        dd($pickup_coordinates);

        $zone = $this->zoneService->getByPoints($pickup_point)->where('is_active', 1)->first();
        if (!$zone) {
            return response()->json(responseFormatter(ZONE_404), 403);
        }

        $pickup_location_coverage = $this->zone->getByPoints($pickup_point)->whereId($zone->id)->first();
        $destination_location_coverage = $this->zone->getByPoints($destination_point)->whereId($zone->id)->first();

        if (!$pickup_location_coverage || !$destination_location_coverage) {
            return response()->json(responseFormatter(ZONE_RESOURCE_404), 403);
        }

        // $minimum_fare = 50; // الحد الأدنى للأجرة
        // $fixed_fare = 50;
        $extraFare = $this->checkZoneExtraFare($zone);
        if (array_key_exists('bid', $request->all()) && $request['bid']) {
            $estimatedFare = $request['actual_fare'];
            $actualFare = $request['actual_fare'];
            $riseRequestCount = 1;
            $returnFee = $request->type == PARCEL ? $request->return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->cancellation_fee : 0;
        } elseif (!empty($extraFare)) {
            $estimatedFare = $request['extra_estimated_fare'];
            $actualFare = $request['extra_estimated_fare'];
            $riseRequestCount = 0;
            $returnFee = $request->type == PARCEL ? $request->extra_return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->extra_cancellation_fee : 0;
        } else {
            $estimatedFare = $request['estimated_fare'];
            $actualFare = $request['estimated_fare'];
            $riseRequestCount = 0;
            $returnFee = $request->type == PARCEL ? $request->return_fee : 0;
            $cancellationFee = $request->type == PARCEL ? $request->cancellation_fee : 0;
        }
        
        // if ($estimatedFare < $minimum_fare && !$request->bid) {
        //     $estimatedFare = $fixed_fare;
        //     $actualFare = $fixed_fare;
        // }


        if ($request->trip_request_id) {
            $save_trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']);
            $save_trip->estimated_fare = $estimatedFare;
            $save_trip->actual_fare = $actualFare;
            $save_trip->rise_request_count = $riseRequestCount;
            if ($save_trip->discount_id != null) {
                $save_trip->discount_id = null;
                $save_trip->discount_amount = null;
            }
            $save_trip->save();
        } else {
            $customer_coordinates = json_decode($request['customer_coordinates'], true);
            $customer_point = new Point($customer_coordinates[0], $customer_coordinates[1], 4326);
            $request->merge([
                'customer_id' => auth('api')->id(),
                'zone_id' => $zone->id,
                'pickup_coordinates' => $pickup_point,
                'destination_coordinates' => $destination_point,
                'estimated_fare' => $estimatedFare,
                'actual_fare' => $actualFare,
                'return_fee' => $returnFee,
                'cancellation_fee' => $cancellationFee,
                'customer_request_coordinates' => $customer_point,
                'rise_request_count' => $riseRequestCount
            ]);
            $save_trip = $this->trip->store(attributes: $request->all());
        }

        if ($request->bid) {
            $final = $this->trip->getBy(column: 'id', value: $save_trip->id, attributes: ['relations' => 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        } else {
            $tripDiscount = $this->trip->getBy(column: 'id', value: $save_trip->id);
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $estimatedAmount = $tripDiscount->actual_fare / (1 + ($vat_percent / 100));
            $discount = $this->getEstimatedDiscount(user: $tripDiscount->customer, zoneId: $tripDiscount->zone_id, tripType: $tripDiscount->type, vehicleCategoryId: $tripDiscount->vehicle_category_id, estimatedAmount: $estimatedAmount);
            if ($discount['discount_amount'] != 0) {
                $save_trip->discount_amount = $discount['discount_amount'];
                $save_trip->discount_id = $discount['discount_id'];
                $save_trip->save();
            }
            $final = $this->trip->getBy(column: 'id', value: $tripDiscount->id, attributes: ['relations' => 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        }

        $search_radius = (double)get_cache('search_radius') ?? 5;
        // Find drivers list based on pickup locations
        $find_drivers = $this->findNearestDriver(
            latitude: $pickup_coordinates[0] ?? $final->coordinate->pickup_coordinates->latitude,
            longitude: $pickup_coordinates[1] ?? $final->coordinate->pickup_coordinates->longitude,
            zone_id: $zone->id,
            radius: $search_radius,
            vehicleCategoryId: $request->vehicle_category_id ?? $final->vehicle_category_id,
            requestType: $request->type ?? $final->type,
            parcelWeight: $request->weight ?? null
        );
        //Send notifications to drivers
        if (!empty($find_drivers)) {
            $notify = [];
            foreach ($find_drivers as $key => $value) {
                if ($value->user?->fcm_token) {
                    $notify[$key]['user_id'] = $value->user->id;
                    $notify[$key]['trip_request_id'] = $final->id;
                }

            }
            $push = getNotification('new_' . $final->type);
            $notification = [
                'title' => translate($push['title']),
                'description' => translate($push['description']),
                'status' => $push['status'],
                'ride_request_id' => $final->id,
                'type' => $final->type,
                'action' => 'new_ride_request_notification'
            ];
            if (!empty($notify)) {

                dispatch(new SendPushNotificationJob($notification, $find_drivers))->onQueue('high');
                $this->temp_notification->store(['data' => $notify]);
            }
            foreach ($find_drivers as $key => $value) {
                try {
                    checkPusherConnection(CustomerTripRequestEvent::broadcast($value->user, $final));
                } catch (Exception $exception) {

                }
            }
        }
        //Send notifications to admins
        if (!is_null(businessConfig('server_key', NOTIFICATION_SETTINGS))) {
            sendTopicNotification(
                'admin_notification',
                translate('new_request_notification'),
                translate('new_request_has_been_placed'),
                'null',
                $final->id,
                $request->type);
        }
        //Trip API resource
        $trip = new TripRequestResource($final);

        return response()->json(responseFormatter(TRIP_REQUEST_STORE_200, $trip));
    }

    /**
     * @param $trip_request_id
     * @param Request $request
     * @return JsonResponse
     */
    public function biddingList($trip_request_id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $tripRequest = $this->trip->getBy(column: 'id', value: $trip_request_id);
        if ($tripRequest->current_status == PENDING){
            $bidding = $this->bidding->get(limit: $request['limit'], offset: $request['offset'], dynamic_page: true, attributes: [
                'trip_request_id' => $trip_request_id,
                'relations' => ['driver_last_location', 'driver', 'trip_request', 'driver.vehicle.model'],
                'withAvgRelation' => 'driverReceivedReviews',
                'withAvgColumn' => 'rating',
            ]);
            $bidding = FareBiddingResource::collection($bidding);

            return response()->json(responseFormatter(constant: DEFAULT_200, content: $bidding, limit: $request['limit'], offset: $request['offset']));
        }
        return response()->json(responseFormatter(constant: DEFAULT_200, content: []));

    }


    public function ignoreBidding(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bidding_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $bidding = $this->bidding->getBy(column: 'id', value: $request['bidding_id']);
        if (!$bidding) {

            return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
        }

        $this->bidding->update(attributes: [
            'column' => 'id',
            'is_ignored' => 1
        ], id: $request->bidding_id);
        if ($bidding->driver_id) {
            if (!is_null($bidding->driver->fcm_token)) {
                $push = getNotification('customer_bid_rejected');
                sendDeviceNotification(fcm_token: $bidding->driver->fcm_token,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'], tripId: $bidding->trip_request->ref_id)),
                    status: $push['status'],
                    ride_request_id: $bidding->trip_request_id,
                    type: $bidding->trip_request_id,
                    action: 'bid_rejected',
                    user_id: $bidding->driver->id
                );
            }
        }

        return response()->json(responseFormatter(constant: DEFAULT_200));
    }


    /**
     *
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'driver_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id'], attributes: ['relations' => 'coordinate']);
        $driver = $this->driver->getBy(column: 'id', value: $request['driver_id'], attributes: ['relations' => ['vehicle', 'driverDetails', 'lastLocations']]);

        if ($trip->current_status == ACCEPTED && $trip->driver_id == $driver->id) {
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
            $checkBid = $this->bidding->getBy(column: 'trip_request_id', value: $request['trip_request_id'], attributes: [
                'additionalColumn' => 'driver_id',
                'additionalValue' => $request['driver_id']
            ]);

            if (!$checkBid) {
                return response()->json(responseFormatter(constant: DRIVER_BID_NOT_FOUND_403), 403);
            }

        }

        $env = env('APP_MODE');
        $otp = $env != "live" ? '0000' : rand(1000, 9999);

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
            'trip_status' => ACCEPTED,
            'vehicle_category_id' => $assignedVehicleCategoryId,
        ];

        if ($request['action'] == ACCEPTED) {
            DB::beginTransaction();

            //set driver availability_status as on_trip
            $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $driver->id);
            if ($trip->type == RIDE_REQUEST) {
                $driverDetails->ride_count = 1;
            } else {
                ++$driverDetails->parcel_count;
            }
            $driverDetails->save();

            //deleting exiting rejected driver request for this trip
            $this->rejected_request->destroyData([
                'column' => 'trip_request_id',
                'value' => $trip->id,
            ]);
            if (get_cache('bid_on_fare') ?? 0) {
                $all_bidding = $this->bidding->get(limit: 200, offset: 1, attributes: [
                    'trip_request_id' => $request['trip_request_id'],
                ]);

                if (count($all_bidding) > 0) {
                    $actual_fare = $all_bidding
                        ->where('driver_id', $request['driver_id'])
                        ->firstWhere('trip_request_id', $request['trip_request_id'])
                        ->bid_fare;
                    $attributes['actual_fare'] = $actual_fare;
                    $attributes['estimated_fare'] = $actual_fare;
                }
            }


            $data = $this->temp_notification->get([
                'relations' => 'user',
                'trip_request_id' => $request['trip_request_id'],
                'whereNotInColumn' => 'user_id',
                'whereNotInValue' => [$driver->id]
            ]);

            $push = getNotification('driver_assigned');
            if (!empty($data)) {
                $notification['title'] = translate($push['title']);
                $notification['description'] = translate($push['description']);
                $notification['status'] = $push['status'];
                $notification['ride_request_id'] = $trip->id;
                $notification['type'] = $trip->type;
                $notification['action'] = 'ride_started';

                dispatch(new SendPushNotificationJob($notification, $data))->onQueue('high');
                $this->temp_notification->delete($trip->id);
            }
            $driver_arrival_time = getRoutes(
                originCoordinates: [
                    $trip->coordinate->pickup_coordinates->latitude,
                    $trip->coordinate->pickup_coordinates->longitude
                ],
                destinationCoordinates: [
                    $driver->lastLocations->latitude,
                    $driver->lastLocations->longitude
                ],
            );

            // Check if getRoutes returned an error (integer status code) instead of array
            if (!is_array($driver_arrival_time)) {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, 'Unable to calculate driver arrival time. API returned status: ' . $driver_arrival_time), 404);
            }

            if ($driver_arrival_time[1]['status'] !== "OK") {
                return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, $driver_arrival_time[1]['error_detail'] ?? 'Route not found'), 404);
            }
            if ($trip->type == 'ride_request') {
                $attributes['driver_arrival_time'] = (double)($driver_arrival_time[0]['duration']) / 60;
            }

            //Trip update
            $this->trip->update(attributes: $attributes, id: $request['trip_request_id']);
            $updateTripDiscount = $this->trip->getBy(column: 'id', value: $request['trip_request_id']);
            $updateTripDiscount->discount_id = null;
            $updateTripDiscount->discount_amount = null;
            $updateTripDiscount->save();
            DB::commit();

            $otpRequired = (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
            if ($otpRequired && $trip->type === RIDE_REQUEST && $otp) {
                $otpMessage = 'Your trip OTP is ' . $otp;
                try {
                    if ($trip?->customer?->phone) {
                        \Modules\Gateways\Traits\SmsGatewayForMessage::send($trip->customer->phone, $otpMessage);
                    }
                } catch (\Exception $exception) {

                }

                if ($trip?->customer?->fcm_token) {
                    sendDeviceNotification(
                        fcm_token: $trip->customer->fcm_token,
                        title: translate('Trip OTP'),
                        description: translate($otpMessage),
                        status: 1,
                        ride_request_id: $trip->id,
                        type: $trip->type,
                        action: 'trip_otp',
                        user_id: $trip->customer->id
                    );
                }
            }
            if (get_cache('bid_on_fare') ?? 0) {

                $acceptDriverBid = $this->bidding->getBy(column: 'trip_request_id', value: $request['trip_request_id'], attributes: [
                    'additionalColumn' => 'driver_id',
                    'additionalValue' => $request['driver_id']
                ]);
                $all_bidding = $this->bidding->get(limit: 200, offset: 1, attributes: [
                    'trip_request_id' => $request['trip_request_id'],
                    'without_ids' => [$acceptDriverBid->id],
                ]);
                if ($all_bidding->isNotEmpty()) {
                    $this->bidding->destroyData([
                        'column' => 'id',
                        'ids' => $all_bidding->pluck('id')
                    ]);
                }
            }
            $push = getNotification('bid_accepted');
            sendDeviceNotification(fcm_token: $driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'ride_' . $request->action,
                user_id: $driver->id);
        } else {
            if (get_cache('bid_on_fare') ?? 0) {
                $all_bidding = $this->bidding->get(limit: 200, offset: 1, attributes: [
                    'trip_request_id' => $request['trip_request_id'],
                ]);

                if (count($all_bidding) > 0) {
                    $this->bidding->destroyData([
                        'column' => 'id',
                        'ids' => $all_bidding->pluck('id')
                    ]);
                }

            }
        }

        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }

    public function rideStatusUpdate($trip_request_id, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->trip->getBy(column: 'id', value: $trip_request_id, attributes: ['relations' => 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->current_status == CANCELLED) {
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        if ($trip->current_status == COMPLETED) {
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }
        if ($trip->current_status == RETURNING) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
        }
        $attributes = [
            'column' => 'id',
            'value' => $trip_request_id,
            'trip_status' => $request['status'],
            'trip_cancellation_reason' => utf8Clean($request['cancel_reason'] ?? null)
        ];


        if ($request->status == 'cancelled' && ($trip->current_status == ACCEPTED || $trip->current_status == PENDING)) {
            //referral
            if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
                $trip->customer->referralCustomerDetails()->update([
                    'is_used' => 1
                ]);
                if ($trip->customer?->referralCustomerDetails?->ref_by_earning_amount && $trip->customer?->referralCustomerDetails?->ref_by_earning_amount > 0) {
                    $shareReferralUser = $trip->customer?->referralCustomerDetails?->shareRefferalCustomer;
                    $this->customerReferralEarningTransaction($shareReferralUser, $trip->customer?->referralCustomerDetails?->ref_by_earning_amount);

                    $push = getNotification('referral_reward_received');
                    sendDeviceNotification(fcm_token: $shareReferralUser?->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'], referralRewardAmount: getCurrencyFormat($trip->customer?->referralCustomerDetails?->ref_by_earning_amount))),
                        status: $push['status'],
                        ride_request_id: $shareReferralUser?->id,
                        action: 'referral_reward_received',
                        user_id: $shareReferralUser?->id
                    );
                }
            }
            $data = $this->temp_notification->get([
                'trip_request_id' => $request['trip_request_id'],
                'relations' => 'user'
            ]);
            $push = getNotification('ride_cancelled');
            if (!empty($data)) {
                if ($trip->driver_id) {
                    if (!is_null($trip->driver->fcm_token)) {
                        sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                            title: translate($push['title']),
                            description: translate(textVariableDataFormat(value: $push['description'])),
                            status: $push['status'],
                            ride_request_id: $request['trip_request_id'],
                            type: $trip->type,
                            action: 'ride_cancelled',
                            user_id: $trip->driver->id
                        );
                    }
                    try {
                        checkPusherConnection(CustomerTripCancelledEvent::broadcast($trip->driver, $trip));
                    } catch (Exception $exception) {

                    }
                    //set driver availability_status as on_trip
                    $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $trip->driver_id);
                    if ($trip->type == RIDE_REQUEST) {
                        $driverDetails->ride_count = 0;
                    } else {
                        --$driverDetails->parcel_count;
                    }
                    $driverDetails->availability_status = 'available';
                    $driverDetails->save();
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
                    foreach ($data as $tempNotification) {
                        try {
                            checkPusherConnection(CustomerTripCancelledEvent::broadcast($tempNotification->user, $trip));
                        } catch (Exception $exception) {

                        }
                    }
                }
                $this->temp_notification->delete($trip->id);
            }
        }
        if ($trip->is_paused) {

            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        if ($trip->driver_id && ($request->status == 'completed' || $request->status == 'cancelled') && $trip->current_status == ONGOING) {
            if ($request->status == 'cancelled') {
                $attributes['fee']['cancelled_by'] = 'customer';
            }
            $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude);

            //set driver availability_status as on_trip
            $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $trip->driver_id);
            if ($trip->type == RIDE_REQUEST) {
                $driverDetails->ride_count = 0;
            } else if ($request->status == 'completed') {
                --$driverDetails->parcel_count;
            }
            $driverDetails->availability_status = 'available';
            $driverDetails->save();

            if ($request->status == 'cancelled' && $trip->type == PARCEL) {
                $push = getNotification('ride_' . $request->status);
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $request['trip_request_id'],
                        type: $trip->type,
                        action: 'parcel_cancelled',
                        user_id: $trip->driver->id
                    );
                }
            } else {
                //Get status wise notification message
                $push = getNotification('ride_' . $request->status);
                if (!is_null($trip->driver->fcm_token)) {
                    sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $request['trip_request_id'],
                        type: $trip->type,
                        action: 'ride_completed',
                        user_id: $trip->driver->id
                    );
                }
                try {
                    checkPusherConnection(CustomerTripCancelledAfterOngoingEvent::broadcast($trip));
                } catch (Exception $exception) {

                }
            }

        }

        DB::beginTransaction();
        if ($request->status == 'cancelled' && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->trip->updateRelationalTable($attributes);
            $this->customerLevelUpdateChecker(auth()->user());
            $this->driverLevelUpdateChecker($trip->driver);
        } elseif ($request->status == 'completed' && $trip->driver_id && $trip->current_status == ONGOING) {
            $this->trip->updateRelationalTable($attributes);
            $this->customerLevelUpdateChecker(auth()->user());
            $this->driverLevelUpdateChecker($trip->driver);
        } else {
            $this->trip->updateRelationalTable($attributes);
        }
        DB::commit();
        if ($trip->driver_id && $request->status == 'cancelled' && $trip->current_status == ONGOING && $trip->type == PARCEL) {
            $env = env('APP_MODE');
            $otp = $env != "live" ? '0000' : rand(1000, 9999);
            $trip->otp = $otp;
            if ($trip?->parcel?->payer == SENDER) {
                $trip->paid_fare += $trip->return_fee;
                $trip->due_amount = $trip->return_fee;
                $trip->payment_status = PARTIAL_PAID;
            }
            $trip->current_status = RETURNING;
            $parcelReturnTimeFeeStatus = businessConfig('parcel_return_time_fee_status', PARCEL_SETTINGS)?->value ?? false;
            $time = (int)businessConfig('return_time_for_driver', PARCEL_SETTINGS)?->value;
            $timeType = businessConfig('return_time_type_for_driver', PARCEL_SETTINGS)?->value;
            if ($parcelReturnTimeFeeStatus) {
                if ($timeType === 'hour') {
                    $trip->return_time = Carbon::now()->addHours($time);
                } else {
                    $trip->return_time = Carbon::now()->addDays($time);
                }
            }
            $trip->save();
            $trip->tripStatus()->update([
                RETURNING => now()
            ]);
        }

        // Publish to Redis for real-time UI updates
        if ($request->status == 'cancelled') {
            try {
                $realtimePublisher = app(RealtimeEventPublisher::class);
                $realtimePublisher->publishRideCancelled($trip, 'customer');
            } catch (\Exception $e) {
                \Log::warning('Failed to publish ride cancelled event', ['error' => $e->getMessage()]);
            }
        }

        // Refresh trip from database to get updated status
        $trip = $trip->fresh(['driver', 'vehicle.model', 'vehicleCategory', 'tripStatus', 'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo', 'parcelRefund']);
        
        $resource = TripRequestResource::make($trip)->resolve();
        return response()->json(utf8Clean(responseFormatter(DEFAULT_UPDATE_200, $resource)));
    }

    /**
     * @param $trip_request_id
     * @return JsonResponse
     */
    public function rideDetails($trip_request_id): JsonResponse
    {
        $attributes = [
            'relations' => [
                'driver', 'vehicle.model', 'vehicleCategory', 'tripStatus',
                'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo', 'parcelRefund'
            ],
            'withAvgRelation' => 'driverReceivedReviews',
            'withAvgColumn' => 'rating'
        ];

        $data = $this->trip->getBy('id', $trip_request_id, $attributes);
        if (!$data) {

            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        $resource = TripRequestResource::make($data->append('distance_wise_fare'));

        return response()->json(responseFormatter(DEFAULT_200, $resource));

    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function rideList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filter' => Rule::in([TODAY, PREVIOUS_DAY, THIS_WEEK, LAST_WEEK, LAST_7_DAYS, THIS_MONTH, LAST_MONTH, THIS_YEAR, ALL_TIME, CUSTOM_DATE]),
            'status' => Rule::in([ALL, PENDING, ONGOING, COMPLETED, CANCELLED, RETURNED]),
            'start' => 'required_if:filter,==,custom_date|required_with:end',
            'end' => 'required_if:filter,==,custom_date|required_with:end',
            'limit' => 'required|numeric',
            'offset' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        if (!is_null($request->filter) && $request->filter != CUSTOM_DATE) {
            $date = getDateRange($request->filter);
        } elseif (!is_null($request->filter)) {
            $date = getDateRange([
                'start' => $request->start,
                'end' => $request->end
            ]);
        }
        $attributes = [
            'column' => 'customer_id',
            'value' => auth('api')->id(),
            'withAvgRelation' => 'driverReceivedReviews',
            'withAvgColumn' => 'rating',
        ];
        if (!empty($date)) {
            $attributes['from'] = $date['start'];
            $attributes['to'] = $date['end'];
        }
        if (!is_null($request->status) && $request->status != ALL) {
            $attributes['column_name'] = 'current_status';
            $attributes['column_value'] = [$request->status];
        }
        $relations = ['driver', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee', 'parcel.parcelCategory', 'parcelRefund'];
        $data = $this->trip->get(limit: $request['limit'], offset: $request['offset'], dynamic_page: true, attributes: $attributes, relations: $relations);
        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }


    /**
     * Calculate final trip cost.
     *
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function finalFareCalculation(Request $request): JsonResponse
    {
        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']
            , attributes: ['relations' =>
                ['vehicleCategory.tripFares', 'coupon', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
            ]
        );

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }

        if ($trip->current_status != 'completed' && $trip->current_status != 'cancelled' && $trip->type == 'ride_request') {

            return response()->json(responseFormatter(constant: TRIP_STATUS_NOT_COMPLETED_200));
        }

        if ($trip->customer_id != auth('api')->id() && $trip->driver_id != auth('api')->id()) {

            return response()->json(responseFormatter(constant: DEFAULT_404), 403);
        }
        if ($trip->discount_amount != null && $trip->discount_amount > 0 && $trip->actual_fare == $trip->discount_amount) {
            $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']
                , attributes: ['relations' => [
                    'vehicleCategory.tripFares', 'customer', 'driver', 'coupon', 'discount', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
                ]
            );
            $trip = new TripRequestResource($trip->append('distance_wise_fare'));
            return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
        }
        if ($trip->paid_fare != 0) {
            $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']
                , attributes: ['relations' => [
                    'vehicleCategory.tripFares', 'customer', 'driver', 'coupon', 'discount', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
                ]
            );
            $trip = new TripRequestResource($trip->append('distance_wise_fare'));
            return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
        }


        if ($trip->type == 'ride_request') {
            $fare = $trip->vehicleCategory->tripFares->where('zone_id', $trip->zone_id)->first();
            if (!$fare) {
                return response()->json(responseFormatter(ZONE_404), 403);
            }
        } else {
            $fare = null;
        }

        DB::beginTransaction();
        //final fare calculation trait
        // $calculated_data = $this->calculateFinalFare($trip, $fare);

        // $attributes = [
        //     'extra_fare_amount' => round($calculated_data['extra_fare_amount'], 2),
        //     'paid_fare' => round($calculated_data['final_fare'], 2),
        //     'actual_fare' => round($calculated_data['actual_fare'], 2),
        //     'column' => 'id',
        //     'actual_distance' => $calculated_data['actual_distance'],
        // ];
        // $this->trip->update(attributes: $attributes, id: $request['trip_request_id']);

        // $bid_on_fare = FareBidding::where('trip_request_id', $request['trip_request_id'])->where('is_ignored', 0)->first();
        // if (($bid_on_fare || $trip->rise_request_count > 0) && $trip->type == 'ride_request') {
        //     $this->finalFareCouponAutoApply($trip->customer, $request['trip_request_id']);
        // } else {
        //     $this->finalFareDiscountAutoApply($trip->customer, $request['trip_request_id']);
        //     $this->finalFareCouponAutoApply($trip->customer, $request['trip_request_id']);
        // }
        
         // Retrieve threshold fields from defaultFare relationship
        $default_fare = $trip->zone->defaultFare;
        $minimum_distance_threshold = $default_fare->minimum_distance_threshold ?? 0;
        $fixed_price_below_threshold = $default_fare->fixed_price_below_threshold ?? 0;
        $points = (int)getSession('currency_decimal_point') ?? 0;

        // Check if actual distance is below threshold
        $calculated_data = $this->calculateFinalFare($trip, $fare);
        $actual_distance_km = $calculated_data['actual_distance']; // Convert meters to km

        if ($actual_distance_km > 0 && $actual_distance_km <= $minimum_distance_threshold) {
            // Apply fixed price
            $final_fare = round($fixed_price_below_threshold, $points);
            $attributes = [
                'extra_fare_amount' => 0,
                'paid_fare' => $final_fare,
                'actual_fare' => $final_fare,
                'column' => 'id',
                'actual_distance' => $calculated_data['actual_distance'],
            ];
        } else {
            // Use calculated fare
            $attributes = [
                'extra_fare_amount' => round($calculated_data['extra_fare_amount'], 2),
                'paid_fare' => round($calculated_data['final_fare'], 2),
                'actual_fare' => round($calculated_data['actual_fare'], 2),
                'column' => 'id',
                'actual_distance' => $calculated_data['actual_distance'],
            ];
        }

        $this->trip->update(attributes: $attributes, id: $request['trip_request_id']);

        // Apply discounts or coupons only if not using fixed price
        if ($actual_distance_km <= 0 || $actual_distance_km > $minimum_distance_threshold) {
            $bid_on_fare = FareBidding::where('trip_request_id', $request['trip_request_id'])->where('is_ignored', 0)->first();
            if (($bid_on_fare || $trip->rise_request_count > 0) && $trip->type == 'ride_request') {
                $this->finalFareCouponAutoApply($trip->customer, $request['trip_request_id']);
            } else {
                $this->finalFareDiscountAutoApply($trip->customer, $request['trip_request_id']);
                $this->finalFareCouponAutoApply($trip->customer, $request['trip_request_id']);
            }
        }
        DB::commit();
        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']
            , attributes: ['relations' =>
                ['vehicleCategory.tripFares', 'coupon', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
            ]
        );
        $amount = $trip->paid_fare + $trip->return_fee;
        if ($trip->type == PARCEL && $trip->current_status == RETURNING && $trip?->parcel?->payer == "receiver") {
            $trip->paid_fare += $trip->return_fee;
            $trip->due_amount = $amount;
            $trip->save();
        }

        //referral user reward
        if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
            $trip->customer->referralCustomerDetails()->update([
                'is_used' => 1
            ]);
            if ($trip->customer?->referralCustomerDetails?->ref_by_earning_amount && $trip->customer?->referralCustomerDetails?->ref_by_earning_amount > 0) {
                $shareReferralUser = $trip->customer?->referralCustomerDetails?->shareRefferalCustomer;
                $this->customerReferralEarningTransaction($shareReferralUser, $trip->customer?->referralCustomerDetails?->ref_by_earning_amount);

                $push = getNotification('referral_reward_received');
                sendDeviceNotification(fcm_token: $shareReferralUser?->fcm_token,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'], referralRewardAmount: getCurrencyFormat($trip->customer?->referralCustomerDetails?->ref_by_earning_amount))),
                    status: $push['status'],
                    ride_request_id: $shareReferralUser?->id,
                    action: 'referral_reward_received',
                    user_id: $shareReferralUser?->id
                );
            }

        }
        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']
            , attributes: ['relations' => [
                'vehicleCategory.tripFares', 'customer', 'driver', 'coupon', 'discount', 'time', 'coordinate', 'fee', 'tripStatus', 'parcelRefund']
            ]
        );
        $trip = new TripRequestResource($trip->append('distance_wise_fare'));
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    /**
     * Summary of driversNearMe
     * @param Request $request
     * @return JsonResponse
     */
    public function driversNearMe(Request $request): JsonResponse
    {
        if (is_null($request->header('zoneId'))) {
            return response()->json(responseFormatter(ZONE_404));
        }

        // Find drivers list based on customer last locations
        $driver_list = $this->findNearestDriver(
            latitude: $request->latitude,
            longitude: $request->longitude,
            zone_id: $request->header('zoneId'),
            radius: (double)(get_cache('search_radius') ?? 50)
        );
        $lastLocationDriver = LastLocationResource::collection($driver_list);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $lastLocationDriver));
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function arrivalTime(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $time = TripRequestTime::query()->where('trip_request_id', $request->trip_request_id)->first();
        if (!$time) {

            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        $time->customer_arrives_at = now();
        $time->save();

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function coordinateArrival(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'is_reached' => 'required|in:coordinate_1,coordinate_2,destination',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = TripRequestCoordinate::query()->where('trip_request_id', $request->trip_request_id)->first();
        if ($request->is_reached == 'coordinate_1') {
            $trip->is_reached_1 = true;
        }
        if ($request->is_reached == 'coordinate_2') {
            $trip->is_reached_2 = true;
        }
        if ($request->is_reached == 'destination') {
            $trip->is_reached_destination = true;
        }
        $trip->save();

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));

    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function applyCoupon(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'coupon_code' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->trip->getBy(column: 'id', value: $request->trip_request_id, attributes: ['relations' => 'driver', 'fee', 'parcelRefund']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->coupon_id) {

            return response()->json(responseFormatter(constant: COUPON_APPLIED_403), 403);
        }
        $user = auth('api')->user();
        $date = date('Y-m-d');
        $coupon = $this->coupon->getAppliedCoupon([
            'coupon_code' => $request->coupon_code,
            'fare' => $trip->paid_fare,
            'date' => $date
        ]);

        if (!$coupon) {

            return response()->json(responseFormatter(constant: COUPON_404, content: ['discount' => 0]), 403);
        }
        $response = $this->getCouponDiscount($user, $trip, $coupon);

        if ($response['discount'] != 0) {
            $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount'];
            $vat = ($vat_percent * $final_fare_without_tax) / 100;
            $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
            $updateTrip = TripRequest::find($request->trip_request_id);
            $updateTrip->coupon_id = $coupon->id;
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
                description: translate(textVariableDataFormat(value: $push['description'])) . ' ' . $response['discount'],
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'coupon_applied',
                user_id: $trip->driver->id
            );
            try {
                checkPusherConnection(CustomerCouponAppliedEvent::broadcast($trip));
            } catch (Exception $exception) {

            }
            $trip = new TripRequestResource($trip->append('distance_wise_fare'));
            return response()->json(responseFormatter(constant: $response['message'], content: $trip));
        }

        return response()->json(responseFormatter(constant: $response['message'], content: $trip), 403);
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function pendingParcelList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $attributes = [
            'limit' => $request->limit,
            'offset' => $request->offset,
            'column' => 'customer_id',
            'value' => auth()->id(),
            'whereNotNull' => 'customer_id',
        ];

        $trips = $this->trip->pendingParcelList($attributes, 'customer');
        $trips = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function unpaidParcelRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trips = $this->trip->unpaidParcelRequest([
            'limit' => $request->limit,
            'offset' => $request->offset,
            'column' => 'customer_id',
            'value' => auth()->id(),
            'whereHas' => true,
            'relations' => ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
                'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo', 'parcelRefund']
        ]);
        $trips = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function storeScreenshot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'file' => 'required|mimes:jpg,png,webp'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $this->trip->update([
            'column' => 'id',
            'map_screenshot' => $request->file,
        ], $request->trip_request_id);

        return response()->json(responseFormatter(DEFAULT_200));
    }


    #receivedReturingParcel
    public function receivedReturningParcel($trip_request_id): JsonResponse
    {
        $trip = $this->trip->getBy(column: 'id', value: $trip_request_id, attributes: ['relations' => 'driver', 'driver.driverDetails', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->current_status == RETURNED) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNED_403), 403);
        }
        DB::beginTransaction();
        if ($trip?->fee?->cancelled_by == CUSTOMER && $trip?->parcel?->payer == 'sender' && $trip->due_amount > 0) {
            $this->cashReturnFeeTransaction($trip);
        }
        if ($trip?->fee?->cancelled_by == CUSTOMER && $trip?->parcel?->payer == 'receiver' && $trip->due_amount > 0) {
            $this->cashTransaction($trip, true);
            $this->cashReturnFeeTransaction($trip);
        }
        if ($trip?->fee?->cancelled_by == CUSTOMER) {
            $trip->payment_status = PAID;
        }
        $trip->due_amount = 0;
        $trip->current_status = RETURNED;
        $trip->save();
        $trip->tripStatus()->update([
            RETURNED => now()
        ]);
        DB::commit();
        $this->returnTimeExceedFeeTransaction($trip);
        $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $trip->driver_id);
        --$driverDetails->parcel_count;
        $driverDetails->save();
        $push = getNotification('parcel_returned');
        sendDeviceNotification(fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip_request_id,
            type: $trip->type,
            action: 'parcel_returned',
            user_id: $trip->driver->id
        );
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }


    /**
     * Summary of findNearestDriver
     * @param mixed $latitude
     * @param mixed $longitude
     * @param mixed $zone_id
     * @param mixed $radius
     * @param null $vehicleCategoryId
     * @return mixed
     */
    private function findNearestDriver($latitude, $longitude, $zone_id, $radius = 50, $vehicleCategoryId = null, $requestType = null, $parcelWeight = null): mixed
    {
        /*
         * replace 6371000 with 6371 for kilometer and 3956 for miles
         */
        $attributes = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
            'zone_id' => $zone_id,
        ];
        if ($vehicleCategoryId) {
            $attributes['vehicle_category_id'] = $vehicleCategoryId;
        }
        if ($parcelWeight) {
            $attributes['parcel_weight_capacity'] = $parcelWeight;
        }
        if ($requestType) {
            $attributes['service'] = $requestType;
        }
        $maxParcelRequestAcceptLimit = businessConfig(key: 'maximum_parcel_request_accept_limit', settingsType: DRIVER_SETTINGS);
        $maxParcelRequestAcceptLimitStatus = (bool)($maxParcelRequestAcceptLimit?->value['status'] ?? false);
        $maxParcelRequestAcceptLimitCount = (int)($maxParcelRequestAcceptLimit?->value['limit'] ?? 0);
        if ($maxParcelRequestAcceptLimitStatus) {
            $attributes['parcel_accept_limit'] = $maxParcelRequestAcceptLimitCount;
        }
        if ($requestType) {
            $driverList = $this->lastLocation->getNearestDrivers($attributes);
            return $driverList->filter(function ($driver) use ($requestType, $maxParcelRequestAcceptLimitStatus, $maxParcelRequestAcceptLimitCount) {
                $rideCount = $driver->driverDetails->ride_count ?? 0;
                $parcelCount = $driver->driverDetails->parcel_count ?? 0;

                // Handle ride request logic
                if (($requestType == RIDE_REQUEST) && $rideCount >= 1) {
                    return false; // Exclude drivers with 1 or more rides
                }

                // Handle parcel request logic
                if (($requestType == PARCEL) && $maxParcelRequestAcceptLimitStatus) {
                    // Enforce parcel count limit if the restriction is enabled
                    return $parcelCount < $maxParcelRequestAcceptLimitCount;
                }

                // Allow drivers if request type is not strict
                return true;
            })->map(function ($driver) {
                // Format the driver data
                return $driver;
            });
        }
        return $this->lastLocation->getNearestDrivers($attributes);

    }

    /**
     * @return Model|mixed|null
     */
    private function getIncompleteRide(): mixed
    {
        $trip = $this->trip->getBy(column: 'customer_id', value: auth()->id(), attributes: [
            'withAvgRelation' => 'driverReceivedReviews',
            'withAvgColumn' => 'rating',
            'relations' => ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
                'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo', 'customerReceivedReviews', 'driverReceivedReviews']
        ]);

        if (!$trip || $trip->type != 'ride_request' ||
            $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)) {

            return null;
        }
        return $trip;
    }

    private function getResumeRide(): mixed
    {
        $attributes = [
            'type' => 'ride_request',
            'column' => 'customer_id',
            'value' => auth()->id(),
        ];
        $tripRequest = $this->trip->getIncompleteRide($attributes);
        if (!$tripRequest) {
            return null;
        }
        $trip = $this->trip->getBy(column: 'id', value: $tripRequest->id, attributes: [
            'withAvgRelation' => 'driverReceivedReviews',
            'withAvgColumn' => 'rating',
            'relations' => ['customer', 'driver', 'vehicleCategory', 'vehicleCategory.tripFares', 'vehicle', 'coupon', 'time',
                'coordinate', 'fee', 'tripStatus', 'zone', 'vehicle.model', 'fare_biddings', 'parcel', 'parcelUserInfo', 'customerReceivedReviews', 'driverReceivedReviews']
        ]);
        return $trip;
    }

    private function removeCouponData($trip)
    {
        $this->coupon->removeCouponUsage(attributes: ['id' => $trip->coupon_id, 'amount' => $trip->coupon_amount]);

        $trip = $this->trip->getBy(column: 'id', value: $trip->id);
        $vat_percent = (double)get_cache('vat_percent') ?? 1;
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

    private function finalFareDiscountAutoApply($user, $tripId)
    {
        $trip = $this->trip->getBy(column: 'id', value: $tripId);

        $updateTripDiscount = $this->trip->getBy(column: 'id', value: $trip->id);
        $updateTripDiscount->discount_id = null;
        $updateTripDiscount->discount_amount = null;
        $updateTripDiscount->save();

        $finalData = $this->trip->getBy(column: 'id', value: $updateTripDiscount->id);
        $response = $this->getFinalDiscount(user: $user, trip: $finalData);
        if ($response['discount_amount'] != 0) {
            $finalData = $this->trip->getBy(column: 'id', value: $updateTripDiscount->id);
            $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $final_fare_without_tax = ($finalData->paid_fare - $finalData->fee->vat_tax - $finalData->fee->tips) - $response['discount_amount'];
            $vat = ($vat_percent * $final_fare_without_tax) / 100;
            $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
            $updateTrip = $this->trip->getBy(column: 'id', value: $finalData->id);
            $updateTrip->discount_id = $response['discount_id'];
            $updateTrip->discount_amount = $response['discount_amount'];
            $updateTrip->paid_fare = $final_fare_without_tax + $vat + $updateTrip->fee->tips;
            $updateTrip->fee()->update([
                'vat_tax' => $vat,
                'admin_commission' => $admin_commission
            ]);
            $updateTrip->save();
            if ($response['discount_id'] != null) {
                $this->updateDiscountCount($response['discount_id'], $response['discount_amount']);
            }
        }
    }

    private function finalFareCouponAutoApply($user, $tripId)
    {
        $trip = $this->trip->getBy(column: 'id', value: $tripId);
        $response = $this->getFinalCouponDiscount(user: $user, trip: $trip);
        if ($response['discount'] != 0) {
            $trip = $this->trip->getBy(column: 'id', value: $tripId);
            $admin_trip_commission = (double)get_cache('trip_commission') ?? 0;
            $vat_percent = (double)get_cache('vat_percent') ?? 1;
            $final_fare_without_tax = ($trip->paid_fare - $trip->fee->vat_tax - $trip->fee->tips) - $response['discount'];
            $vat = ($vat_percent * $final_fare_without_tax) / 100;
            $admin_commission = (($final_fare_without_tax * $admin_trip_commission) / 100) + $vat;
            $updateTrip = $this->trip->getBy(column: 'id', value: $trip->id);
            $updateTrip->coupon_id = $response['coupon_id'];
            $updateTrip->coupon_amount = $response['discount'];
            $updateTrip->paid_fare = $final_fare_without_tax + $vat + $trip->fee->tips;
            $updateTrip->fee()->update([
                'vat_tax' => $vat,
                'admin_commission' => $admin_commission
            ]);
            $updateTrip->save();
            $this->updateCouponCount($response['coupon'], $response['discount']);

        }
    }

}
