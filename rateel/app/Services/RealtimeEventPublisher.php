<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcasting;

/**
 * Realtime Event Publisher
 * Publishes events to Redis for Node.js real-time service to consume
 *
 * IMPORTANT: Events published here are received by Node.js RedisEventBus
 * and immediately emitted to connected socket clients.
 *
 * This is the PRIMARY mechanism for real-time state updates.
 * FCM notifications are SECONDARY (for when socket is disconnected).
 *
 * Issue #10 FIX: Implements event batching to reduce Redis pub/sub overhead
 */
class RealtimeEventPublisher
{
    // Redis channels matching Node.js RedisEventBus
    const CHANNEL_RIDE_CREATED = 'laravel:ride.created';
    const CHANNEL_RIDE_CANCELLED = 'laravel:ride.cancelled';
    const CHANNEL_RIDE_COMPLETED = 'laravel:ride.completed';
    const CHANNEL_RIDE_STARTED = 'laravel:ride.started';
    const CHANNEL_DRIVER_ASSIGNED = 'laravel:driver.assigned';
    const CHANNEL_PAYMENT_COMPLETED = 'laravel:payment.completed';

    // NEW: Critical channels for fixing race conditions
    const CHANNEL_TRIP_ACCEPTED = 'laravel:trip.accepted';  // Driver accepted trip - notify BOTH driver and customer
    const CHANNEL_OTP_VERIFIED = 'laravel:otp.verified';    // OTP verified - trip can start
    const CHANNEL_DRIVER_ARRIVED = 'laravel:driver.arrived'; // Driver arrived at pickup

    // Issue #10 FIX: Batch channel for multi-driver notifications
    const CHANNEL_BATCH_NOTIFICATION = 'laravel:batch.notification';

    /**
     * Issue #10 FIX: Pending events for batching
     * @var array
     */
    private array $pendingEvents = [];

    /**
     * Issue #10 FIX: Whether batching mode is enabled
     * @var bool
     */
    private bool $batchingEnabled = false;

