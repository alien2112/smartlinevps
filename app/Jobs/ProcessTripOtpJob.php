<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;
use App\Events\DriverTripStartedEvent;
use App\Services\RealtimeEventPublisher;
use Illuminate\Support\Facades\Log;

/**
 * ⚙️ BACKGROUND JOB: Process OTP Match Post-Actions ⚙️
 *
 * This job handles ALL heavy work after OTP is verified:
 * - Push notifications to customer
 * - Socket/Pusher broadcasts
 * - Redis pub/sub for real-time updates
 *
 * Runs asynchronously - does NOT block the HTTP response
 * Target execution time: < 2 seconds
 */
class ProcessTripOtpJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tripId;
    public $driverId;

    /**
     * Job configuration
     */
    public $tries = 3; // Retry up to 3 times on failure
    public $timeout = 20; // 20 second timeout
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     */
    public function __construct(string $tripId, string $driverId)
    {
        $this->tripId = $tripId;
        $this->driverId = $driverId;
        $this->onQueue('high-priority');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        Log::info('⚙️ Processing OTP match (async)', [
            'trip_id' => $this->tripId,
            'driver_id' => $this->driverId,
        ]);

        // Load trip with required relations
        $trip = TripRequest::with(['customer', 'driver', 'coordinate'])->find($this->tripId);

        if (!$trip) {
            Log::warning("Trip not found in OTP job", ['trip_id' => $this->tripId]);
            return;
        }

        try {
            // === STEP 1: Send FCM Notification to Customer ===
            $fcmStart = microtime(true);
            if ($trip->customer?->fcm_token) {
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
                Log::debug('📱 OTP FCM sent', [
                    'trip_id' => $trip->id,
                    'elapsed_ms' => round((microtime(true) - $fcmStart) * 1000, 2)
                ]);
            }

            // === STEP 2: Broadcast Pusher/Reverb Event ===
            $pusherStart = microtime(true);
            try {
                checkPusherConnection(DriverTripStartedEvent::broadcast($trip));
                Log::debug('📡 Pusher event sent', [
                    'trip_id' => $trip->id,
                    'elapsed_ms' => round((microtime(true) - $pusherStart) * 1000, 2)
                ]);
            } catch (\Exception $e) {
                Log::warning("Pusher broadcast failed (non-critical)", [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage()
                ]);
            }

            // === STEP 3: Publish to Redis for real-time updates ===
            $redisStart = microtime(true);
            try {
                $realtimePublisher = app(RealtimeEventPublisher::class);
                $realtimePublisher->publishRideStarted($trip);
                Log::debug('🔴 Redis event published', [
                    'trip_id' => $trip->id,
                    'elapsed_ms' => round((microtime(true) - $redisStart) * 1000, 2)
                ]);
            } catch (\Exception $e) {
                Log::warning('Redis publish failed (non-critical)', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage()
                ]);
            }

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            Log::info('✅ OTP processing completed', [
                'trip_id' => $this->tripId,
                'elapsed_ms' => $elapsed,
                'performance' => $elapsed < 1000 ? '⚡ FAST' : '⚠️ SLOW'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ OTP processing failed', [
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
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('🔥 OTP processing job failed permanently', [
            'trip_id' => $this->tripId,
            'driver_id' => $this->driverId,
            'error' => $exception->getMessage()
        ]);
    }
}
