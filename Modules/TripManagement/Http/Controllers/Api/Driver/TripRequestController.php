<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use App\Events\AnotherDriverTripAcceptedEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Events\DriverTripAcceptedEvent;
use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use App\Events\DriverTripStartedEvent;
use App\Jobs\SendPushNotificationJob;
use App\Services\RealtimeEventPublisher;
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
use App\Services\TripAtomicLockService;
use App\Jobs\ProcessTripAcceptanceJob;
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
        private TripAtomicLockService          $atomicLock,
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
     * OPTIMIZED FOR PERFORMANCE:
     * - Early validation to fail fast
     * - Single trip query instead of multiple
     * - Reduced redundant database calls
     *
     * @param Request $request
     * @return JsonResponse
     */
     public function requestAction(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {
            \Log::warning('Trip action validation failed', [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['password', 'token'])
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $user = auth('api')->user();
        $tripRequestId = $request['trip_request_id'];
        $action = $request['action'];

        \Log::info('Driver trip action request', [
            'driver_id' => $user->id,
            'trip_request_id' => $tripRequestId,
            'action' => $action,
            'request_time' => now()->toDateTimeString()
        ]);

        // === EARLY VALIDATION - FAIL FAST ===
        // Check driver status BEFORE loading trip (saves DB query if driver unavailable)
        if (!$user->driverDetails) {
            \Log::error('Driver details not found', ['driver_id' => $user->id]);
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        $user_status = $user->driverDetails->availability_status;
        if ($user_status == 'unavailable' || !$user->driverDetails->is_online) {
            \Log::warning('Driver unavailable or offline', [
                'driver_id' => $user->id,
                'availability_status' => $user_status,
                'is_online' => $user->driverDetails->is_online
            ]);
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        // Check vehicle BEFORE loading trip (saves DB query)
        if (!$user->vehicle) {
            \Log::error('Driver has no vehicle', ['driver_id' => $user->id]);
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404), 403);
        }

        // Load trip ONCE with all needed relations
        $trip = $this->trip->getBy('id', $tripRequestId, ['relations' => ['customer', 'vehicleCategory']]);

        if (!$trip) {
            \Log::warning('Trip not found for action', [
                'trip_request_id' => $tripRequestId,
                'driver_id' => $user->id
            ]);
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }

        // Handle rejection
        if ($action != ACCEPTED) {
            \Log::info('Driver rejected trip', [
                'driver_id' => $user->id,
                'trip_id' => $trip->id
            ]);
            
            if (get_cache('bid_on_fare') ?? 0) {
                $allBidding = $this->bidding->get(limit: 200, offset: 1, attributes: [
                    'trip_request_id' => $tripRequestId,
                    'driver_id' => $user->id,
                ]);

                if (count($allBidding) > 0) {
                    $push = getNotification('driver_cancel_ride_request');
                    if ($trip->customer?->fcm_token) {
                        SendPushNotificationJob::dispatch([
                            'title' => translate($push['title']),
                            'description' => translate(textVariableDataFormat(value: $push['description'])),
                            'status' => $push['status'],
                            'ride_request_id' => $trip->id,
                            'type' => $trip->type,
                            'action' => 'driver_after_bid_trip_rejected',
                            'user' => [[
                                'fcm_token' => $trip->customer->fcm_token,
                                'user_id' => $trip->customer->id,
                            ]]
                        ])->onQueue('notifications');
                    }
                    $this->bidding->destroyData([
                        'column' => 'id',
                        'ids' => $allBidding->pluck('id')
                    ]);
                }
            }

            $data = $this->tempNotification->getBy([
                'trip_request_id' => $tripRequestId,
                'user_id' => $user->id
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

        // ⚡ === ATOMIC ACCEPTANCE FLOW (LAYER 1 + LAYER 2) === ⚡
        $acceptStartTime = microtime(true);

        \Log::info('🚀 Driver attempting to accept trip', [
            'driver_id' => $user->id,
            'trip_id' => $trip->id,
            'trip_status' => $trip->current_status,
            'validation_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);

        // Check driver availability status (already loaded, just validate)
        if (!in_array($user_status, ['available', 'on_bidding'])) {
            \Log::warning('Driver not in available status for acceptance', [
                'driver_id' => $user->id,
                'current_status' => $user_status
            ]);
            return response()->json(responseFormatter(DRIVER_403), 403);
        }

        // Check if trip is still available
        if ($trip->current_status === 'cancelled') {
            \Log::warning('Cannot accept cancelled trip', [
                'trip_id' => $trip->id,
                'driver_id' => $user->id
            ]);
            return response()->json(responseFormatter(DRIVER_REQUEST_ACCEPT_TIMEOUT_408), 403);
        }

        // Validate vehicle category (vehicle already checked earlier)
        $customer_vehicle_category_id = $trip->vehicle_category_id;
        $vehicle_category_ids = is_string($user->vehicle->category_id)
            ? json_decode($user->vehicle->category_id, true)
            : $user->vehicle->category_id;

        if (!is_array($vehicle_category_ids)) {
            $vehicle_category_ids = [$vehicle_category_ids];
        }

        if (!in_array($customer_vehicle_category_id, $vehicle_category_ids ?? [])) {
            \Log::warning('Vehicle category mismatch', [
                'trip_id' => $trip->id,
                'driver_id' => $user->id,
                'required' => $customer_vehicle_category_id,
                'driver_has' => $vehicle_category_ids
            ]);
            return response()->json(responseFormatter('VEHICLE_CATEGORY_MISMATCH_403'), 403);
        }

        // Generate OTP
        $env = env('APP_MODE');
        $otp = $env != 'live' ? '0000' : rand(1000, 9999);

        // Prepare additional data for async job
        $bid_on_fare = get_cache('bid_on_fare') ?? 0;
        $additionalData = [
            'otp' => $otp,
            'vehicle_id' => $user->vehicle->id,
            'vehicle_category_id' => $customer_vehicle_category_id,
        ];

        // Include actual_fare if bid_on_fare is enabled
        if ($bid_on_fare && isset($trip->actual_fare)) {
            $additionalData['actual_fare'] = $trip->actual_fare;
        }

        // ⚡ LAYER 1: ATOMIC LOCK (Redis SETNX) ⚡
        // This MUST complete in < 100ms
        try {
            $lockResult = $this->atomicLock->acquireTripLock(
                tripId: $tripRequestId,
                driverId: $user->id
            );
        } catch (\Exception $e) {
            \Log::error('❌ Atomic lock service exception', [
                'trip_id' => $tripRequestId,
                'driver_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(responseFormatter(DEFAULT_400, 'Failed to process trip acceptance'), 500);
        }

        // Handle lock failure
        if (!$lockResult['success']) {
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            // IDEMPOTENT RETRY: Same driver already accepted
            if ($lockResult['is_retry']) {
                \Log::info('✅ Idempotent retry - trip already accepted by this driver', [
                    'trip_id' => $tripRequestId,
                    'driver_id' => $user->id,
                    'elapsed_ms' => $elapsed
                ]);

                // Load fresh trip data and return success
                $trip = $this->trip->getBy('id', $tripRequestId, [
                    'relations' => ['customer', 'vehicleCategory', 'tripStatus', 'coordinate', 'fee', 'time', 'parcel', 'parcelUserInfo']
                ]);

                $resource = TripRequestResource::make($trip);
                return response()->json(responseFormatter(DEFAULT_UPDATE_200, content: $resource));
            }

            // LOCK FAILED: Another driver won
            \Log::warning('❌ Trip already accepted by another driver', [
                'trip_id' => $tripRequestId,
                'driver_id' => $user->id,
                'reason' => $lockResult['message'],
                'elapsed_ms' => $elapsed
            ]);

            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403, $lockResult['message']), 403);
        }

        // 🎉 LOCK ACQUIRED! Return success immediately
        $lockElapsed = round((microtime(true) - $acceptStartTime) * 1000, 2);
        \Log::info('🎉 Trip lock acquired successfully', [
            'trip_id' => $tripRequestId,
            'driver_id' => $user->id,
            'lock_time_ms' => $lockElapsed,
            'performance' => $lockElapsed < 100 ? '⚡ FAST' : '⚠️ SLOW'
        ]);

        // ⚙️ LAYER 2: ASYNC PROCESSING (Background Job) ⚙️
        // Dispatch ALL heavy work to background
        dispatch(new ProcessTripAcceptanceJob($tripRequestId, $user->id, $additionalData))
            ->onQueue('high-priority');

        \Log::info('⚙️ Background job dispatched for trip acceptance', [
            'trip_id' => $tripRequestId,
            'driver_id' => $user->id
        ]);

        $totalElapsed = round((microtime(true) - $startTime) * 1000, 2);
        \Log::info('✅ Trip acceptance response sent', [
            'trip_id' => $tripRequestId,
            'driver_id' => $user->id,
            'total_time_ms' => $totalElapsed,
            'lock_time_ms' => $lockElapsed,
            'performance' => $totalElapsed < 200 ? '⚡⚡⚡ EXCELLENT' : ($totalElapsed < 500 ? '⚡⚡ GOOD' : '⚠️ NEEDS OPTIMIZATION')
        ]);

        // CRITICAL PERFORMANCE: Return SUCCESS immediately without heavy resource transformation
        // The background job will handle all notifications and updates
        // The driver app will call /ride/details separately to get full trip data
        return response()->json(responseFormatter(
            constant: DEFAULT_UPDATE_200,
            content: [
                'trip_id' => $tripRequestId,
                'status' => 'accepted',
                'message' => 'Trip accepted successfully. Fetching details...'
            ]
        ));
    }


    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function rideStatusUpdate(Request $request): JsonResponse
    {
        \Log::info('rideStatusUpdate called', [
            'input' => $request->except(['password', 'token']),
            'driver_id' => auth('api')->id()
        ]);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,cancelled',
            'trip_request_id' => 'required',
            'return_time' => 'sometimes',
        ]);

        if ($validator->fails()) {
            \Log::warning('rideStatusUpdate validation failed', [
                'errors' => $validator->errors()->toArray()
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        
        $user = auth('api')->user();
        $tripRequestId = $request['trip_request_id'];
        $status = $request['status'];
        
        try {
            $trip = $this->trip->getBy(column: 'id', value: $tripRequestId, attributes: [
                'relations' => ['customer', 'driver.lastLocations', 'fee', 'parcel', 'tripStatus']
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to load trip', [
                'trip_request_id' => $tripRequestId,
                'error' => $e->getMessage()
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: ['Failed to load trip']), 500);
        }
        
        if (!$trip) {
            \Log::warning('Trip not found for status update', ['trip_request_id' => $tripRequestId]);
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        
        \Log::info('Trip loaded for status update', [
            'trip_id' => $trip->id,
            'current_status' => $trip->current_status,
            'driver_id' => $trip->driver_id,
            'requested_status' => $status
        ]);
        
        if ($trip->driver_id != $user->id) {
            \Log::warning('Driver mismatch for trip', [
                'trip_driver' => $trip->driver_id,
                'request_driver' => $user->id
            ]);
            return response()->json(responseFormatter(DEFAULT_400), 403);
        }
        if ($trip->current_status == 'cancelled') {
            \Log::warning('Trip already cancelled', ['trip_id' => $trip->id]);
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        if ($trip->current_status == 'completed') {
            \Log::warning('Trip already completed', ['trip_id' => $trip->id]);
            return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
        }
        if ($trip->current_status == RETURNING) {
            \Log::warning('Trip in returning status', ['trip_id' => $trip->id]);
            return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
        }
        if ($trip->is_paused) {
            \Log::warning('Trip is paused', ['trip_id' => $trip->id]);
            return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
        }

        $attributes = [
            'column' => 'id',
            'value' => $tripRequestId,
            'trip_status' => $status,
            'trip_cancellation_reason' => utf8Clean($request['cancel_reason'] ?? null)
        ];

        // Variable to store original trip status before update
        $originalStatus = null;

        // Use pessimistic locking to prevent concurrent updates
        DB::beginTransaction();
        try {
            // Lock the trip record for update to prevent race conditions
            // Reload with all necessary relations
            $trip = \Modules\TripManagement\Entities\TripRequest::with(['customer', 'driver.lastLocations', 'fee', 'parcel', 'tripStatus'])
                ->where('id', $tripRequestId)
                ->lockForUpdate()
                ->first();

            if (!$trip) {
                DB::rollBack();
                \Log::warning('Trip not found during status update (locked query)', ['trip_request_id' => $tripRequestId]);
                return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
            }

            // Verify driver ownership (inside transaction to prevent race conditions)
            if ($trip->driver_id != $user->id) {
                DB::rollBack();
                \Log::warning('Driver mismatch for trip (locked check)', [
                    'trip_driver' => $trip->driver_id,
                    'request_driver' => $user->id
                ]);
                return response()->json(responseFormatter(DEFAULT_400), 403);
            }

            // Verify trip state hasn't changed (idempotency check)
            if ($trip->current_status == 'cancelled') {
                DB::rollBack();
                \Log::warning('Trip already cancelled (concurrent update detected)', ['trip_id' => $trip->id]);
                return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
            }
            if ($trip->current_status == 'completed') {
                DB::rollBack();
                \Log::warning('Trip already completed (concurrent update detected)', ['trip_id' => $trip->id]);
                return response()->json(responseFormatter(TRIP_STATUS_COMPLETED_403), 403);
            }
            if ($trip->current_status == RETURNING) {
                DB::rollBack();
                \Log::warning('Trip in returning status (locked check)', ['trip_id' => $trip->id]);
                return response()->json(responseFormatter(TRIP_STATUS_RETURNING_403), 403);
            }
            if ($trip->is_paused) {
                DB::rollBack();
                \Log::warning('Trip is paused (locked check)', ['trip_id' => $trip->id]);
                return response()->json(responseFormatter(TRIP_REQUEST_PAUSED_404), 403);
            }

            // Store original status for parcel cancellation check
            $originalStatus = $trip->current_status;

            // Store data for post-transaction processing
            $referralData = null;

            if ($status == 'completed' || $status == 'cancelled') {
                if ($status == 'cancelled') {
                    $attributes['fee']['cancelled_by'] = 'driver';
                    // Collect referral data but process AFTER transaction commit (non-blocking)
                    if ($trip->customer->referralCustomerDetails && $trip->customer->referralCustomerDetails->is_used == 0) {
                        $referralDetails = $trip->customer->referralCustomerDetails;
                        $referralData = [
                            'customer_id' => $trip->customer_id,
                            'referral_id' => $referralDetails->id,
                            'ref_by_earning_amount' => $referralDetails->ref_by_earning_amount,
                            'share_referral_user_id' => $referralDetails->shareRefferalCustomer?->id,
                            'share_referral_fcm_token' => $referralDetails->shareRefferalCustomer?->fcm_token
                        ];
                        // Mark as used immediately to prevent duplicate processing
                        $trip->customer->referralCustomerDetails()->update(['is_used' => 1]);
                    }
                }

                // Get driver's last location for drop coordinates
                $driverLastLocation = $trip->driver?->lastLocations;
                if ($driverLastLocation && $driverLastLocation->latitude && $driverLastLocation->longitude) {
                    $attributes['coordinate']['drop_coordinates'] = new Point($driverLastLocation->latitude, $driverLastLocation->longitude);
                }

                // Update driver details
                $driverDetails = $this->driverDetails->getBy(column: 'user_id', value: $user->id);
                if ($driverDetails) {
                    if ($trip->type == RIDE_REQUEST) {
                        $driverDetails->ride_count = 0;
                    } else if ($status == 'completed' || ($trip->driver_id && $status == 'cancelled' && $trip->current_status == ACCEPTED)) {
                        $driverDetails->parcel_count = max(0, $driverDetails->parcel_count - 1);
                    }
                    $driverDetails->save();
                }
            }

            $data = $this->trip->updateRelationalTable($attributes);

            DB::commit();

            \Log::info('Trip status updated successfully (transaction committed)', [
                'trip_id' => $trip->id,
                'new_status' => $status,
                'previous_status' => $originalStatus,
                'has_referral_data' => $referralData !== null
            ]);

            // Process referral rewards AFTER commit (non-blocking)
            if ($referralData && $referralData['ref_by_earning_amount'] > 0 && $referralData['share_referral_user_id']) {
                dispatch(function () use ($referralData) {
                    try {
                        $shareReferralUser = \Modules\UserManagement\Entities\User::find($referralData['share_referral_user_id']);
                        if ($shareReferralUser) {
                            // Process transaction
                            app(TripRequestController::class)->customerReferralEarningTransaction(
                                $shareReferralUser,
                                $referralData['ref_by_earning_amount']
                            );

                            // Send notification
                            if ($referralData['share_referral_fcm_token']) {
                                $push = getNotification('referral_reward_received');
                                SendPushNotificationJob::dispatch([
                                    'title' => translate($push['title']),
                                    'description' => translate(textVariableDataFormat(
                                        value: $push['description'],
                                        referralRewardAmount: getCurrencyFormat($referralData['ref_by_earning_amount'])
                                    )),
                                    'status' => $push['status'],
                                    'ride_request_id' => $shareReferralUser->id,
                                    'action' => 'referral_reward_received',
                                    'user' => [[
                                        'fcm_token' => $referralData['share_referral_fcm_token'],
                                        'user_id' => $shareReferralUser->id,
                                    ]]
                                ])->onQueue('notifications');
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Referral processing failed (non-critical)', [
                            'error' => $e->getMessage(),
                            'referral_data' => $referralData
                        ]);
                    }
                })->afterResponse();
            }

            // Move level checks to background (non-critical)
            $customerId = $trip->customer_id;
            $driverId = $user->id;
            dispatch(function () use ($customerId, $driverId, $status) {
                try {
                    if ($status == 'cancelled' || $status == 'completed') {
                        $customer = \Modules\UserManagement\Entities\User::find($customerId);
                        $driver = \Modules\UserManagement\Entities\User::find($driverId);
                        if ($customer) {
                            app()->call([\Modules\UserManagement\Lib\LevelUpdateCheckerTrait::class, 'customerLevelUpdateChecker'], ['user' => $customer]);
                        }
                        if ($driver) {
                            app()->call([\Modules\UserManagement\Lib\LevelUpdateCheckerTrait::class, 'driverLevelUpdateChecker'], ['user' => $driver]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Level update check failed', ['error' => $e->getMessage()]);
                }
            })->afterResponse();

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            \Log::error('Database error during trip status update', [
                'trip_id' => $tripRequestId,
                'status' => $status,
                'error' => $e->getMessage(),
                'sql_state' => $e->errorInfo[0] ?? null,
                'error_code' => $e->errorInfo[1] ?? null
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: ['Database error. Please try again.']), 500);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update trip status', [
                'trip_id' => $tripRequestId,
                'status' => $status,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: ['Failed to update trip: ' . $e->getMessage()]), 500);
        }

        // Handle parcel cancellation returning (use original status stored before transaction)
        if ($trip->driver_id && $status == 'cancelled' && $originalStatus == ONGOING && $trip->type == PARCEL) {
            try {
                $env = env('APP_MODE');
                $otp = $env != "live" ? '0000' : rand(1000, 9999);
                $trip->otp = $otp;
                $trip->return_fee = 0;
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
            } catch (\Exception $e) {
                \Log::error('Failed to process parcel cancellation', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Queue push notification
        if ($trip->customer?->fcm_token) {
            $action = ($status == 'cancelled' && $trip->type == PARCEL) ? 'parcel_cancelled' : 'ride_' . $status;
            $push = getNotification('ride_' . $status);
            SendPushNotificationJob::dispatch([
                'title' => translate($push['title']),
                'description' => translate(textVariableDataFormat(value: $push['description'])),
                'status' => $push['status'],
                'ride_request_id' => $tripRequestId,
                'type' => $trip->type,
                'action' => $action,
                'user' => [[
                    'fcm_token' => $trip->customer->fcm_token,
                    'user_id' => $trip->customer->id,
                ]]
            ])->onQueue('notifications');
        }
        
        // Dispatch Pusher events after response
        $tripForEvent = $trip;
        dispatch(function () use ($tripForEvent, $status) {
            try {
                if ($status == "completed") {
                    checkPusherConnection(DriverTripCompletedEvent::broadcast($tripForEvent));
                } elseif ($status == "cancelled") {
                    checkPusherConnection(DriverTripCancelledEvent::broadcast($tripForEvent));
                }
            } catch (\Exception $exception) {
                \Log::warning('Pusher event failed', ['error' => $exception->getMessage()]);
            }
        })->afterResponse();

        // Publish to Redis for real-time UI updates (do this early, before heavy DB operations)
        try {
            $realtimePublisher = app(RealtimeEventPublisher::class);
            if ($status == 'cancelled') {
                $realtimePublisher->publishRideCancelled($trip, 'driver');
            } elseif ($status == 'completed') {
                $realtimePublisher->publishRideCompleted($trip);
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to publish ride event to Redis', ['error' => $e->getMessage()]);
        }

        // Load only essential relations for response (optimized)
        $trip = \Modules\TripManagement\Entities\TripRequest::with([
            'driver:id,first_name,last_name,phone,profile_image',
            'vehicleCategory:id,name,type',
            'tripStatus',
            'fee',
            'time'
        ])->find($tripRequestId);
        
        $resource = TripRequestResource::make($trip);
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $resource));
    }


    /**
     * Trip otp submit - ULTRA OPTIMIZED for speed
     *
     * CRITICAL PERFORMANCE FIX:
     * - Validate with MINIMAL query (only 5 fields)
     * - Update status immediately
     * - Return SUCCESS instantly (< 50ms target)
     * - ALL notifications/events in background queue
     * - Driver app fetches full trip data separately
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function matchOtp(Request $request): JsonResponse
    {
        $startTime = microtime(true);

        $tripRequestId = $request['trip_request_id'];
        $otp = $request['otp'];
        $driverId = auth('api')->id();

        // Quick validation
        if (!$tripRequestId) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: ['trip_request_id required']), 403);
        }

        // ULTRA-FAST: Load ONLY what we need for validation (5 fields, no relations)
        $trip = \Modules\TripManagement\Entities\TripRequest::where('id', $tripRequestId)
            ->select(['id', 'driver_id', 'otp', 'current_status', 'type', 'customer_id'])
            ->first();

        $queryTime = round((microtime(true) - $startTime) * 1000, 2);

        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != $driverId) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if ($otp && $trip->otp !== $otp) {
            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
        }

        $updateStart = microtime(true);

        // ATOMIC: Update both tables in one go
        DB::transaction(function () use ($tripRequestId) {
            \Modules\TripManagement\Entities\TripRequest::where('id', $tripRequestId)
                ->update(['current_status' => ONGOING]);

            \Modules\TripManagement\Entities\TripStatus::where('trip_request_id', $tripRequestId)
                ->update(['ongoing' => now()]);
        }, 3);

        $updateTime = round((microtime(true) - $updateStart) * 1000, 2);

        // Dispatch ALL heavy work to background job (notifications, broadcasts, etc.)
        dispatch(new \App\Jobs\ProcessTripOtpJob($tripRequestId, $driverId))
            ->onQueue('high-priority');

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        \Log::info('✅ OTP matched - INSTANT RESPONSE', [
            'trip_id' => $tripRequestId,
            'driver_id' => $driverId,
            'total_time_ms' => $totalTime,
            'query_time_ms' => $queryTime,
            'update_time_ms' => $updateTime,
            'performance' => $totalTime < 50 ? '⚡⚡⚡⚡ BLAZING' : ($totalTime < 100 ? '⚡⚡⚡ EXCELLENT' : ($totalTime < 200 ? '⚡⚡ GOOD' : '⚠️ SLOW'))
        ]);

        // INSTANT RESPONSE: Just confirm success, don't load heavy trip data
        // Driver app will call /ride/details to get full trip info
        return response()->json(responseFormatter(DEFAULT_STORE_200, content: [
            'trip_id' => $tripRequestId,
            'status' => ONGOING,
            'message' => 'OTP verified. Trip started.'
        ]));
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
    public function pendingRideList1(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        if (empty($request->header('zoneId'))) {

            return response()->json(responseFormatter(ZONE_404));
        }
        $user = auth('api')->user();
        if ($user->driverDetails->is_online != 1) {

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
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }
        if (!$vehicle->is_active && $vehicle->vehicle_request_status !== APPROVED) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_APPROVED_OR_ACTIVE_404, content: []), 403);
        }
        $maxParcelRequestAcceptLimit = businessConfig(key: 'maximum_parcel_request_accept_limit', settingsType: DRIVER_SETTINGS);
        $maxParcelRequestAcceptLimitStatus = (bool)($maxParcelRequestAcceptLimit?->value['status'] ?? false);
        $maxParcelRequestAcceptLimitCount = (int)($maxParcelRequestAcceptLimit?->value['limit'] ?? 0);
        $search_radius = (double)get_cache('search_radius') ?? 5;
        $location = $this->lastLocation->getBy(column: 'user_id', value: $user->id);
        if (!$location) {

            return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
        }
        $pendingTrips = $this->trip->getPendingRides(attributes: [
            'ride_count' => $user?->driverDetails->ride_count ?? 0,
            'parcel_count' => $user?->driverDetails->parcel_count ?? 0,
            'parcel_follow_status' => $maxParcelRequestAcceptLimitStatus,
            'max_parcel_request_accept_limit' => $maxParcelRequestAcceptLimitCount,
            'vehicle_category_id' => $vehicle->category_id,
            'driver_locations' => $location,
            'service' => $user?->driverDetails?->service ?? null,
            'parcel_weight_capacity' => $vehicle->parcel_weight_capacity ?? null,
            'distance' => $search_radius * 1000,
            'zone_id' => $request->header('zoneId'),
            'relations' => ['driver.driverDetails', 'customer', 'ignoredRequests', 'time', 'fee', 'fare_biddings', 'parcel', 'parcelRefund'],
            'withAvgRelation' => 'customerReceivedReviews',
            'withAvgColumn' => 'rating',
            'limit' => $request['limit'],
            'offset' => $request['offset']
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
     * Get pending ride list for driver
     * 
     * PERFORMANCE OPTIMIZED:
     * - Reduced logging (use debug level for production)
     * - Removed redundant pending count query
     * - Streamlined vehicle category processing
     */
    public function pendingRideList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
            'offset' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $zoneId = $request->header('zoneId');
        if (empty($zoneId)) {
            return response()->json(responseFormatter(ZONE_404));
        }

        $user = auth('api')->user();
        
        // Quick validation checks
        if (!$user->driverDetails || $user->driverDetails->is_online != 1) {
            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        // Get vehicle with single optimized query (prioritize active/approved, fallback to any)
        $vehicle = Vehicle::query()
            ->where('driver_id', $user->id)
            ->orderByRaw('CASE WHEN is_active = 1 OR vehicle_request_status = ? THEN 0 ELSE 1 END', [APPROVED])
            ->orderBy('updated_at', 'desc')
            ->first();

        if (is_null($vehicle)) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }

        if (!$vehicle->is_active && $vehicle->vehicle_request_status !== APPROVED) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_APPROVED_OR_ACTIVE_404, content: []), 403);
        }

        // Get cached config values
        $maxParcelRequestAcceptLimit = businessConfig(key: 'maximum_parcel_request_accept_limit', settingsType: DRIVER_SETTINGS);
        $maxParcelRequestAcceptLimitStatus = (bool)($maxParcelRequestAcceptLimit?->value['status'] ?? false);
        $maxParcelRequestAcceptLimitCount = (int)($maxParcelRequestAcceptLimit?->value['limit'] ?? 0);
        $search_radius = (double)get_cache('search_radius') ?? 5;

        // Get driver location
        $location = $this->lastLocation->getBy(column: 'user_id', value: $user->id);
        if (!$location) {
            return response()->json(responseFormatter(constant: DEFAULT_200, content: ''));
        }

        // Process vehicle category IDs efficiently
        $vehicleCategoryIds = $vehicle->category_id ?? [];
        if (is_string($vehicleCategoryIds)) {
            $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
        }
        if (!is_array($vehicleCategoryIds)) {
            $vehicleCategoryIds = [$vehicleCategoryIds];
        }

        if (empty($vehicleCategoryIds)) {
            return response()->json(responseFormatter(constant: VEHICLE_NOT_REGISTERED_404, content: []), 403);
        }

        // Fetch pending trips with optimized query
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
            'relations' => ['customer', 'time', 'fee', 'fare_biddings', 'parcel'],
            'withAvgRelation' => 'customerReceivedReviews',
            'withAvgColumn' => 'rating',
            'limit' => $request['limit'],
            'offset' => $request['offset']
        ]);

        // Debug logging only when enabled
        if (config('performance.logging.debug_hot_paths', false)) {
            \Log::debug('pendingRideList', [
                'zone_id' => $zoneId,
                'trips_found' => $pendingTrips->count()
            ]);
        }

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
        if ($trip->otp !== $request['otp']) {

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
