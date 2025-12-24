<?php

namespace App\Jobs;

use App\Events\CustomerTripCancelledEvent;
use App\Events\CustomerTripCancelledAfterOngoingEvent;
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

class ProcessCustomerCancelNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, LevelUpdateCheckerTrait;

    public $tries = 3;
    public $backoff = 5;

    protected $tripId;
    protected $status;
    protected $previousStatus;
    protected $referralData;
    protected $customerId;
    protected $driverId;

    public function __construct(
        $tripId,
        $status,
        $previousStatus,
        $referralData = null,
        $customerId = null,
        $driverId = null
    ) {
        $this->tripId = $tripId;
        $this->status = $status;
        $this->previousStatus = $previousStatus;
        $this->referralData = $referralData;
        $this->customerId = $customerId;
        $this->driverId = $driverId;

        $this->onQueue('high');
    }

    public function handle(): void
    {
        $trip = TripRequest::with(['customer', 'driver'])->find($this->tripId);

        if (!$trip) {
            Log::warning("ProcessCustomerCancelNotificationsJob: Trip not found", ['trip_id' => $this->tripId]);
            return;
        }

        // 1. Level update checks (heavy DB queries - moved from controller)
        if ($this->status == 'cancelled' || $this->status == 'completed') {
            if ($this->driverId && $this->previousStatus == ONGOING) {
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
        }

        // 2. Handle Referral Notification
        if ($this->referralData && isset($this->referralData['fcm_token'])) {
            if (isset($this->referralData['language'])) {
                App::setLocale($this->referralData['language']);
            }
            
            $push = getNotification('referral_reward_received');
            if ($push) {
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
        }

        // 3. Notify driver about cancellation
        if ($this->status == 'cancelled' && $trip->driver && $trip->driver->fcm_token) {
            $driverLanguage = $trip->driver->current_language_key ?? 'en';
            App::setLocale($driverLanguage);
            
            $action = $trip->type == PARCEL ? 'parcel_cancelled' : 'ride_cancelled';
            $push = getNotification('ride_cancelled');
            
            if ($push) {
                sendDeviceNotification(
                    fcm_token: $trip->driver->fcm_token,
                    title: translate($push['title']),
                    description: translate(textVariableDataFormat(value: $push['description'])),
                    status: $push['status'],
                    ride_request_id: $trip->id,
                    type: $trip->type,
                    action: $action,
                    user_id: $trip->driver->id
                );
            }
        }

        // 4. Broadcast Pusher/Reverb Events
        if ($this->status == 'cancelled') {
            try {
                if ($this->previousStatus == ONGOING) {
                    checkPusherConnection(CustomerTripCancelledAfterOngoingEvent::broadcast($trip));
                } elseif ($trip->driver) {
                    checkPusherConnection(CustomerTripCancelledEvent::broadcast($trip->driver, $trip));
                }
            } catch (\Exception $exception) {
                Log::debug('Pusher broadcast failed for customer cancel event', ['trip_id' => $this->tripId]);
            }
        }

        Log::info('ProcessCustomerCancelNotificationsJob completed', [
            'trip_id' => $this->tripId,
            'status' => $this->status,
            'previous_status' => $this->previousStatus
        ]);
    }
}
