<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use App\Events\AnotherDriverTripAcceptedEvent;
use App\Events\CustomerTripCancelledEvent;
use App\Events\DriverTripAcceptedEvent;
use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use App\Events\DriverTripStartedEvent;
use App\Jobs\SendPushNotificationJob;
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
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'action' => 'required|in:accepted,rejected',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }

        $user = auth('api')->user();
        $trip = $this->trip->getBy('id', $request['trip_request_id']);
        $user_status = $user->driverDetails->availability_status;

        if ($user_status == 'unavailable' || !$user->driverDetails->is_online) {

            return response()->json(responseFormatter(constant: DRIVER_UNAVAILABLE_403), 403);
        }

        if ($trip->current_status == ACCEPTED && $trip->driver_id != $user->id) {

            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403), 403);
        }
        if ($trip->current_status == ACCEPTED && $trip->driver_id == $user->id) {

            return response()->json(responseFormatter(DEFAULT_UPDATE_200));
        }

        if (!$trip) {
            return response()->json(responseFormatter(constant: TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id && $trip->driver_id != $user->id) {

            return response()->json(responseFormatter(TRIP_REQUEST_DRIVER_403), 403);
        }


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


        $env = env('APP_MODE');
        $otp = $env != "live" ? '0000' : rand(1000, 9999);

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
            'driver_id' => $user->id,
            'otp' => $otp,
            'vehicle_id' => $user->vehicle->id,
            'vehicle_category_id' => $assignedVehicleCategoryId,
            'current_status' => ACCEPTED,
            'trip_status' => ACCEPTED,
        ];
        if ($bid_on_fare) {
            $bidding = $this->bidding->getBy(column: 'trip_request_id', value: $request['trip_request_id'],
                attributes: ['additionalColumn' => 'driver_id', 'additionalValue' => $user->id, 'additionalColumn2' => 'is_ignored', 'additionalValue2' => 0],
            );
            if ($bidding) {
                return response()->json(responseFormatter(constant: BIDDING_SUBMITTED_403), 403);
            }
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
        Cache::put($trip->id, ACCEPTED, now()->addHour());
        $driverArrivalTime = getRoutes(
            originCoordinates: [
                $trip->coordinate->pickup_coordinates->latitude,
                $trip->coordinate->pickup_coordinates->longitude
            ],
            destinationCoordinates: [
                $user->lastLocations->latitude,
                $user->lastLocations->longitude
            ],
        );

        // Check if getRoutes returned an error (integer status code) instead of array
        if (!is_array($driverArrivalTime)) {
            return response()->json(responseFormatter(ROUTE_NOT_FOUND_404, 'Unable to calculate driver arrival time. API returned status: ' . $driverArrivalTime), 404);
        }

        $attributes['driver_arrival_time'] = (double)($driverArrivalTime[0]['duration']) / 60;

        //notify other driver about ride started

        $data = $this->tempNotification->get([
            'relations' => 'user',
            'trip_request_id' => $request['trip_request_id'],
            'whereNotInColumn' => 'user_id',
            'whereNotInValue' => [auth('api')->id()]
        ]);

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
            foreach ($data as $tempNotification) {
                try {
                    checkPusherConnection(AnotherDriverTripAcceptedEvent::broadcast($tempNotification->user, $trip));
                } catch (Exception $exception) {

                }
            }
            $this->tempNotification->delete($trip->id);
            TempTripNotification::where('user_id', $user->id)->delete();
        }
        //Trip update
        $trip = $this->trip->update(attributes: $attributes, id: $request['trip_request_id']);
        if ($trip->type == PARCEL && $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number && businessConfig('parcel_tracking_message')?->value && businessConfig('parcel_tracking_status')?->value && businessConfig('parcel_tracking_status')?->value == 1) {
            $parcelTemplateMessage = businessConfig('parcel_tracking_message')?->value;
            $smsTemplate = smsTemplateDataFormat(
                value: $parcelTemplateMessage,
                customerName: $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->name,
                parcelId: $trip->ref_id,
                trackingLink: route('track-parcel', $trip->ref_id)
            );
            try {
                self::send($trip->parcelUserInfo?->firstWhere('user_type', RECEIVER)?->contact_number, $smsTemplate);
            } catch (\Exception $exception) {

            }
        }

        $trip->tripStatus()->update([
            'accepted' => now()
        ]);
        //deleting exiting rejected driver request for this trip
        $this->rejectedRequest->destroyData([
            'column' => 'trip_request_id',
            'value' => $trip->id,
        ]);

        $push = getNotification('driver_is_on_the_way');
        sendDeviceNotification(fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $request['trip_request_id'],
            type: $trip->type,
            action: 'driver_assigned',
            user_id: $trip->customer->id
        );

        $otpRequired = (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
        if ($otpRequired && $trip->type === RIDE_REQUEST && $trip->otp) {
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
                    ride_request_id: $request['trip_request_id'],
                    type: $trip->type,
                    action: 'trip_otp',
                    user_id: $trip->customer->id
                );
            }
        }
        try {
            checkPusherConnection(DriverTripAcceptedEvent::broadcast($trip));
        } catch (Exception $exception) {

        }
        return response()->json(responseFormatter(constant: DEFAULT_UPDATE_200));
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
        if ($trip->current_status == 'cancelled') {
            return response()->json(responseFormatter(TRIP_STATUS_CANCELLED_403), 403);
        }
        if ($trip->current_status == 'completed') {
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
            'trip_cancellation_reason' => $request['cancel_reason'] ?? null
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


        if ($trip->driver_id && $request->status == 'cancelled' && $trip->current_status == ONGOING && $trip->type == PARCEL) {
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
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $data));
    }


    /**
     * Trip otp submit.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function matchOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'otp' => Rule::requiredIf(function () {
                return (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;
            }), 'min:4|max:4',
        ]);

        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $trip = $this->trip->getBy(column: 'id', value: $request['trip_request_id'], attributes: ['relations' => 'customer', 'coordinate']);


        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->driver_id != auth('api')->id()) {
            return response()->json(responseFormatter(DEFAULT_404), 403);
        }
        if (array_key_exists('otp', $request->all()) && $request['otp'] && $trip->otp !== $request['otp']) {

            return response()->json(responseFormatter(OTP_MISMATCH_404), 403);
        }
        DB::beginTransaction();
        $attributes = [
            'column' => 'id',
            'current_status' => ONGOING,
            'trip_status' => now()
        ];

        $trip = $this->trip->update(attributes: $attributes, id: $request['trip_request_id']);
        $trip->tripStatus()->update(['ongoing' => now()]);
        if ($trip->customer->fcm_token) {
            $push = getNotification('trip_started');
            sendDeviceNotification(
                fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $request['trip_request_id'],
                type: $trip['type'],
                action: 'otp_matched',
                user_id: $trip->customer->id
            );
        }
        try {
            checkPusherConnection(DriverTripStartedEvent::broadcast($trip));
        } catch (Exception $exception) {

        }
        DB::commit();
        return response()->json(responseFormatter(DEFAULT_STORE_200));
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

        $vehicleCategoryIds = $vehicle->category_id ?? [];
        if (is_string($vehicleCategoryIds)) {
            $vehicleCategoryIds = json_decode($vehicleCategoryIds, true) ?? [];
        }

        $pendingTrips = $this->trip->getPendingRides(attributes: [
            'ride_count' => $user?->driverDetails->ride_count ?? 0,
            'parcel_count' => $user?->driverDetails->parcel_count ?? 0,
            'parcel_follow_status' => $maxParcelRequestAcceptLimitStatus,
            'max_parcel_request_accept_limit' => $maxParcelRequestAcceptLimitCount,
            'vehicle_category_id' => $vehicleCategoryIds,
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
