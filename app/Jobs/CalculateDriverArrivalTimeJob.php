<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;
use App\Events\DriverTripAcceptedEvent;
use Modules\TripManagement\Entities\TripStatus;
use Modules\Gateways\Traits\SmsGatewayForMessage;

class CalculateDriverArrivalTimeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SmsGatewayForMessage;

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
        $trip = TripRequest::with(['coordinate', 'customer'])->find($this->tripId);
        $driver = User::with('lastLocations')->find($this->driverId);

        if (!$trip || !$driver || !$driver->lastLocations) {
            Log::warning("CalculateDriverArrivalTimeJob: Missing trip or driver location data", ['trip_id' => $this->tripId]);
            return;
        }

        // Calculate Arrival Time using Helper function (External API Call)
        $driverArrivalTime = getRoutes(
            originCoordinates: [
                $trip->coordinate->pickup_coordinates->latitude,
                $trip->coordinate->pickup_coordinates->longitude
            ],
            destinationCoordinates: [
                $driver->lastLocations->latitude,
                $driver->lastLocations->longitude
            ],
        );

        $attributes = [];

        // Check if getRoutes returned a valid array response
        if (is_array($driverArrivalTime) && !empty($driverArrivalTime)) {
             $attributes['driver_arrival_time'] = (double)($driverArrivalTime[0]['duration']) / 60;
        } else {
             Log::warning("CalculateDriverArrivalTimeJob: Route calculation failed", ['trip_id' => $this->tripId, 'response' => $driverArrivalTime]);
        }
        
        // Update trip if we have new data
        if (!empty($attributes)) {
            $trip->update($attributes);
        }

        // --- Notifications to Customer ---

        $push = getNotification('driver_is_on_the_way');
        sendDeviceNotification(
            fcm_token: $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'])),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'driver_assigned',
            user_id: $trip->customer->id
        );

        // NOTE: OTP notification is now sent immediately in the controller (requestAction method)
        // to avoid delays from route calculation API calls. This job only handles driver arrival time.

        // Broadcast to Customer that driver accepted
        try {
            checkPusherConnection(DriverTripAcceptedEvent::broadcast($trip));
        } catch (\Exception $exception) {
             Log::error("CalculateDriverArrivalTimeJob: Pusher error", ['error' => $exception->getMessage()]);
        }
    }
}
