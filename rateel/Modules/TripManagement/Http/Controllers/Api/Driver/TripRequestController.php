<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use App\Events\AnotherDriverTripAcceptedEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Events\DriverTripAcceptedEvent;
use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use App\Events\DriverTripStartedEvent;
use App\Jobs\SendPushNotificationJob;
use App\Jobs\ProcessTripAcceptNotificationsJob;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Validation\Rule;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\Gateways\Entities\Setting;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use Modules\ReviewModule\Interfaces\ReviewInterface;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripRequestCoordinate;
use Modules\TripManagement\Entities\TripRequestTime;
use Modules\TripManagement\Interfaces\FareBiddingInterface;
use Modules\TripManagement\Interfaces\FareBiddingLogInterface;
use Modules\TripManagement\Interfaces\RejectedDriverRequestInterface;
use Modules\TripManagement\Interfaces\TempTripNotificationInterface;
use Modules\TripManagement\Interfaces\TripRequestInterfaces;
use Modules\TripManagement\Interfaces\TripRequestTimeInterface;
use Modules\TripManagement\Transformers\TripRequestResource;
use Modules\UserManagement\Interfaces\DriverDetailsInterface;
use Modules\UserManagement\Interfaces\UserLastLocationInterface;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;
use Modules\VehicleManagement\Entities\Vehicle;
use App\Services\TripLockingService;
use App\Services\RealtimeEventPublisher;
use App\Services\ObservabilityService;
use Ramsey\Uuid\Nonstandard\Uuid;

class TripRequestController extends Controller
{
    use LevelUpdateCheckerTrait, TransactionTrait, SmsGatewayForMessage;

    public function __construct(
        private TripRequestInterfaces          $trip,
        private FareBiddingInterface           $bidding,
        private FareBiddingLogInterface        $biddingLog,
        private UserLastLocationInterface      $lastLocation,
        private DriverDetailsInterface         $driverDetails,
        private RejectedDriverRequestInterface $rejectedRequest,
        private TempTripNotificationInterface  $tempNotification,
        private ReviewInterface                $review,
        private TripRequestTimeInterface       $time,
        private TripLockingService             $tripLockingService,
        private RealtimeEventPublisher         $realtimeEventPublisher,
    )
    {
    }

