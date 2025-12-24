<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;
use App\Events\AnotherDriverTripAcceptedEvent;
use Modules\TripManagement\Entities\TempTripNotification;
use Modules\TripManagement\Entities\TripRequest;

class NotifyOtherDriversJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tripId;
    public $driverId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($tripId, $driverId)
    {
        $this->tripId = $tripId;
        $this->driverId = $driverId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $trip = TripRequest::find($this->tripId);
        if (!$trip) {
            return;
        }

        // Fetch temp notifications excluding the current driver
        $tempNotifications = TempTripNotification::with('user')
            ->where('trip_request_id', $this->tripId)
            ->where('user_id', '!=', $this->driverId)
            ->get();

        if ($tempNotifications->isEmpty()) {
            return;
        }

        // Prepare Push Notification Data
        $push = getNotification('ride_is_started');
        $notification = [
            'title' => translate($push['title']),
            'description' => translate($push['description']),
            'status' => $push['status'],
            'ride_request_id' => $trip->id,
            'type' => $trip->type,
            'action' => 'ride_started'
        ];
        
        // Dispatch Push Notification Job
        dispatch(new SendPushNotificationJob($notification, $tempNotifications));

        // Broadcast Event to other drivers (Pusher/Socket)
        foreach ($tempNotifications as $tempNotification) {
            try {
                checkPusherConnection(AnotherDriverTripAcceptedEvent::broadcast($tempNotification->user, $trip));
            } catch (Exception $exception) {
                // Log error if needed, but don't fail the job
            }
        }

        // Cleanup: Delete notifications for this trip
        TempTripNotification::where('trip_request_id', $this->tripId)->delete();
        
        // Cleanup: Delete notifications for the winning driver (they are busy now)
        TempTripNotification::where('user_id', $this->driverId)->delete();
    }
}