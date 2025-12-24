<?php

namespace App\Jobs;

use App\Events\CustomerTripPaymentSuccessfulEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;
use Illuminate\Support\Facades\Log;

class ProcessPaymentHookNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tripId;

    /**
     * Create a new job instance.
     */
    public function __construct($tripId)
    {
        $this->tripId = $tripId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $trip = TripRequest::with(['customer', 'driver'])->find($this->tripId);

        if (!$trip) {
            Log::warning("ProcessPaymentHookNotificationsJob: Trip not found", ['trip_id' => $this->tripId]);
            return;
        }

        // 1. Success notification
        $push = getNotification('payment_successful');
        if ($trip->driver && $trip->driver->fcm_token) {
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'], paidAmount: $trip->paid_fare, methodName: $trip->payment_method)),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'payment_successful',
                user_id: $trip->driver->id
            );
        }

        // 2. Tips notification
        if ($trip->tips > 0 && $trip->driver && $trip->driver->fcm_token) {
            $pushTips = getNotification('tips_from_customer');
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($pushTips['title']),
                description: translate(textVariableDataFormat(value: $pushTips['description'], tipsAmount: $trip->tips)),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'tips_from_customer',
                user_id: $trip->driver->id
            );
        }

        // 3. Broadcast Pusher Event
        try {
            checkPusherConnection(CustomerTripPaymentSuccessfulEvent::broadcast($trip));
        } catch (\Exception $exception) {}
    }
}
