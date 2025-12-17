<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Realtime Event Publisher
 * Publishes events to Redis for Node.js real-time service to consume
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
     * Publish ride started event
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
     * Publish message to Redis channel
     *
     * @param string $channel
     * @param array $data
     * @return void
     */
    protected function publish(string $channel, array $data): void
    {
        try {
            Redis::publish($channel, json_encode($data));

            Log::info('Published realtime event', [
                'channel' => $channel,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish realtime event', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }
}
