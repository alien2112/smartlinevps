<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Realtime Event Publisher
 * Publishes events to Redis for Node.js real-time service to consume
 * 
 * IMPORTANT: Events published here are received by Node.js RedisEventBus
 * and immediately emitted to connected socket clients.
 * 
 * This is the PRIMARY mechanism for real-time state updates.
 * FCM notifications are SECONDARY (for when socket is disconnected).
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
        
        try {
            Redis::publish($channel, json_encode($data));

            Log::info('Published realtime event', [
                'channel' => $channel,
                'trace_id' => $data['trace_id'],
                'ride_id' => $data['ride_id'] ?? $data['trip_id'] ?? null,
                'driver_id' => $data['driver_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish realtime event', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace_id' => $data['trace_id'],
                'ride_id' => $data['ride_id'] ?? $data['trip_id'] ?? null,
            ]);
        }
    }
}

