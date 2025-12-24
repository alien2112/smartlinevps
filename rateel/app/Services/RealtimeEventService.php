<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Service to publish events to Redis for Node.js realtime service
 * This bridges Laravel with the Node.js Socket.IO server
 */
class RealtimeEventService
{
    /**
     * Redis channels for different events
     */
    const CHANNEL_DRIVER_ACCEPTED = 'laravel:driver.accepted';
    const CHANNEL_RIDE_STARTED = 'laravel:ride.started';
    const CHANNEL_RIDE_COMPLETED = 'laravel:ride.completed';
    const CHANNEL_RIDE_CANCELLED = 'laravel:ride.cancelled';
    const CHANNEL_DRIVER_ASSIGNED = 'laravel:driver.assigned';

    /**
     * Publish driver accepted event
     * Called when a driver accepts a ride request
     *
     * @param string $tripId
     * @param string $driverId
     * @param string $customerId
     * @param array $driverInfo
     * @param array $tripInfo
     * @param string|null $traceId
     * @return bool
     */
    public function publishDriverAccepted(
        string $tripId,
        string $driverId,
        string $customerId,
        array $driverInfo = [],
        array $tripInfo = [],
        ?string $traceId = null
    ): bool {
        $payload = [
            'event' => 'driver.accepted',
            'trace_id' => $traceId ?? request()?->attributes?->get('trace_id'),
            'timestamp' => now()->toISOString(),
            'ride_id' => $tripId,
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'customer_id' => $customerId,
            'driver' => $driverInfo,
            'trip' => $tripInfo,
        ];

        return $this->publish(self::CHANNEL_DRIVER_ACCEPTED, $payload);
    }

    /**
     * Publish ride started event
     * Called when OTP is verified and ride begins
     *
     * @param string $tripId
     * @param string $driverId
     * @param string $customerId
     * @param string|null $traceId
     * @return bool
     */
    public function publishRideStarted(
        string $tripId,
        string $driverId,
        string $customerId,
        ?string $traceId = null
    ): bool {
        $payload = [
            'event' => 'ride.started',
            'trace_id' => $traceId ?? request()?->attributes?->get('trace_id'),
            'timestamp' => now()->toISOString(),
            'ride_id' => $tripId,
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'customer_id' => $customerId,
        ];

        return $this->publish(self::CHANNEL_RIDE_STARTED, $payload);
    }

    /**
     * Publish ride completed event
     *
     * @param string $tripId
     * @param string $driverId
     * @param string $customerId
     * @param float $finalFare
     * @param string|null $traceId
     * @return bool
     */
    public function publishRideCompleted(
        string $tripId,
        string $driverId,
        string $customerId,
        float $finalFare = 0,
        ?string $traceId = null
    ): bool {
        $payload = [
            'event' => 'ride.completed',
            'trace_id' => $traceId ?? request()?->attributes?->get('trace_id'),
            'timestamp' => now()->toISOString(),
            'ride_id' => $tripId,
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'customer_id' => $customerId,
            'final_fare' => $finalFare,
        ];

        return $this->publish(self::CHANNEL_RIDE_COMPLETED, $payload);
    }

    /**
     * Publish ride cancelled event
     *
     * @param string $tripId
     * @param string|null $driverId
     * @param string $customerId
     * @param string $cancelledBy 'driver' or 'customer'
     * @param string|null $traceId
     * @return bool
     */
    public function publishRideCancelled(
        string $tripId,
        ?string $driverId,
        string $customerId,
        string $cancelledBy,
        ?string $traceId = null
    ): bool {
        $payload = [
            'event' => 'ride.cancelled',
            'trace_id' => $traceId ?? request()?->attributes?->get('trace_id'),
            'timestamp' => now()->toISOString(),
            'ride_id' => $tripId,
            'trip_id' => $tripId,
            'driver_id' => $driverId,
            'customer_id' => $customerId,
            'cancelled_by' => $cancelledBy,
        ];

        return $this->publish(self::CHANNEL_RIDE_CANCELLED, $payload);
    }

    /**
     * Publish to Redis channel
     *
     * @param string $channel
     * @param array $payload
     * @return bool
     */
    protected function publish(string $channel, array $payload): bool
    {
        try {
            $json = json_encode($payload);
            
            Log::info('Publishing realtime event', [
                'channel' => $channel,
                'trace_id' => $payload['trace_id'] ?? null,
                'ride_id' => $payload['ride_id'] ?? null,
                'event' => $payload['event'] ?? null,
            ]);

            Redis::publish($channel, $json);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to publish realtime event', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace_id' => $payload['trace_id'] ?? null,
            ]);
            
            return false;
        }
    }
}
