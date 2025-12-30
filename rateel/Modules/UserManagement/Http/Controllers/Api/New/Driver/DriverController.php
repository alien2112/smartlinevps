<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use Carbon\Carbon;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Service\Interface\TripRequestServiceInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Service\Interface\DriverDetailServiceInterface;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;
use Modules\UserManagement\Service\Interface\DriverTimeLogServiceInterface;
use Modules\UserManagement\Service\Interface\TimeLogServiceInterface;
use Modules\UserManagement\Transformers\DriverResource;
use Modules\UserManagement\Transformers\DriverTimeLogResource;

class DriverController extends Controller
{
    protected $driverService;
    protected $driverDetailService;
    protected $driverTimeLogService;
    protected $tripRequestService;

    public function __construct(DriverServiceInterface        $driverService, DriverDetailServiceInterface $driverDetailService,
                                DriverTimeLogServiceInterface $driverTimeLogService, TripRequestServiceInterface $tripRequestService)
    {
        $this->driverService = $driverService;
        $this->driverDetailService = $driverDetailService;
        $this->driverTimeLogService = $driverTimeLogService;
        $this->tripRequestService = $tripRequestService;
    }

    public function profileInfo(Request $request): JsonResponse
    {
        if ($request->user()->user_type == DRIVER) {

            $relations = [
                'level', 'vehicle', 'vehicle.brand', 'vehicle.model', 'vehicle.category', 'driverDetails', 'userAccount', 'latestTrack','receivedReviews'];
            $withAvgRelations = [
                ['receivedReviews', 'rating']
            ];
            $withCountQuery = [
                'receivedReviews' => [],
            ];

            $driver = $this->driverService->findOneBy(criteria: ['id' => auth()->user()->id], withAvgRelations: $withAvgRelations, relations: $relations, withCountQuery: $withCountQuery);
            $driver = DriverResource::make($driver);

            return response()->json(responseFormatter(DEFAULT_200, $driver), 200);
        }
        return response()->json(responseFormatter(DEFAULT_401), 401);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'unique:users,email,' . $request->user()->id,
            'service' => 'required',
            'profile_image' => 'image|mimes:jpeg,jpg,png,gif,webp|max:10000',
            'identity_images' => 'sometimes|array',
            'identity_images.*' => 'image|mimes:jpeg,jpg,png,gif,webp|max:10000'
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $this->driverService->update(id: $request->user()->id, data: $request->all());

        return response()->json(responseFormatter(DEFAULT_UPDATE_200), 200);
    }

