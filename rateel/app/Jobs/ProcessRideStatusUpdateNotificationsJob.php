<?php

namespace App\Jobs;

use App\Events\DriverTripCancelledEvent;
use App\Events\DriverTripCompletedEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;
use Illuminate\Support\Facades\Log;

class ProcessRideStatusUpdateNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LevelUpdateCheckerTrait;

    public $tries = 3;
    public $backoff = 5;

    protected $tripId;
    protected $status;
    protected $referralData;
    protected $customerId;
    protected $driverId;

    /**
     * Create a new job instance.
     */
    public function __construct($tripId, $status, $referralData = null, $customerId = null, $driverId = null)
    {
        $this->tripId = $tripId;
        $this->status = $status;
        $this->referralData = $referralData;
        $this->customerId = $customerId;
        $this->driverId = $driverId;
        
        $this->onQueue('high');
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

        // 1. Level update checks (moved from controller - these have heavy DB queries)
        if ($this->status == 'cancelled' || $this->status == 'completed') {
            try {
                $customer = $trip->customer ?? ($this->customerId ? User::find($this->customerId) : null);
                $driver = $trip->driver ?? ($this->driverId ? User::find($this->driverId) : null);
                
                if ($customer) {
                    $this->customerLevelUpdateChecker($customer);
                }
                if ($driver) {
                    $this->driverLevelUpdateChecker($driver);
                }
            } catch (\Exception $e) {
                Log::error('Level update check failed', [
                    'trip_id' => $this->tripId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // 2. Handle Referral Notification if applicable
        if ($this->referralData && isset($this->referralData['fcm_token'])) {
            if (isset($this->referralData['language'])) {
                App::setLocale($this->referralData['language']);
            }
            
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

        // 3. Status wise notification (set locale for customer)
        $customerLanguage = $trip->customer?->current_language_key ?? 'en';
        App::setLocale($customerLanguage);
        
        if ($this->status == 'cancelled' && $trip->type == PARCEL) {
            $push = getNotification('ride_cancelled');
            if ($push && $trip->customer?->fcm_token) {
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
            }
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

        // 4. Broadcast Pusher/Reverb Events (legacy support)
        if ($this->status == "completed") {
            try {
                checkPusherConnection(DriverTripCompletedEvent::broadcast($trip));
            } catch (\Exception $exception) {
                Log::debug('Pusher broadcast failed for completed event', ['trip_id' => $this->tripId]);
            }
        }
        if ($this->status == "cancelled") {
            try {
                checkPusherConnection(DriverTripCancelledEvent::broadcast($trip));
            } catch (\Exception $exception) {
                Log::debug('Pusher broadcast failed for cancelled event', ['trip_id' => $this->tripId]);
            }
        }

        Log::info('ProcessRideStatusUpdateNotificationsJob completed', [
            'trip_id' => $this->tripId,
            'status' => $this->status
        ]);
    }
}