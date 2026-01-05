<?php

namespace Modules\TripManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\Payment;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Lib\CommonTrait;
use Modules\TripManagement\Lib\CouponCalculationTrait;
use Modules\TripManagement\Service\Interface\TravelRideServiceInterface;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\VehicleManagement\Entities\VehicleCategory;

class TripRequestController extends Controller
{
    use CommonTrait, TransactionTrait, Payment, CouponCalculationTrait, LevelHistoryManagerTrait;

    protected $tripRequestService;
    protected TravelRideServiceInterface $travelRideService;

    public function __construct(
        TripRequestServiceInterface $tripRequestService,
        TravelRideServiceInterface $travelRideService
    ) {
        $this->tripRequestService = $tripRequestService;
        $this->travelRideService = $travelRideService;
    }

    public function currentRideStatus()
    {

        $relations = ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'time', 'fee', 'parcelRefund'];
        $criteria = ['type' => 'ride_request', 'driver_id' => auth('api')->id()];
        $orderBy = ['created_at' => 'desc'];
        $withAvgRelations = [['customerReceivedReviews', 'rating']];
        $trip = $this->tripRequestService->findOneBy(criteria: $criteria, withAvgRelations: $withAvgRelations, relations: $relations, orderBy: $orderBy);

        if (!$trip || $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);
        }
        $trip = TripRequestResource::make($trip);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    /**
     * Get pending travel requests for VIP drivers
     */
    public function pendingTravelList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $user = auth('api')->user();

        // Check if driver has VIP vehicle
        $vehicle = $user->vehicle;
        if (!$vehicle) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404), 404);
        }

        // Get driver's category level
        $categoryLevel = $vehicle->category?->category_level ?? VehicleCategory::LEVEL_BUDGET;

        // Only VIP drivers can see travel requests
        if ($categoryLevel < VehicleCategory::LEVEL_VIP) {
            return response()->json(responseFormatter(constant: DEFAULT_403, errors: [['code' => 'travel', 'message' => 'Only VIP drivers can view travel requests']]), 403);
        }

        // Get pending travel requests
        $trips = $this->travelRideService->getPendingTravelRequests();

        // Paginate manually
        $offset = ($request->offset - 1) * $request->limit;
        $paginatedTrips = $trips->slice($offset, $request->limit);

        $resource = TripRequestResource::collection($paginatedTrips);

        return response()->json(responseFormatter(
            constant: DEFAULT_200,
            content: $resource,
            limit: $request->limit,
            offset: $request->offset
        ));
    }

    /**
     * Accept a travel request (VIP drivers only)
     */
    public function acceptTravelRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $user = auth('api')->user();

        // Check if driver has VIP vehicle
        $vehicle = $user->vehicle;
        if (!$vehicle) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404), 404);
        }

        $categoryLevel = $vehicle->category?->category_level ?? VehicleCategory::LEVEL_BUDGET;

        // Only VIP drivers can accept travel requests
        if ($categoryLevel < VehicleCategory::LEVEL_VIP) {
            return response()->json(responseFormatter(constant: DEFAULT_403, errors: [['code' => 'travel', 'message' => 'Only VIP drivers can accept travel requests']]), 403);
        }

        // Check driver availability
        if ($user->driverDetails->availability_status !== 'available') {
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        try {
            $success = $this->travelRideService->assignDriverToTravel(
                $request->trip_request_id,
                $user->id
            );

            if ($success) {
                $trip = $this->tripRequestService->findOne(
                    id: $request->trip_request_id,
                    relations: ['customer', 'vehicleCategory', 'coordinate', 'fee', 'time', 'tripStatus']
                );

                return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
            }

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => 'Failed to accept travel request']]), 400);
        } catch (\Exception $e) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => $e->getMessage()]]), 400);
        }
    }

    /**
     * Get travel request details (VIP drivers only)
     */
    public function travelDetails(string $tripId): JsonResponse
    {
        $user = auth('api')->user();

        $trip = $this->tripRequestService->findOne(
            id: $tripId,
            relations: ['customer', 'vehicleCategory', 'coordinate', 'fee', 'time', 'tripStatus']
        );

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 404);
        }

        // Verify it's a travel request
        if (!$trip->isTravel()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: [['code' => 'travel', 'message' => 'Not a travel request']]), 400);
        }

        // Check authorization - either assigned driver or VIP driver viewing pending
        if ($trip->driver_id && $trip->driver_id !== $user->id) {
            return response()->json(responseFormatter(constant: DEFAULT_403), 403);
        }

        // If not assigned, verify VIP status
        if (!$trip->driver_id) {
            $categoryLevel = $user->vehicle?->category?->category_level ?? VehicleCategory::LEVEL_BUDGET;
            if ($categoryLevel < VehicleCategory::LEVEL_VIP) {
                return response()->json(responseFormatter(constant: DEFAULT_403), 403);
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, TripRequestResource::make($trip)));
    }
}