    /**
     * @return JsonResponse
     */
    public function onlineStatus(): JsonResponse
    {
        $driver = auth()->user();
        $details = $this->driverDetailService->findOneBy(criteria: ['user_id' => $driver->id]);
        $attributes = [
            'column' => 'user_id',
            'is_online' => $details['is_online'] == 1 ? 0 : 1,
            'availability_status' => $details['is_online'] == 1 ? 'unavailable' : 'available',
        ];
        $this->driverService->update(data: $attributes, id: $driver->id);
        // Time log set into driver details
//        $this->details->setTimeLog(
//            driver_id:$driver->id,
//            date:date('Y-m-d'),
//            online:($details->is_online == 1 ? now() : null),
//            offline:($details->is_online == 1 ? null : now()),
//            activeLog:true
//        );

        return response()->json(responseFormatter(DEFAULT_STATUS_UPDATE_200));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function myActivity(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required_with:from|date',
            'from' => 'required_with:to|date',
            'limit' => 'required|numeric',
            'offset' => 'required|numeric'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $user = auth()->user();
        $attributes = [
            'driver_id' => $user->id,
        ];

        $whereBetweenCriteria = [];
        if ($request['to']) {
            $from = Carbon::parse($request['from'])->startOfDay();
            $to = Carbon::parse($request['to'])->endOfDay();
            $whereBetweenCriteria = [
                'created_at' => [$from, $to],
            ];
        }

        $data = $this->driverTimeLogService->getBy(criteria: $attributes, whereBetweenCriteria: $whereBetweenCriteria, limit: $request['limit'], offset: $request['offset']);
        $activity = DriverTimeLogResource::collection($data);
        return response()->json(responseFormatter(DEFAULT_200, $activity, $request['limit'], $request['offset']), 200);

    }

    public function changeLanguage(Request $request): JsonResponse
    {
        if (auth('api')->user()) {
            $this->driverService->changeLanguage(id: auth('api')->user()->id, data: [
                'current_language_key' => $request->header('X-localization') ?? 'en'
            ]);
            return response()->json(responseFormatter(DEFAULT_200), 200);
        }
        return response()->json(responseFormatter(DEFAULT_404), 200);
    }

    public function incomeStatement(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $criteria = [
            ['driver_id', '!=', null],
            'driver_id' => auth()->user()->id,
            'payment_status' => PAID,
        ];
        $incomeStatements = $this->tripRequestService->getBy(criteria: $criteria, limit: $request->limit, offset: $request->offset, orderBy: ['updated_at' => 'desc']);
        $incomeStatements = TripRequestResource::collection($incomeStatements);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $incomeStatements, limit: $request->limit, offset: $request->offset));
    }

    public function referralDetails(Request $request): JsonResponse
    {
        if ($request->user()->user_type == DRIVER) {
            $useCodeEarning = referralEarningSetting('use_code_earning', DRIVER)?->value;
            $data = [
                'referral_code' => auth()->user()->ref_code,
                'share_code_earning' => (double)referralEarningSetting('share_code_earning', DRIVER)?->value,
                'use_code_earning' => (double)referralEarningSetting('use_code_earning', DRIVER)?->value,
            ];
            return response()->json(responseFormatter(DEFAULT_200, $data), 200);

        }
        return response()->json(responseFormatter(DEFAULT_401), 401);
    }

    // ============================================
    // VEHICLE SELECTION & TRAVEL APPROVAL SYSTEM
    // Enterprise-grade design: Travel is permission-based
    // ============================================

    /**
     * Select vehicle category with optional travel request
     * POST /api/driver/auth/select-vehicle
     * 
     * Flow:
     * - Budget/Pro/VIP: Save immediately
     * - Travel: Must be VIP + submit approval request
     */
    public function selectVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:vehicle_categories,id',
            'request_travel' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $driver = auth('api')->user();
        if (!$driver || $driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_401), 401);
        }

        // Get requested category
        $category = \Modules\VehicleManagement\Entities\VehicleCategory::find($request->category_id);
        if (!$category) {
            return response()->json(responseFormatter([
                'response_code' => 'category_not_found_404',
                'message' => translate('Vehicle category not found'),
            ]), 404);
        }

        // Update vehicle category
        if ($driver->vehicle) {
            $driver->vehicle->update(['category_id' => $request->category_id]);
        }

        $driverDetails = $driver->driverDetails;
        $requestTravel = $request->boolean('request_travel');

        // Handle travel request
        if ($requestTravel) {
            // Travel requires VIP category
            if ($category->category_level < \Modules\VehicleManagement\Entities\VehicleCategory::LEVEL_VIP) {
                return response()->json(responseFormatter([
                    'response_code' => 'vip_required_403',
                    'message' => translate('Travel mode requires VIP category. Please upgrade your vehicle.'),
                ]), 403);
            }

            // Check if already approved
            if ($driverDetails && $driverDetails->isTravelApproved()) {
                return response()->json(responseFormatter([
                    'response_code' => 'already_approved_200',
                    'message' => translate('You are already approved for travel bookings'),
                    'data' => $driverDetails->getTravelStatusInfo(),
                ]), 200);
            }

            // Check if already pending
            if ($driverDetails && $driverDetails->hasPendingTravelRequest()) {
                return response()->json(responseFormatter([
                    'response_code' => 'already_requested_200',
                    'message' => translate('Your travel request is pending admin approval'),
                    'data' => $driverDetails->getTravelStatusInfo(),
                ]), 200);
            }

            // Submit travel request
            if ($driverDetails) {
                $driverDetails->requestTravelPrivilege();
                
                // Create admin notification
                \Modules\AdminModule\Entities\AdminNotification::create([
                    'model' => 'driver_travel_request',
                    'model_id' => $driver->id,
                    'message' => 'new_travel_request',
                ]);

                return response()->json(responseFormatter([
                    'response_code' => 'travel_requested_201',
                    'message' => translate('Travel request submitted successfully. Pending admin approval.'),
                    'data' => [
                        'category_id' => $request->category_id,
                        'category_name' => $category->name,
                        ...$driverDetails->fresh()->getTravelStatusInfo(),
                    ],
                ]), 201);
            }
        } else {
            // Not requesting travel - reset status if needed
            if ($driverDetails && $driverDetails->travel_status === 'requested') {
                // Keep as requested, don't auto-cancel
            }
        }

        return response()->json(responseFormatter([
            'response_code' => 'vehicle_updated_200',
            'message' => translate('Vehicle category updated successfully'),
            'data' => [
                'category_id' => $request->category_id,
                'category_name' => $category->name,
                'category_level' => $category->category_level,
                ...($driverDetails ? $driverDetails->getTravelStatusInfo() : ['travel_status' => 'none', 'travel_enabled' => false]),
            ],
        ]), 200);
    }

    /**
     * Get current travel status
     * GET /api/driver/auth/travel-status
     */
    public function travelStatus(): JsonResponse
    {
        $driver = auth('api')->user();
        if (!$driver || $driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_401), 401);
        }

        $driverDetails = $driver->driverDetails;
        $vehicle = $driver->vehicle;

        $data = [
            'vehicle_category_id' => $vehicle?->category_id,
            'vehicle_category_name' => $vehicle?->category?->name,
            'vehicle_category_level' => $vehicle?->category?->category_level,
            'is_vip' => ($vehicle?->category?->category_level ?? 0) >= \Modules\VehicleManagement\Entities\VehicleCategory::LEVEL_VIP,
            ...($driverDetails ? $driverDetails->getTravelStatusInfo() : [
                'travel_status' => 'none',
                'travel_enabled' => false,
                'can_request_travel' => true,
            ]),
        ];

        return response()->json(responseFormatter(DEFAULT_200, $data), 200);
    }

    /**
     * Request travel privilege (standalone)
     * POST /api/driver/auth/request-travel
     */
    public function requestTravel(): JsonResponse
    {
        $driver = auth('api')->user();
        if (!$driver || $driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_401), 401);
        }

        $vehicle = $driver->vehicle;
        if (!$vehicle || !$vehicle->category) {
            return response()->json(responseFormatter([
                'response_code' => 'no_vehicle_403',
                'message' => translate('Please add your vehicle first'),
            ]), 403);
        }

        // Verify VIP category
        if ($vehicle->category->category_level < \Modules\VehicleManagement\Entities\VehicleCategory::LEVEL_VIP) {
            return response()->json(responseFormatter([
                'response_code' => 'vip_required_403',
                'message' => translate('Travel mode requires VIP category. Your current category: ') . $vehicle->category->name,
            ]), 403);
        }

        $driverDetails = $driver->driverDetails;
        if (!$driverDetails) {
            return response()->json(responseFormatter([
                'response_code' => 'profile_incomplete_403',
                'message' => translate('Please complete your driver profile first'),
            ]), 403);
        }

        // Check current status
        if ($driverDetails->isTravelApproved()) {
            return response()->json(responseFormatter([
                'response_code' => 'already_approved_200',
                'message' => translate('You are already approved for travel bookings'),
                'data' => $driverDetails->getTravelStatusInfo(),
            ]), 200);
        }

        if ($driverDetails->hasPendingTravelRequest()) {
            return response()->json(responseFormatter([
                'response_code' => 'already_requested_200',
                'message' => translate('Your travel request is already pending admin approval'),
                'data' => $driverDetails->getTravelStatusInfo(),
            ]), 200);
        }

        // Submit request
        $driverDetails->requestTravelPrivilege();

        // Create admin notification
        \Modules\AdminModule\Entities\AdminNotification::create([
            'model' => 'driver_travel_request',
            'model_id' => $driver->id,
            'message' => 'new_travel_request',
        ]);

        return response()->json(responseFormatter([
            'response_code' => 'travel_requested_201',
            'message' => translate('Travel request submitted successfully. You will be notified when approved.'),
            'data' => $driverDetails->fresh()->getTravelStatusInfo(),
        ]), 201);
    }

    /**
     * Cancel travel request (if pending)
     * POST /api/driver/auth/cancel-travel-request
     */
    public function cancelTravelRequest(): JsonResponse
    {
        $driver = auth('api')->user();
        if (!$driver || $driver->user_type !== DRIVER) {
            return response()->json(responseFormatter(DEFAULT_401), 401);
        }

        $driverDetails = $driver->driverDetails;
        if (!$driverDetails) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        if (!$driverDetails->hasPendingTravelRequest()) {
            return response()->json(responseFormatter([
                'response_code' => 'no_pending_request_404',
                'message' => translate('No pending travel request found'),
            ]), 404);
        }

        // Reset to none
        $driverDetails->update([
            'travel_status' => 'none',
            'travel_requested_at' => null,
        ]);

        return response()->json(responseFormatter([
            'response_code' => 'request_cancelled_200',
            'message' => translate('Travel request cancelled'),
            'data' => $driverDetails->fresh()->getTravelStatusInfo(),
        ]), 200);
    }
}
