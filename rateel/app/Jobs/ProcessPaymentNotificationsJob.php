<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;
use App\Events\CustomerTripPaymentSuccessfulEvent;
use App\Events\DriverPaymentReceivedEvent;
use Illuminate\Support\Facades\Log;

class ProcessPaymentNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tripId;
    protected $paymentMethod;
    protected $authUserId;
    protected $authUserType;

    /**
     * Create a new job instance.
     */
    public function __construct($tripId, $paymentMethod, $authUserId, $authUserType)
    {
        $this->tripId = $tripId;
        $this->paymentMethod = $paymentMethod;
        $this->authUserId = $authUserId;
        $this->authUserType = $authUserType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $trip = TripRequest::with(['customer', 'driver'])->find($this->tripId);

        if (!$trip) {
            Log::warning("ProcessPaymentNotificationsJob: Trip not found", ['trip_id' => $this->tripId]);
            return;
        }

        // Determine who to notify via FCM
        $fcmToken = ($this->authUserType == 'customer') ? $trip->driver?->fcm_token : $trip->customer?->fcm_token;
        
        if ($fcmToken) {
            $push = getNotification('payment_successful');
            $method = ($this->paymentMethod == 'wallet') ? '_with_wallet_balance' : '_by_cash';
            
            sendDeviceNotification(
                fcm_token: $fcmToken,
                title: translate($push['title']),
                description: translate(textVariableDataFormat($push['description'], paidAmount: $trip->paid_fare, methodName: $method)),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'payment_successful',
                user_id: ($this->authUserType == 'customer') ? $trip->driver?->id : $trip->customer?->id
            );
        }

        // Tips notification if applicable
        if ($trip->tips > 0 && $trip->driver && $trip->driver->fcm_token) {
            $pushTips = getNotification("tips_from_customer");
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($pushTips['title']),
                description: translate(textVariableDataFormat(value: $pushTips['description'], tipsAmount: $trip->tips)),
                status: 1,
                ride_request_id: $trip->id,
                action: 'got_tipped',
                user_id: $trip->driver->id,
            );
        }

        // Broadcast Pusher Events
        try {
            checkPusherConnection(DriverPaymentReceivedEvent::broadcast($trip));
        } catch (\Exception $exception) {
            Log::error("ProcessPaymentNotificationsJob: Driver broadcast failed", ['error' => $exception->getMessage()]);
        }

        try {
            checkPusherConnection(CustomerTripPaymentSuccessfulEvent::broadcast($trip));
        } catch (\Exception $exception) {
            Log::error("ProcessPaymentNotificationsJob: Customer broadcast failed", ['error' => $exception->getMessage()]);
        }
    }
}