    /**
     * Get trace ID from current request context
     */
    private function getTraceId(): ?string
    {
        try {
            return request()?->attributes?->get('trace_id');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Publish ride created event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishRideCreated($trip): void
    {
        $this->publish(self::CHANNEL_RIDE_CREATED, [
            'ride_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'pickup_latitude' => $trip->coordinates->pickup_coordinates->latitude ?? null,
            'pickup_longitude' => $trip->coordinates->pickup_coordinates->longitude ?? null,
            'destination_latitude' => $trip->coordinates->destination_coordinates->latitude ?? null,
            'destination_longitude' => $trip->coordinates->destination_coordinates->longitude ?? null,
            'vehicle_category_id' => $trip->vehicle_category_id,
            'estimated_fare' => $trip->estimated_fare,
            'created_at' => $trip->created_at->toIso8601String(),
        ]);
    }

    /**
     * Publish ride cancelled event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @param string $cancelledBy
     * @return void
     */
    public function publishRideCancelled($trip, string $cancelledBy): void
    {
        $this->publish(self::CHANNEL_RIDE_CANCELLED, [
            'ride_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'cancelled_by' => $cancelledBy, // 'driver' or 'customer'
            'cancelled_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish ride completed event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishRideCompleted($trip): void
    {
        $this->publish(self::CHANNEL_RIDE_COMPLETED, [
            'ride_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'final_fare' => $trip->paid_fare ?? $trip->actual_fare,
            'total_distance' => $trip->total_distance,
            'total_duration' => $trip->total_duration,
            'completed_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish ride started event (OTP verified, trip ongoing)
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishRideStarted($trip): void
    {
        $this->publish(self::CHANNEL_RIDE_STARTED, [
            'ride_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'started_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish driver assigned event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishDriverAssigned($trip): void
    {
        $this->publish(self::CHANNEL_DRIVER_ASSIGNED, [
            'ride_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'assigned_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish payment completed event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @param float $amount
     * @return void
     */
    public function publishPaymentCompleted($trip, float $amount): void
    {
        $this->publish(self::CHANNEL_PAYMENT_COMPLETED, [
            'ride_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'amount' => $amount,
            'payment_method' => $trip->payment_method ?? 'cash',
            'paid_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * ========================================================================
     * NEW: Critical event for fixing race condition
     * ========================================================================
     * Publish trip accepted event - THIS MUST BE CALLED BEFORE API RETURNS
     * 
     * This event:
     * 1. Notifies DRIVER that their acceptance was successful (immediate UI update)
     * 2. Notifies CUSTOMER that a driver has been assigned
     * 3. Notifies OTHER DRIVERS that this ride is no longer available
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @param array $driverData Driver info to send to customer
     * @param array $tripData Full trip data for driver confirmation
     * @return void
     */
    public function publishTripAccepted($trip, array $driverData = [], array $tripData = []): void
    {
        $eventData = [
            'ride_id' => $trip->id,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'status' => 'accepted',
            'otp' => $trip->otp,
            'vehicle_id' => $trip->vehicle_id,
            'vehicle_category_id' => $trip->vehicle_category_id,
            'accepted_at' => now()->toIso8601String(),
            
            // Driver info for customer app
            'driver' => $driverData,
            
            // Full trip data for driver app confirmation
            'trip' => $tripData,
        ];
        
        Log::info('Publishing trip accepted event BEFORE response', [
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'customer_id' => $trip->customer_id,
        ]);
        
        $this->publish(self::CHANNEL_TRIP_ACCEPTED, $eventData);
    }

    /**
     * Publish OTP verified event - Trip is now ONGOING
     * 
     * Called BEFORE the API returns success to ensure driver app updates immediately
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishOtpVerified($trip): void
    {
        $eventData = [
            'ride_id' => $trip->id,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'status' => 'ongoing',
            'verified_at' => now()->toIso8601String(),
        ];
        
        Log::info('Publishing OTP verified event BEFORE response', [
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
        ]);
        
        $this->publish(self::CHANNEL_OTP_VERIFIED, $eventData);
    }

    /**
     * Publish driver arrived at pickup event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishDriverArrived($trip): void
    {
        $this->publish(self::CHANNEL_DRIVER_ARRIVED, [
            'ride_id' => $trip->id,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'arrived_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Publish trip completed event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishTripCompleted($trip): void
    {
        $this->publish(self::CHANNEL_RIDE_COMPLETED, [
            'ride_id' => $trip->id,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'status' => 'completed',
            'completed_at' => now()->toIso8601String(),
            'paid_fare' => $trip->paid_fare ?? null,
            'payment_status' => $trip->payment_status ?? null,
        ]);
    }

    /**
     * Publish trip cancelled event
     *
     * @param \Modules\TripManagement\Entities\TripRequest $trip
     * @return void
     */
    public function publishTripCancelled($trip): void
    {
        $this->publish(self::CHANNEL_RIDE_CANCELLED, [
            'ride_id' => $trip->id,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'driver_id' => $trip->driver_id,
            'status' => 'cancelled',
            'cancelled_at' => now()->toIso8601String(),
            'cancelled_by' => $trip->fee?->cancelled_by ?? 'driver',
        ]);
    }

    /**
     * Publish message to Redis channel
     *
     * @param string $channel
     * @param array $data
     * @return void
     */
    protected function publish(string $channel, array $data): void
    {
        // Add trace_id for end-to-end tracking
        $data['trace_id'] = $this->getTraceId();
        $data['published_at'] = now()->toIso8601String();

        // Issue #10 FIX: If batching is enabled, queue the event
        if ($this->batchingEnabled) {
            $this->pendingEvents[] = ['channel' => $channel, 'data' => $data];
            return;
        }

        $this->publishNow($channel, $data);
    }

    /**
     * Issue #10 FIX: Publish immediately without batching
     * Implements fallback to Pusher if Redis fails (redundancy)
     */
    protected function publishNow(string $channel, array $data): void
    {
        $redisSuccess = false;

        try {
            Redis::publish($channel, json_encode($data));
            $redisSuccess = true;

            Log::info('Published realtime event via Redis', [
                'channel' => $channel,
                'trace_id' => $data['trace_id'] ?? null,
                'ride_id' => $data['ride_id'] ?? $data['trip_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to publish via Redis, attempting Pusher fallback', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace_id' => $data['trace_id'] ?? null,
            ]);

            // Fallback to Pusher if Redis fails
            $this->publishViaPusher($channel, $data);
        }
    }

    /**
     * Fallback publisher using Pusher for redundancy
     * Used when Redis is unavailable
     */
    private function publishViaPusher(string $channel, array $data): void
    {
        try {
            // Convert internal channel names to Pusher format
            $pusherChannel = $this->convertChannelToPusher($channel);

            Broadcasting::channel($pusherChannel)->emit('realtime.event', $data);

            Log::info('Published realtime event via Pusher fallback', [
                'channel' => $pusherChannel,
                'trace_id' => $data['trace_id'] ?? null,
                'ride_id' => $data['ride_id'] ?? $data['trip_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::critical('Failed to publish via both Redis and Pusher', [
                'channel' => $channel,
                'redis_error' => $data['redis_error'] ?? null,
                'pusher_error' => $e->getMessage(),
                'trace_id' => $data['trace_id'] ?? null,
            ]);
        }
    }

    /**
     * Convert internal Redis channel names to Pusher broadcast channels
     */
    private function convertChannelToPusher(string $channel): string
    {
        // Map internal channels to public broadcast channels
        $mapping = [
            'laravel:trip.accepted' => 'public:trip.accepted',
            'laravel:otp.verified' => 'public:trip.started',
            'laravel:driver.arrived' => 'public:driver.arrived',
            'laravel:ride.created' => 'public:ride.created',
            'laravel:ride.cancelled' => 'public:ride.cancelled',
            'laravel:ride.completed' => 'public:ride.completed',
            'laravel:payment.completed' => 'public:payment.completed',
            'laravel:batch.notification' => 'public:batch.notification',
        ];

        return $mapping[$channel] ?? $channel;
    }

    // ========================================================================
    // Issue #10 FIX: Batching Methods
    // ========================================================================

    /**
     * Enable batching mode - events will be queued instead of published immediately
     * Call flush() at end of request to publish all at once
     */
    public function startBatch(): self
    {
        $this->batchingEnabled = true;
        $this->pendingEvents = [];
        return $this;
    }

    /**
     * Queue an event for batched publishing
     */
    public function queueEvent(string $channel, array $data): self
    {
        $data['trace_id'] = $this->getTraceId();
        $data['published_at'] = now()->toIso8601String();
        $this->pendingEvents[] = ['channel' => $channel, 'data' => $data];
        return $this;
    }

    /**
     * Flush all pending events using Redis pipeline
     * This is much more efficient than individual publishes
     */
    public function flush(): void
    {
        if (empty($this->pendingEvents)) {
            $this->batchingEnabled = false;
            return;
        }

        try {
            $pipeline = Redis::pipeline();

            foreach ($this->pendingEvents as $event) {
                $pipeline->publish($event['channel'], json_encode($event['data']));
            }

            $pipeline->exec();

            Log::info('Flushed batched realtime events', [
                'count' => count($this->pendingEvents),
                'channels' => array_unique(array_column($this->pendingEvents, 'channel')),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to flush batched realtime events', [
                'error' => $e->getMessage(),
                'count' => count($this->pendingEvents),
            ]);
        }

        $this->pendingEvents = [];
        $this->batchingEnabled = false;
    }

    /**
     * Issue #10 & #15 FIX: Publish notification to multiple drivers in a single Redis call
     * Instead of looping and publishing individually
     *
     * @param array $driverIds Array of driver IDs to notify
     * @param mixed $trip Trip request object
     * @param string $eventType Event type (new_ride_request, etc)
     */
    public function publishToDrivers(array $driverIds, $trip, string $eventType = 'new_ride_request'): void
    {
        if (empty($driverIds)) {
            return;
        }

        $eventData = [
            'driver_ids' => $driverIds,
            'ride_id' => $trip->id,
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'event_type' => $eventType,
            'pickup_latitude' => $trip->coordinate?->pickup_coordinates?->latitude ?? null,
            'pickup_longitude' => $trip->coordinate?->pickup_coordinates?->longitude ?? null,
            'destination_latitude' => $trip->coordinate?->destination_coordinates?->latitude ?? null,
            'destination_longitude' => $trip->coordinate?->destination_coordinates?->longitude ?? null,
            'vehicle_category_id' => $trip->vehicle_category_id,
            'estimated_fare' => $trip->estimated_fare,
            'type' => $trip->type,
            'created_at' => $trip->created_at?->toIso8601String(),
            'trace_id' => $this->getTraceId(),
            'published_at' => now()->toIso8601String(),
        ];

        try {
            // Single Redis publish - Node.js will fan out to individual sockets
            Redis::publish(self::CHANNEL_BATCH_NOTIFICATION, json_encode($eventData));

            Log::info('Published batch driver notification', [
                'driver_count' => count($driverIds),
                'trip_id' => $trip->id,
                'event_type' => $eventType,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish batch driver notification', [
                'error' => $e->getMessage(),
                'driver_count' => count($driverIds),
                'trip_id' => $trip->id,
            ]);
        }
    }

    /**
     * Get count of pending events in batch
     */
    public function getPendingCount(): int
    {
        return count($this->pendingEvents);
    }

    /**
     * Check if batching mode is currently enabled
     */
    public function isBatching(): bool
    {
        return $this->batchingEnabled;
    }
}

