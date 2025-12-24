<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use App\Services\RealtimeEventPublisher;
use App\Events\DriverTripAcceptedEvent;

/**
 * ⚙️ LAYER 2: ASYNC POST-PROCESSING ⚙️
 *
 * This job handles ALL the heavy work after trip acceptance:
 * - OTP generation and sending
 * - Push notifications
 * - SMS notifications
 * - Socket/Pusher broadcasts
 * - Redis pub/sub for real-time updates
 * - Bidding cleanup
 * - Route calculation
 * - Driver availability updates
 *
 * Runs asynchronously - does NOT block the HTTP response
 */
class ProcessTripAcceptanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SmsGatewayForMessage;

    public $tripId;
    public $driverId;
    public $additionalData;

    /**
     * Job configuration
     */
    public $tries = 3; // Retry up to 3 times on failure
    public $timeout = 30; // 30 second timeout
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tripId, string $driverId, array $additionalData = [])
    {
        $this->tripId = $tripId;
        $this->driverId = $driverId;
        $this->additionalData = $additionalData;
        $this->onQueue('high-priority'); // Use high-priority queue for trip acceptance
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('⚙️ Processing trip acceptance (async)', [
            'trip_id' => $this->tripId,
            'driver_id' => $this->driverId,
        ]);

        // Load trip with required relations
        $trip = TripRequest::with([
            'customer',
            'driver',
            'vehicleCategory',
            'parcelUserInfo',
            'fee'
        ])->find($this->tripId);

        if (!$trip) {
            Log::error('Trip not found in acceptance job', [
                'trip_id' => $this->tripId
            ]);
            return;
        }

        // Verify driver ownership (safety check)
        if ($trip->driver_id !== $this->driverId) {
            Log::warning('Driver mismatch in acceptance job', [
                'trip_id' => $this->tripId,
                'expected_driver' => $this->driverId,
                'actual_driver' => $trip->driver_id
            ]);
            return;
        }

        try {
            // === STEP 1: Update trip with additional data (OTP, vehicle, fare) ===
            $this->updateTripData($trip);

            // === STEP 2: Handle bidding if enabled ===
            $this->handleBidding($trip);

            // === STEP 3: Send OTP to customer ===
            $this->sendOtpToCustomer($trip);

            // === STEP 4: Send parcel SMS if applicable ===
            $this->sendParcelSms($trip);

            // === STEP 5: Update trip status timestamps ===
            $trip->tripStatus()->updateOrCreate(
                ['trip_request_id' => $trip->id],
                ['accepted' => now()]
            );

            // === STEP 6: Cleanup rejected driver requests ===
            DB::table('rejected_driver_requests')
                ->where('trip_request_id', $trip->id)
                ->delete();

            // === STEP 7: Update driver availability ===
            $this->updateDriverAvailability($trip);

            // === STEP 8: Dispatch route calculation job ===
            dispatch(new CalculateDriverArrivalTimeJob($trip->id, $this->driverId))
                ->onQueue('calculations');

            // === STEP 9: Notify other drivers ===
            dispatch(new NotifyOtherDriversJob($trip->id, $this->driverId))
                ->onQueue('notifications');

            // === STEP 10: Publish real-time events ===
            $this->publishRealtimeEvents($trip);

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('✅ Trip acceptance processing completed', [
                'trip_id' => $this->tripId,
                'elapsed_ms' => $elapsed
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Trip acceptance processing failed', [
                'trip_id' => $this->tripId,
                'driver_id' => $this->driverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Update trip with OTP, vehicle, and fare data
     */
    private function updateTripData(TripRequest $trip): void
    {
        $updateData = [];

        // Generate OTP
        if (isset($this->additionalData['otp'])) {
            $updateData['otp'] = $this->additionalData['otp'];
        }

        // Set vehicle
        if (isset($this->additionalData['vehicle_id'])) {
            $updateData['vehicle_id'] = $this->additionalData['vehicle_id'];
        }

        // Set vehicle category
        if (isset($this->additionalData['vehicle_category_id'])) {
            $updateData['vehicle_category_id'] = $this->additionalData['vehicle_category_id'];
        }

        // Set actual fare (for bidding)
        if (isset($this->additionalData['actual_fare'])) {
            $updateData['actual_fare'] = $this->additionalData['actual_fare'];
        }

        if (!empty($updateData)) {
            $trip->update($updateData);
            $trip->refresh();
        }
    }

    /**
     * Handle bidding logic if bid_on_fare is enabled
     */
    private function handleBidding(TripRequest $trip): void
    {
        $bid_on_fare = get_cache('bid_on_fare') ?? 0;

        if (!$bid_on_fare) {
            return;
        }

        // Check if driver already has a bidding record
        $existingBid = DB::table('fare_biddings')
            ->where('trip_request_id', $trip->id)
            ->where('driver_id', $this->driverId)
            ->where('is_ignored', 0)
            ->first();

        // If no existing bid and actual fare differs from estimated, create bid
        if (!$existingBid && $trip->estimated_fare != $trip->actual_fare) {
            DB::table('fare_biddings')->insert([
                'trip_request_id' => $trip->id,
                'driver_id' => $this->driverId,
                'customer_id' => $trip->customer_id,
                'bid_fare' => $trip->actual_fare,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Send OTP to customer via FCM and SMS
     */
    private function sendOtpToCustomer(TripRequest $trip): void
    {
        // Check if OTP is required
        $otpRequired = (bool)businessConfig(key: 'driver_otp_confirmation_for_trip', settingsType: TRIP_SETTINGS)?->value == 1;

        if (!$otpRequired || $trip->type !== RIDE_REQUEST) {
            return;
        }

        if (!$trip->customer) {
            Log::warning('Customer not found for OTP', ['trip_id' => $trip->id]);
            return;
        }

        $otp = $trip->otp ?? $this->additionalData['otp'] ?? '0000';
        $otpMessage = 'Your trip OTP is ' . $otp;

        // Send FCM notification (high priority)
        if ($trip->customer->fcm_token) {
            try {
                sendDeviceNotification(
                    fcm_token: $trip->customer->fcm_token,
                    title: translate('Trip OTP'),
                    description: translate($otpMessage),
                    status: 1,
                    ride_request_id: $trip->id,
                    type: $trip->type,
                    action: 'trip_otp',
                    user_id: $trip->customer->id
                );

                Log::info('📱 OTP FCM sent', [
                    'trip_id' => $trip->id,
                    'customer_id' => $trip->customer->id,
                    'otp' => $otp
                ]);
            } catch (\Exception $e) {
                Log::error('OTP FCM failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send SMS (lower priority, can fail)
        if ($trip->customer->phone) {
            try {
                self::send($trip->customer->phone, $otpMessage);

                Log::info('📧 OTP SMS sent', [
                    'trip_id' => $trip->id,
                    'phone' => substr($trip->customer->phone, -4) // Log last 4 digits only
                ]);
            } catch (\Exception $e) {
                Log::warning('OTP SMS failed (non-critical)', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage()
                ]);
                // Don't throw - SMS failure is acceptable
            }
        }
    }

    /**
     * Send parcel tracking SMS
     */
    private function sendParcelSms(TripRequest $trip): void
    {
        if ($trip->type !== PARCEL) {
            return;
        }

        $receiver = $trip->parcelUserInfo?->firstWhere('user_type', RECEIVER);
        if (!$receiver || !$receiver->contact_number) {
            return;
        }

        $parcelTrackingEnabled = businessConfig('parcel_tracking_status')?->value == 1;
        if (!$parcelTrackingEnabled) {
            return;
        }

        try {
            $template = businessConfig('parcel_tracking_message')?->value;
            if (!$template) {
                return;
            }

            $smsContent = smsTemplateDataFormat(
                value: $template,
                customerName: $receiver->name,
                parcelId: $trip->ref_id,
                trackingLink: route('track-parcel', $trip->ref_id)
            );

            self::send($receiver->contact_number, $smsContent);

            Log::info('📦 Parcel SMS sent', [
                'trip_id' => $trip->id,
                'receiver' => $receiver->name
            ]);
        } catch (\Exception $e) {
            Log::warning('Parcel SMS failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update driver availability status
     */
    private function updateDriverAvailability(TripRequest $trip): void
    {
        try {
            $driverDetails = DB::table('driver_details')
                ->where('user_id', $this->driverId)
                ->first();

            if (!$driverDetails) {
                return;
            }

            // Update ride/parcel count
            if ($trip->type === RIDE_REQUEST) {
                DB::table('driver_details')
                    ->where('user_id', $this->driverId)
                    ->update([
                        'availability_status' => 'on_trip',
                        'ride_count' => 1,
                        'updated_at' => now(),
                    ]);
            } else if ($trip->type === PARCEL) {
                DB::table('driver_details')
                    ->where('user_id', $this->driverId)
                    ->increment('parcel_count', 1, [
                        'updated_at' => now(),
                    ]);
            }

            Log::debug('Driver availability updated', [
                'driver_id' => $this->driverId,
                'trip_type' => $trip->type
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update driver availability', [
                'driver_id' => $this->driverId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Publish real-time events to Redis and Pusher
     */
    private function publishRealtimeEvents(TripRequest $trip): void
    {
        try {
            // Publish to Redis for Node.js real-time service
            $realtimePublisher = app(RealtimeEventPublisher::class);
            $realtimePublisher->publishDriverAssigned($trip);

            Log::debug('Redis event published', ['trip_id' => $trip->id]);
        } catch (\Exception $e) {
            Log::warning('Redis publish failed (non-critical)', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage()
            ]);
        }

        try {
            // Broadcast Pusher/Reverb event (if enabled)
            $tripForEvent = TripRequest::with(['customer', 'driver'])->find($trip->id);
            if ($tripForEvent) {
                checkPusherConnection(\App\Events\DriverTripAcceptedEvent::broadcast($tripForEvent));
            }

            Log::debug('Pusher event broadcast', ['trip_id' => $trip->id]);
        } catch (\Exception $e) {
            Log::warning('Pusher broadcast failed (non-critical)', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('🔥 Trip acceptance job failed permanently', [
            'trip_id' => $this->tripId,
            'driver_id' => $this->driverId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // TODO: Implement fallback mechanism
        // - Send alert to admin
        // - Mark trip for manual review
        // - Attempt to release lock if appropriate
    }
}