    public function rideResumeStatus()
    {
        $trip = $this->getIncompleteRide();
        if (!$trip) {
            return response()->json(responseFormatter(constant: DEFAULT_404), 404);

        }
        $trip = TripRequestResource::make($trip);
        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trip));
    }

    /**
     * Summary of bid
     * @param Request $request
     * @return JsonResponse
     */
    public function bid(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        if ($user->driverDetails->availability_status != 'available' || $user->driverDetails->is_online != 1) {

            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'bid_fare' => 'numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->trip->getBy('id', $request['trip_request_id'], attributes: ['relations' => 'customer']);

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id) {

            return response()->json(responseFormatter(constant: TRIP_REQUEST_DRIVER_403), 403);
        }
        $attributes = [
            'additionalColumn' => 'driver_id',
            'additionalValue' => $user->id
        ];
        $bidding = $this->bidding->getBy(column: 'trip_request_id', value: $request['trip_request_id'], attributes: $attributes);
        if ($bidding) {

            return response()->json(responseFormatter(constant: BIDDING_SUBMITTED_403), 403);
        }
        $this->bidding->store(attributes: [
            'trip_request_id' => $request['trip_request_id'],
            'driver_id' => $user->id,
            'customer_id' => $trip->customer_id,
            'bid_fare' => $request['bid_fare']
        ]);

        $push = getNotification('received_new_bid');
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'driver_bid_received',
            user_id: $trip->customer->id
        );
        return response()->json(responseFormatter(constant: BIDDING_ACTION_200));
    }

    /**
     * Summary of requestAction
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function requestAction(Request $request): JsonResponse
    {
        // OBSERVABILITY: Log controller entry
        $controllerStartTime = ObservabilityService::observeControllerEntry(
            'TripRequestController',
            'requestAction',
            ['trip_request_id' => $request->input('trip_request_id'), 'action' => $request->input('action')],
            auth('api')->id(),
            $request->input('trip_request_id')
        );
        
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            // OBSERVABILITY: Log validation failure
            ObservabilityService::observeValidation('requestAction', false, $validator->errors()->toArray());
            ObservabilityService::observeControllerExit('TripRequestController', 'requestAction', $controllerStartTime, 'validation_failed');
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        
        // OBSERVABILITY: Log validation passed
        ObservabilityService::observeValidation('requestAction', true);

        $user = auth('api')->user();
        $trip = $this->trip->getBy('id', $request['trip_request_id'], [
            'relations' => ['customer', 'coordinate', 'parcelUserInfo', 'tripStatus']
        ]);

        if (!$trip) {
            \Log::warning('OBSERVE: Trip not found', [
                'trip_request_id' => $request['trip_request_id'],
                'user_id' => $user->id,
                'trace_id' => ObservabilityService::getTraceId()
            ]);
            ObservabilityService::observeControllerExit('TripRequestController', 'requestAction', $controllerStartTime, 'trip_not_found', $user->id, $request['trip_request_id']);
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        
        // OBSERVABILITY: Log trip current state
        \Log::info('OBSERVE: Trip found for action', [
            'trip_id' => $trip->id,
            'current_status' => $trip->current_status,
            'driver_id' => $trip->driver_id,
            'customer_id' => $trip->customer_id,
            'action_requested' => $request['action'],
            'requesting_driver' => $user->id,
            'trace_id' => ObservabilityService::getTraceId()
        ]);

        if ($request['action'] != ACCEPTED) {
            if (get_cache('bid_on_fare') ?? 0) {
                $allBidding = $this->bidding->get(limit: 200, offset: 1, attributes: [
                    'trip_request_id' => $request['trip_request_id'],
                    'driver_id' => $user?->id,
                ]);

                if (count($allBidding) > 0) {
                    $push = getNotification('driver_cancel_ride_request');
                    sendDeviceNotification(
                        fcm_token: $trip->customer->fcm_token,
                        title: translate($push['title']),
                        description: translate(textVariableDataFormat(value: $push['description'])),
                        status: $push['status'],
                        ride_request_id: $trip->id,
                        type: $trip->type,
                        action: 'driver_after_bid_trip_rejected',
                        user_id: $trip->customer->id
                    );
                    $this->bidding->destroyData([
                        'column' => 'id',
                        'ids' => $allBidding->pluck('id')
                    ]);
                }
            }
            $data = $this->tempNotification->getBy([
                'trip_request_id' => $request->trip_request_id,
                'user_id' => auth()->id()
            ]);
            if ($data) {
                $data->delete();
            }

            $this->rejectedRequest->store([
                'trip_request_id' => $trip->id,
                'user_id' => $user->id
            ]);

            return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
        }

        // ATOMIC TRIP ASSIGNMENT
        // OBSERVABILITY: Log before lock attempt
        $lockStartTime = microtime(true);
        \Log::info('OBSERVE: Attempting trip lock', [
            'trip_id' => $request['trip_request_id'],
            'driver_id' => $user->id,
            'current_trip_status' => $trip->current_status,
            'current_trip_driver' => $trip->driver_id,
            'trace_id' => ObservabilityService::getTraceId()
        ]);
        
        $lockResult = $this->tripLockingService->lockAndAssignTrip(
            tripId: $request['trip_request_id'],
            driverId: $user->id
        );
        
        // OBSERVABILITY: Log lock result
        $lockDuration = round((microtime(true) - $lockStartTime) * 1000, 2);
        \Log::info('OBSERVE: Trip lock result', [
            'trip_id' => $request['trip_request_id'],
            'driver_id' => $user->id,
            'success' => $lockResult['success'],
            'message' => $lockResult['message'] ?? null,
            'duration_ms' => $lockDuration,
            'trace_id' => ObservabilityService::getTraceId()
        ]);

        if (!$lockResult['success']) {
            if ($lockResult['trip'] && $lockResult['trip']->driver_id == $user->id) {
                // OBSERVABILITY: Log idempotent retry detection
                \Log::info('OBSERVE: Idempotent trip accept detected (already assigned to this driver)', [
                    'trip_id' => $request['trip_request_id'],
                    'driver_id' => $user->id,
                    'trace_id' => ObservabilityService::getTraceId()
                ]);
                
                // Trip already assigned to this driver, check if acceptance was completed
                $existingTrip = $this->trip->getBy('id', $request['trip_request_id'], [
                    'relations' => ['customer', 'coordinate', 'parcelUserInfo', 'tripStatus', 'time', 'vehicle.model', 'vehicleCategory']
                ]);

                // If trip has OTP and vehicle assigned, acceptance was completed
                if ($existingTrip->otp && $existingTrip->vehicle_id) {
                    \Log::info('OBSERVE: Returning cached acceptance result (fully completed before)', [
                        'trip_id' => $request['trip_request_id'],
                        'driver_id' => $user->id,
                        'has_otp' => true,
                        'has_vehicle' => true,
                        'trace_id' => ObservabilityService::getTraceId()
                    ]);
                    
                    ObservabilityService::observeControllerExit('TripRequestController', 'requestAction', $controllerStartTime, 'success_idempotent', $user->id, $request['trip_request_id']);
                    $data = TripRequestResource::make($existingTrip);
                    return response()->json(responseFormatter(DEFAULT_UPDATE_200, content: $data));
                }

                // Otherwise, continue with acceptance flow to complete it
                $trip = $existingTrip;
            } else {
                return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403, $lockResult['message']), 403);
            }
        } else {
            $trip = $lockResult['trip'];
        }

        $env = env('APP_MODE');
        $smsConfig = Setting::where('settings_type', SMS_CONFIG)->where('live_values->status', 1)->exists();
        $otp = ($env == "live" && $smsConfig) ? rand(1000, 9999) : '0000';

        $driverCurrentStatus = $this->driverDetails->getBy(column: 'user_id', value: $user->id, attributes: [
            'whereInColumn' => 'availability_status',
            'whereInValue' => ['available', 'on_bidding'],
        ]);
        if (!$driverCurrentStatus) {
            return response()->json(responseFormatter(DRIVER_403), 403);
        }

        if ($trip->current_status === "cancelled") {
            return response()->json(responseFormatter(DRIVER_REQUEST_ACCEPT_TIMEOUT_408), 403);
        }

        $bid_on_fare = get_cache('bid_on_fare') ?? 0;
        $assignedVehicleCategoryId = $trip->vehicle_category_id;
        if (empty($assignedVehicleCategoryId)) {
            $assignedVehicleCategoryId = $user->vehicle->category_id ?? null;
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
            'otp' => $otp,
            'vehicle_id' => $user->vehicle->id,
            'vehicle_category_id' => $assignedVehicleCategoryId,
            'current_status' => 'accepted', // Set status after all validations pass
        ];

        if ($bid_on_fare) {
            if ($trip->estimated_fare != $trip->actual_fare) {
                $this->bidding->store(attributes: [
                    'trip_request_id' => $request['trip_request_id'],
                    'driver_id' => $user->id,
                    'customer_id' => $trip->customer_id,
                    'bid_fare' => $trip->actual_fare
                ]);
            }
            $attributes['actual_fare'] = $trip->actual_fare;
        }

        // Update trip
        $trip = $this->trip->update(attributes: $attributes, id: $request['trip_request_id']);

        $trip->tripStatus()->update([
            'accepted' => now()
        ]);

        $this->rejectedRequest->destroyData([
            'column' => 'trip_request_id',
            'value' => $trip->id,
        ]);

        // Offload slow external API calls (Routing, FCM, OTP, etc.)
        $parcelReceiverInfo = null;
        if ($trip->type == PARCEL && $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number
            && businessConfig('parcel_tracking_message')?->value
            && businessConfig('parcel_tracking_status')?->value == 1) {
            $receiver = $trip->parcelUserInfo->firstWhere('user_type', RECEIVER);
            $parcelReceiverInfo = [
                'contact_number' => $receiver?->contact_number,
                'name' => $receiver?->name,
                'ref_id' => $trip->ref_id,
                'tracking_link' => route('track-parcel', $trip->ref_id)
            ];
        }

        $otpRequired = (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
        $sendOtp = $otpRequired && $trip->type === RIDE_REQUEST && $trip->otp;

        ProcessTripAcceptNotificationsJob::dispatch(
            tripId: $trip->id,
            customerId: $trip->customer->id,
            customerFcmToken: $trip->customer?->fcm_token,
            customerPhone: $trip->customer?->phone,
            tripType: $trip->type,
            tripOtp: $trip->otp,
            sendOtp: $sendOtp,
            parcelReceiverInfo: $parcelReceiverInfo,
            driverId: $user->id,
            driverFcmToken: $user->fcm_token,
            customerLanguage: $trip->customer?->current_language_key ?? 'en',
            driverLanguage: $user->current_language_key ?? 'en'
        )->onQueue('high');

        // Refresh trip from database to ensure latest data
        $trip = $this->trip->getBy('id', $request['trip_request_id'], [
            'relations' => ['customer', 'coordinate', 'parcelUserInfo', 'tripStatus', 'time', 'vehicle.model', 'vehicleCategory', 'driver']
        ]);
        $data = TripRequestResource::make($trip);

        // ====================================================================
        // CRITICAL FIX: Publish trip accepted event via Redis BEFORE response
        // This ensures driver app receives socket notification immediately,
        // fixing the "second press works" bug.
        // ====================================================================
        try {
            $driverData = [
                'id' => $user->id,
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
                'phone' => $user->phone ?? '',
                'profile_image' => $user->profile_image ?? '',
                'vehicle' => [
                    'brand' => $user->vehicle?->model?->brand?->name ?? '',
                    'model' => $user->vehicle?->model?->name ?? '',
                    'licence_plate' => $user->vehicle?->licence_plate_number ?? '',
                    'color' => $user->vehicle?->color ?? '',
                ],
            ];

            $this->realtimeEventPublisher->publishTripAccepted(
                $trip,
                $driverData,
                $data->resolve()
            );

            \Log::info('Trip accepted event published via Redis BEFORE response', [
                'trip_id' => $trip->id,
                'driver_id' => $user->id,
            ]);
        } catch (Exception $e) {
            \Log::error('Failed to publish trip accepted event', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage()
            ]);
        }

        // Legacy Pusher broadcast (keep for backward compatibility)
        try {
            checkPusherConnection(DriverTripAcceptedEvent::broadcast($trip));
        } catch (Exception $exception) {}

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200, content: $data));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function rideStatusUpdate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required',
            'trip_request_id' => 'required',
            'return_time' => 'sometimes',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $user = auth('api')->user();
        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id'], attributes: ['relations' => 'customer']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_400), 403);
        }
        // Idempotent cancellation: if already cancelled and driver is trying to cancel, return success
        if ($trip->current_status == 'cancelled') {
            if ($request->status == 'cancelled') {
                $data = TripRequestResource::make($trip);
                return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
            }
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        // Idempotent completion: if already completed and driver is trying to complete, return success
        if ($trip->current_status == 'completed') {
            if ($request->status == 'completed') {
                $data = TripRequestResource::make($trip);
                return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
            }
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }
        if ($trip->current_status == RETURNING) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
        }
        if ($trip->is_paused) {

            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        $attributes = [
            'column' => 'id',
            'value' => $request['trip_request_id'],
            'trip_status' => $request['status'],
            'trip_cancellation_reason' => utf8Clean($request['cancel_reason'] ?? null)
        ];
        DB::beginTransaction();
        if ($request->status == 'completed' || $request->status == 'cancelled') {
            if ($request->status == 'cancelled') {
                $attributes['fee']['cancelled_by'] = 'driver';
                //referral
                if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
                    $trip->customer->referralCustomerDetails()->update([
                        'is_used' => 1
                    ]);
                    if ($trip->customer?->referralCustomerDetails?->ref_by_earning_amount && $trip->customer?->referralCustomerDetails?->ref_by_earning_amount > 0) {
                        $shareReferralUser = $trip->customer?->referralCustomerDetails?->shareRefferalCustomer;
                        $this->customerReferralEarningTransaction($shareReferralUser, $trip->customer?->referralCustomerDetails?->ref_by_earning_amount);

                        $referralData = [
                            'fcm_token' => $shareReferralUser?->fcm_token,
                            'amount' => getCurrencyFormat($trip->customer?->referralCustomerDetails?->ref_by_earning_amount),
                            'user_id' => $shareReferralUser?->id
                        ];
                    }
                }
            }
            $attributes['coordinate']['drop_coordinates'] = new Point($trip->driver->lastLocations->latitude, $trip->driver->lastLocations->longitude);

            //set driver availability_status as on_trip
            $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $user?->id);
            if ($trip->type == RIDE_REQUEST) {
                $driverDetails->ride_count = 0;
            } else if ($request->status == 'completed' || ($trip->driver_id && $request->status == 'cancelled' && $trip->current_status == ACCEPTED)) {
                --$driverDetails->parcel_count;
            }
            $driverDetails->save();

        }

        $data = $this->trip->updateRelationalTable($attributes);
        if ($request->status == 'cancelled') {
            $this->customerLevelUpdateChecker($trip->customer);
            $this->driverLevelUpdateChecker(auth()->user());
        } elseif ($request->status == 'completed') {
            $this->customerLevelUpdateChecker($trip->customer);
            $this->driverLevelUpdateChecker(auth()->user());
        }
        DB::commit();

        // Offload slow external API calls to background job
        dispatch(new \App\Jobs\ProcessRideStatusUpdateNotificationsJob($trip->id, $request->status, $referralData ?? null));

        if ($trip->driver_id && $request->status == 'cancelled' && $trip->current_status == ONGOING && $trip->type == PARCEL) {
                            $env = env('APP_MODE');
                            $smsConfig = Setting::where('settings_type', SMS_CONFIG)->where('live_values->status', 1)->exists();
                            $otp = ($env == "live" && $smsConfig) ? rand(1000, 9999) : '0000';            $trip->otp = $otp;            $trip->return_fee = 0;
            $trip->current_status = RETURNING;
            $trip->return_time = Carbon::parse($request->return_time);
            $trip->save();
            $trip->tripStatus()->update([
                RETURNING => now()
            ]);
            if ($trip->cancellation_fee > 0) {
                $this->driverParcelCancellationTransaction($trip);
            }
            if ($trip?->parcel?->payer === 'sender' && $trip->payment_status == PAID) {
                if ($trip->payment_method === 'cash') {
                    $this->senderCashPaymentDriverParcelCancelReverseTransaction($trip);
                } elseif ($trip->payment_method === 'wallet') {
                    $this->senderWalletPaymentDriverParcelCancelReverseTransaction($trip);
                } else {
                    $this->senderDigitalPaymentDriverParcelCancelReverseTransaction($trip);
                }
            }
        }


        //Get status wise notification message
        if ($request->status == 'cancelled' && $trip->type == PARCEL) {
            $push = getNotification('ride_' . $request->status);
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                action: 'parcel_cancelled',
                user_id: $trip->customer->id
            );
        } else {
            $action = 'ride_' . $request->status;
            $push = getNotification($action);
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip->type,
                action: $action,
                user_id: $trip->customer->id
            );
        }
        if ($request->status == "completed") {
            try {
                checkPusherConnection(DriverTripCompletedEvent::broadcast($trip));
            } catch (Exception $exception) {

            }
        }
        if ($request->status == "cancelled") {
            try {
                checkPusherConnection(DriverTripCancelledEvent::broadcast($trip));
            } catch (Exception $exception) {

            }
        }

        // Return the refreshed trip with updated status (not the old $data)
        $trip = $trip->fresh(['driver', 'vehicle.model', 'vehicleCategory', 'tripStatus', 'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo', 'parcelRefund']);
        $resource = TripRequestResource::make($trip);
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $resource));
    }


    /**
     * Trip otp submit.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function matchOtp(Request $request): JsonResponse
    {
        // OBSERVABILITY: Log controller entry for OTP verification
        $controllerStartTime = ObservabilityService::observeControllerEntry(
            'TripRequestController',
            'matchOtp',
            ['trip_request_id' => $request->input('trip_request_id')],
            auth('api')->id(),
            $request->input('trip_request_id')
        );
        
        ObservabilityService::observeOtpVerification($request->input('trip_request_id') ?? 'unknown', 'received', auth('api')->id());
        
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => Rule::requiredIf(function () {
                return (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
            }), 'min:4|max:4',
        ]);

        if ($validator->fails()) {
            ObservabilityService::observeOtpVerification($request->input('trip_request_id') ?? 'unknown', 'validation_failed', auth('api')->id(), json_encode($validator->errors()->toArray()));
            ObservabilityService::observeControllerExit('TripRequestController', 'matchOtp', $controllerStartTime, 'validation_failed');
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        
        ObservabilityService::observeOtpVerification($request['trip_request_id'], 'validated', auth('api')->id());

        // Load trip without relations for faster validation
        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id']);

        if (!$trip) {
            ObservabilityService::observeOtpVerification($request['trip_request_id'], 'trip_not_found', auth('api')->id());
            ObservabilityService::observeControllerExit('TripRequestController', 'matchOtp', $controllerStartTime, 'trip_not_found');
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        
        // OBSERVABILITY: Log trip state before OTP check
        \Log::info('OBSERVE: OTP verification - trip found', [
            'trip_id' => $trip->id,
            'current_status' => $trip->current_status,
            'trip_driver_id' => $trip->driver_id,
            'requesting_driver_id' => auth('api')->id(),
            'trace_id' => ObservabilityService::getTraceId()
        ]);
        
        if ($trip->driver_id != auth('api')->id()) {
            ObservabilityService::observeOtpVerification($trip->id, 'driver_mismatch', auth('api')->id(), 'Driver ID does not match trip driver');
            ObservabilityService::observeControllerExit('TripRequestController', 'matchOtp', $controllerStartTime, 'driver_mismatch', auth('api')->id(), $trip->id);
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if (array_key_exists('otp', $request->all()) && $request['otp'] && $trip->otp != $request['otp']) {
            ObservabilityService::observeOtpVerification($trip->id, 'otp_mismatch', auth('api')->id(), 'OTP does not match');
            ObservabilityService::observeControllerExit('TripRequestController', 'matchOtp', $controllerStartTime, 'otp_mismatch', auth('api')->id(), $trip->id);
            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
        }
        
        // OBSERVABILITY: Log OTP matched, starting DB update
        $dbStartTime = ObservabilityService::observeDbTransactionStart('matchOtp_update_status', $trip->id);
        ObservabilityService::observeTripStateChange($trip->id, $trip->current_status ?? 'accepted', ONGOING, $trip->driver_id, $trip->customer_id, 'otp_verified');

        // Update trip status to ONGOING
        DB::beginTransaction();
        $trip->current_status = ONGOING;
        $trip->save();
        $trip->tripStatus()->update(['ongoing' => now()]);
        DB::commit();
        
        // OBSERVABILITY: Log DB transaction committed
        ObservabilityService::observeDbTransactionCommit('matchOtp_update_status', $dbStartTime, $trip->id);

        // ====================================================================
        // CRITICAL FIX: Publish OTP verified event via Redis BEFORE response
        // This ensures driver app immediately shows trip as ongoing.
        // ====================================================================
        try {
            $this->realtimeEventPublisher->publishOtpVerified($trip);
            
            \Log::info('OTP verified event published via Redis BEFORE response', [
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
            ]);
        } catch (Exception $e) {
            \Log::error('Failed to publish OTP verified event', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage()
            ]);
        }

        // Offload slow external API calls to background job
        dispatch(new \App\Jobs\ProcessTripOtpJob($trip->id))->onQueue('high');

        return response()->json(responseFormatter(constant: DEFAULT_STORE_200));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function trackLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required',
            'longitude' => 'required',
            'zoneId' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $request->merge([
            'user_id' => auth('api')->id(),
            'type' => $request->route()->getPrefix() == "api/customer/track-location" ? 'customer' : 'driver',
            'zone_id' => $request->zoneId,
        ]);
        $this->lastLocation->updateOrCreate($request->all());

        return response()->json(responseFormatter(DEFAULT_STORE_200));
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
        $attributes = [
            'column' => 'driver_id',
            'value' => auth('api')->id(),
            'withAvgRelation' => 'driverReceivedReviews',
            'withAvgColumn' => 'rating',
        ];

        if (!is_null($request->filter) && $request->filter != CUSTOM_DATE) {
            $date = getDateRange($request->filter);
        } elseif (!is_null($request->filter)) {
            $date = getDateRange([
                'start' => $request->start,
                'end' => $request->end
            ]);
        }
        if (!empty($date)) {
            $attributes['from'] = $date['start'];
            $attributes['to'] = $date['end'];
        }
        if (!is_null($request->status) && $request->status != ALL) {
            $attributes['column_name'] = 'current_status';
            $attributes['column_value'] = [$request->status];
        }
        $relations = ['customer', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee', 'parcel.parcelCategory', 'parcelRefund'];
        $data = $this->trip->get(limit: $request['limit'], offset: $request['offset'], dynamic_page: true, attributes: $attributes, relations: $relations);

        $resource = TripRequestResource::setData('distance_wise_fare')::collection($data);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $resource, limit: $request['limit'], offset: $request['offset']));
    }

    /**
     * @param $trip_request_id
     * @param Request $request
     * @return JsonResponse
     */
    public function rideDetails(Request $request, $trip_request_id): JsonResponse
    {
        if (!is_null($request->type) && $request->type == 'overview') {
            $data = $this->rideOverview($trip_request_id, PENDING);
            if (!is_null($data)) {
                $resource = TripRequestResource::make($data);

                return response()->json(responseFormatter(DEFAULT_200, $resource));
            }
        } else {
            $data = $this->rideDetailsFormation($trip_request_id);
            if ($data && (is_null($data->driver_id) || auth('api')->id() == $data->driver_id)) {
                $resource = TripRequestResource::make($data->append('distance_wise_fare'));

                return response()->json(responseFormatter(DEFAULT_200, $resource));
            }
        }

        return response()->json(responseFormatter(DEFAULT_404), 403);
    }

    /**
     * Show driver pending trip request.
     *
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pendingRideList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            \Log::warning('pendingRideList: Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $zoneId = $request->header('zoneId');
        \Log::info('pendingRideList: Request received', [
            'zoneId' => $zoneId,
            'limit' => $request->input('limit'),
            'offset' => $request->input('offset'),
            'all_headers' => $request->headers->all()
        ]);

        if (empty($zoneId)) {
            \Log::warning('pendingRideList: No zoneId provided in request headers');
            return response()->json(responseFormatter(ZONE_404));
        }

        $user = auth('api')->user();
        \Log::info('pendingRideList: User authenticated', ['user_id' => $user->id, 'user_type' => $user->user_type ?? 'unknown']);

        if (!$user->driverDetails) {
            \Log::error('pendingRideList: Driver details not found', ['user_id' => $user->id]);
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        if ($user->driverDetails->is_online != 1) {
            \Log::warning('pendingRideList: Driver is offline', ['user_id' => $user->id, 'is_online' => $user->driverDetails->is_online]);
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        $vehicle = Vehicle::query()
            ->where('driver_id', $user->id)
            ->where(function ($query) {
                $query->where('is_active', 1)->orWhere('vehicle_request_status', APPROVED);
            })
            ->latest('updated_at')
            ->first()
            ?? Vehicle::query()->where('driver_id', $user->id)->latest('updated_at')->first();

        if (is_null($vehicle)) {
            \Log::warning('pendingRideList: No vehicle registered', ['user_id' => $user->id]);
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }

        \Log::info('pendingRideList: Vehicle found', [
            'vehicle_id' => $vehicle->id,
            'is_active' => $vehicle->is_active,
            'vehicle_request_status' => $vehicle->vehicle_request_status,
            'category_id' => $vehicle->category_id
        ]);

        if (!$vehicle->is_active && $vehicle->vehicle_request_status !== APPROVED) {
            \Log::warning('pendingRideList: Vehicle not approved/active', ['vehicle_id' => $vehicle->id]);
            return response()->json(responseFormatter(constant: VEHICLE_NOT_APPROVED_OR_ACTIVE_404, content: []), 403);
        }

        $maxParcelRequestAcceptLimit = businessConfig(key: 'maximum_parcel_request_accept_limit', settingsType: DRIVER_SETTINGS);
        $maxParcelRequestAcceptLimitStatus = (bool)($maxParcelRequestAcceptLimit?->value['status'] ?? false);
        $maxParcelRequestAcceptLimitCount = (int)($maxParcelRequestAcceptLimit?->value['limit'] ?? 0);
        $search_radius = (double)get_cache('search_radius') ?? 5;

        \Log::info('pendingRideList: Search config', [
            'search_radius' => $search_radius,
            'parcel_limit_status' => $maxParcelRequestAcceptLimitStatus,
            'parcel_limit_count' => $maxParcelRequestAcceptLimitCount
        ]);

        $location = $this->lastLocation->getBy(column: 'user_id', value: $user->id);
        if (!$location) {
            \Log::warning('pendingRideList: No location found for driver', ['user_id' => $user->id]);
            return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
        }

        \Log::info('pendingRideList: Driver location found', [
            'latitude' => $location->latitude ?? 'null',
            'longitude' => $location->longitude ?? 'null',
            'zone_id' => $location->zone_id ?? 'null'
        ]);

        $vehicleCategoryIds = $vehicle->category_id ?? [];
        if (is_string($vehicleCategoryIds)) {
            $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
        }
        // Ensure it's always an array
        if (!is_array($vehicleCategoryIds)) {
            $vehicleCategoryIds = [$vehicleCategoryIds];
        }

        \Log::info('pendingRideList: Vehicle category IDs', ['category_ids' => $vehicleCategoryIds]);

        if (empty($vehicleCategoryIds)) {
            \Log::warning('pendingRideList: No vehicle categories found', ['vehicle_id' => $vehicle->id]);
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }

        // Check if there are ANY pending trips in the database for this zone
        $totalPendingInZone = \Modules\TripManagement\Entities\TripRequest::where('zone_id', $zoneId)
            ->where('current_status', PENDING)
            ->count();
        \Log::info('pendingRideList: Total pending trips in zone', ['zone_id' => $zoneId, 'count' => $totalPendingInZone]);

        $pendingTrips = $this->trip->getPendingRides(attributes: [
            'ride_count' => $user->driverDetails->ride_count ?? 0,
            'parcel_count' => $user->driverDetails->parcel_count ?? 0,
            'parcel_follow_status' => $maxParcelRequestAcceptLimitStatus,
            'max_parcel_request_accept_limit' => $maxParcelRequestAcceptLimitCount,
            'vehicle_category_id' => $vehicleCategoryIds,
            'driver_locations' => $location,
            'service' => $user->driverDetails->service ?? null,
            'parcel_weight_capacity' => $vehicle->parcel_weight_capacity ?? null,
            'distance' => $search_radius * 1000,
            'zone_id' => $zoneId,
            'relations' => ['driver.driverDetails', 'customer', 'ignoredRequests', 'time', 'fee', 'fare_biddings', 'parcel', 'parcelRefund', 'vehicle'],
            'withAvgRelation' => 'customerReceivedReviews',
            'withAvgColumn' => 'rating',
            'limit' => $request['limit'],
            'offset' => $request['offset']
        ]);

        \Log::info('pendingRideList: Query completed', [
            'trips_found' => $pendingTrips->count(),
            'total_items' => $pendingTrips->total()
        ]);

        $trips = TripRequestResource::collection($pendingTrips);

        return response()->json(
            responseFormatter(
                constant: DEFAULT_200,
                content: $trips,
                limit: $request['limit'],
                offset: $request['offset'],
            )
        );
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function lastRideDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:ongoing,last_trip',
            'trip_type' => 'required|in:ride_request,parcel',

        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = $this->trip->getBy(column: 'driver_id', value: auth()->id(), attributes: ['latest' => true, 'relations' => 'fee', 'parcelRefund', 'column_name' => 'type', 'column_value' => $request->trip_type ?? 'ride_request']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404, content: $trip));
        }

        $data = [];
        $data[] = TripRequestResource::make($trip->append('distance_wise_fare'));

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $data));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function rideWaiting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $time = $this->time->getBy(column: 'trip_request_id', value: $request->trip_request_id);
        $trip = $this->trip->getBy(column: 'id', value: $request->trip_request_id, attributes: ['relations' => ['customer']]);

        if (!$time) {

            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->is_paused == 0) {
            $trip->is_paused = 1;
        } else {
            $trip->is_paused = 0;
            $idle_time = Carbon::parse($time->idle_timestamp)->diffInMinutes(now());
            $time->idle_time += $idle_time;
        }
        $time->idle_timestamp = now();
        $time->save();
        $trip->save();

        $push = getNotification('trip_' . $request->waiting_status);
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'trip_waited_message',
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function arrivalTime(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required'
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $time = TripRequestTime::query()
            ->where('trip_request_id', $request->trip_request_id)
            ->first();

        if (!$time) {

            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        $time->driver_arrives_at = now();
        $time->save();

        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
    }

    public function coordinateArrival(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'is_reached' => 'required|in:coordinate_1,coordinate_2,destination',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $trip = TripRequestCoordinate::query()->firstWhere('trip_request_id', $request->trip_request_id);

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

    public function ignoreTripNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $this->tempNotification->ignoreNotification([
            'trip_request_id' => $request->trip_request_id,
            'user_id' => auth()->id()
        ]);

        return response()->json(responseFormatter(DEFAULT_200));
    }


    #returnedParcel
    public function returnedParcel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => 'required|min:4|max:4',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->trip->getBy(column: 'id', value: $request->trip_request_id, attributes: ['relations' => 'driver', 'driver.driverDetails', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if ($trip->current_status == RETURNED) {
            return response()->json(responseFormatter(TRIP_STATUS_RETURNED_403), 403);
        }
        \Log::info('OTP Debug', ['trip_otp' => $trip->otp, 'request_otp' => $request['otp'], 'trip_otp_type' => gettype($trip->otp), 'request_otp_type' => gettype($request['otp'])]);
        if ($trip->otp != $request['otp']) {

            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
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
        //set driver availability_status as on_trip
        $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $trip->driver_id);
        --$driverDetails->parcel_count;
        $driverDetails->save();
        $push = getNotification('parcel_returned');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $request->trip_request_id,
            type: $trip->type,
            action: 'parcel_returned',
            user_id: $trip->customer->id
        );

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->trip->getBy(column: 'id', value: $request->trip_request_id, attributes: ['relations' => ['customer', 'driver.lastLocations', 'time', 'coordinate', 'fee', 'parcelRefund']]);
        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }

        if ($trip->type === RIDE_REQUEST) {
            $otpMessage = 'Your trip OTP is ' . $trip->otp;
            try {
                if ($trip->customer?->phone) {
                    self::send($trip->customer->phone, $otpMessage);
                }
            } catch (\Exception $exception) {

            }

            if ($trip->customer?->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $trip->customer->fcm_token,
                    title: translate('Trip OTP'),
                    description: translate($otpMessage),
                    status: 1,
                    ride_request_id: $request->trip_request_id,
                    type: $trip->type,
                    action: 'trip_otp',
                    user_id: $trip->customer->id
                );
            }
        } else {
            $push = getNotification('parcel_returning_otp');
            sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'], otp: $trip->otp)),
                status: $push['status'],
                ride_request_id: $request->trip_request_id,
                type: $trip->type,
                action: 'parcel_returning_otp',
                user_id: $trip->customer->id
            );
        }

        return response()->json(responseFormatter(DEFAULT_UPDATE_200, TripRequestResource::make($trip)));
    }


    /**
     * @param $trip_request_id
     * @param $status
     * @return mixed
     */
    private function rideOverview($trip_request_id, $status): mixed
    {
        return $this->trip->getBy(column: 'id', value: $trip_request_id, attributes: [
            'relations' => ['customer', 'vehicleCategory', 'tripStatus', 'time', 'coordinate', 'fee', 'parcel', 'parcelUserInfo', 'parcelRefund'],
            'fare_biddings' => auth()->id(),
            'column_name' => 'current_status',
            'column_value' => $status,
            'withAvgRelation' => 'customerReceivedReviews',
            'withAvgColumn' => 'rating'
        ]);
    }

    /**
     * @param $trip_request_id
     * @return mixed
     */
    private function rideDetailsFormation($trip_request_id): mixed
    {
        return $this->trip->getBy(column: 'id', value: $trip_request_id, attributes: [
            'relations' => ['customer', 'vehicleCategory', 'tripStatus', 'time', 'coordinate', 'fee', 'parcel', 'parcelUserInfo', 'parcelRefund'],
            'withAvgRelation' => 'customerReceivedReviews',
            'withAvgColumn' => 'rating'
        ]);

    }

    private function getIncompleteRide()
    {
        $trip = $this->trip->getBy(column: 'driver_id', value: auth()->guard('api')->id(), attributes: [
            'relations' => ['tripStatus', 'customer', 'driver', 'time', 'coordinate', 'time', 'fee', 'parcelRefund'],
            'withAvgRelation' => 'customerReceivedReviews',
            'withAvgColumn' => 'rating', 'column_name' => 'type', 'column_value' => 'ride_request'
        ]);

        if (!$trip || $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)) {
            return null;
        }
        return $trip;
    }

    private function getIncompleteRideCustomer($id): mixed
    {
        $trip = $this->trip->getBy(column: 'customer_id', value: $id, attributes: [
            'relations' => ['fee']
        ]);

        if (!$trip || $trip->type != 'ride_request' ||
            $trip->fee->cancelled_by == 'driver' ||
            (!$trip->driver_id && $trip->current_status == 'cancelled') ||
            ($trip->driver_id && $trip->payment_status == PAID)) {

            return null;
        }
        return $trip;
    }


    /**
     * @param Request $request
     * @return array|JsonResponse
     */
    public function tripOverView(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter' => ['required', Rule::in([TODAY, THIS_WEEK, LAST_WEEK])],
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        if ($request->filter == THIS_WEEK) {
            $start = now()->startOfWeek();
            $end = now()->endOfWeek();
            $day = ['Mon', 'Tues', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }
        if ($request->filter == LAST_WEEK) {
            $start = Carbon::now()->subWeek()->startOfWeek();
            $end = Carbon::now()->subWeek()->endOfWeek();
            $day = ['Mon', 'Tues', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        }
        if ($request->filter == TODAY) {
            $start = Carbon::today()->startOfDay();
            $end = Carbon::today()->endOfDay();
            $day = [
                '6:00 am',
                '10:00 am',
                '2:00 pm',
                '6:00 pm',
                '10:00 pm',
                '2:00 am',
            ];
        }
        $trips = $this->trip->get(limit: 9999999999, offset: 1, attributes: [
            'from' => $start,
            'to' => $end,
            'column' => 'driver_id',
            'value' => auth()->id()
        ]);
        if ($request->filter == TODAY) {
            $incomeStat = [];
            $startTime = strtotime('6:00 AM');

            for ($i = 0; $i < 6; $i++) {
                $incomeStat[$day[$i]] = $trips
                    ->whereBetween('created_at', [
                        date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', $startTime),
                        date('Y-m-d', strtotime(TODAY)) . ' ' . date('H:i:s', strtotime('+4 hours', $startTime))
                    ])
                    ->sum('paid_fare');
                $startTime = strtotime('+4 hours', $startTime);
            }
        } else {
            $period = CarbonPeriod::create($start, $end);
            $trips = $this->trip->get(limit: 9999999999, offset: 1, attributes: [
                'from' => $start,
                'to' => $end,
                'column' => 'driver_id',
                'value' => auth()->id()
            ]);
            $incomeStat = [];
            foreach ($period as $key => $p) {
                $incomeStat[$day[$key]] = $trips
                    ->whereBetween('created_at', [$p->copy()->startOfDay(), $p->copy()->endOfDay()])
                    ->sum('paid_fare');
            }
        }


        $attributes = [
            'column' => 'received_by',
            'value' => auth()->id(),
            'whereBetween' => [$start, $end]
        ];
        $totalReviews = $this->review->get(limit: 9999999999, offset: 1, attributes: $attributes);
        $totalReviews = $totalReviews->count();

        $totalTrips = $trips->count();
        if ($totalTrips == 0) {
            $fallback = 1;
        } else {
            $fallback = $totalTrips;
        }
        $successTrips = $trips->where('current_status', 'completed')->count();
        $cancelTrips = $trips->where('current_status', 'cancelled')->count();
        $totalEarn = $trips->sum('paid_fare');

        return [
            'success_rate' => ($successTrips / $fallback) * 100,
            'total_trips' => $totalTrips,
            'total_earn' => $totalEarn,
            'total_cancel' => $cancelTrips,
            'total_reviews' => $totalReviews,
            'income_stat' => $incomeStat
        ];
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
            'column' => 'driver_id',
            'value' => auth()->id(),
            'whereNotNull' => 'driver_id',
        ];

        $trips = $this->trip->pendingParcelList($attributes, 'driver');

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
            'column' => 'driver_id',
            'value' => auth()->id(),
        ]);
        $trips = TripRequestResource::collection($trips);

        return response()->json(responseFormatter(constant: DEFAULT_200, content: $trips, limit: $request->limit, offset: $request->offset));
    }

}
