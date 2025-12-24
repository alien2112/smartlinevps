<?php

namespace App\Jobs;

use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;
use Illuminate\Support\Facades\Log;

class ProcessRideStatusUpdateNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $tripId;
    protected $status;
    protected $referralData;

    /**
     * Create a new job instance.
     */
    public function __construct($tripId, $status, $referralData = null)
    {
        $this->tripId = $tripId;
        $this->status = $status;
        $this->referralData = $referralData;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $trip = TripRequest::with(['customer', 'driver'])->find($this->tripId);

        if (!$trip) {
            Log::warning("ProcessRideStatusUpdateNotificationsJob: Trip not found", ['trip_id' => $this->tripId]);
            return;
        }

        // 1. Handle Referral Notification if applicable
        if ($this->referralData && isset($this->referralData['fcm_token'])) {
            $push = getNotification('referral_reward_received');
            sendDeviceNotification(
                fcm_token: $this->referralData['fcm_token'],
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'], referralRewardAmount: $this->referralData['amount'])),
                status: $push['status'],
                ride_request_id: $this->referralData['user_id'],
                action: 'referral_reward_received',
                user_id: $this->referralData['user_id']
            );
        }

        // 2. Status wise notification
        if ($this->status == 'cancelled' && $trip->type == PARCEL) {
            $push = getNotification('ride_cancelled');
            sendDeviceNotification(
                fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'parcel_cancelled',
                user_id: $trip->customer->id
            );
        } else {
            $action = 'ride_' . $this->status;
            $push = getNotification($action);
            if ($push && $trip->customer && $trip->customer->fcm_token) {
                sendDeviceNotification(
                    fcm_token: $trip->customer->fcm_token,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'])),
                    status: $push['status'],
                    ride_request_id: $trip->id,
                    type: $trip->type,
                    action: $action,
                    user_id: $trip->customer->id
                );
            }
        }

        // 3. Broadcast Pusher Events
        if ($this->status == "completed") {
            try {
                checkPusherConnection(DriverTripCompletedEvent::broadcast($trip));
            } catch (\Exception $exception) {}
        }
        if ($this->status == "cancelled") {
            try {
                checkPusherConnection(DriverTripCancelledEvent::broadcast($trip));
            } catch (\Exception $exception) {}
        }
    }
}