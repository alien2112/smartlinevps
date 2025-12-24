<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Modules\TripManagement\Entities\TripRequest;
use App\Events\DriverTripStartedEvent;
use Illuminate\Support\Facades\Log;

class ProcessTripOtpJob implements ShouldQueue
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
        $trip = TripRequest::with(['customer', 'coordinate'])->find($this->tripId);

        if (!$trip) {
            Log::warning("ProcessTripOtpJob: Trip not found", ['trip_id' => $this->tripId]);
            return;
        }

        // 1. Send FCM Notification to Customer (in customer's preferred language)
        if ($trip->customer && $trip->customer->fcm_token) {
            // Set locale to customer's preferred language
            $customerLanguage = $trip->customer->current_language_key ?? 'en';
            App::setLocale($customerLanguage);
            
            $push = getNotification('trip_started');
            sendDeviceNotification(
                fcm_token: $trip->customer->fcm_token,
                title: translate($push['title']),
                description: translate(textVariableDataFormat(value: $push['description'])),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'otp_matched',
                user_id: $trip->customer->id
            );
        }

        // 2. Broadcast Pusher/Reverb Event
        try {
            checkPusherConnection(DriverTripStartedEvent::broadcast($trip));
        } catch (\Exception $exception) {
            Log::error("ProcessTripOtpJob: Broadcasting failed", [
                'trip_id' => $trip->id,
                'error' => $exception->getMessage()
            ]);
        }
    }
}
