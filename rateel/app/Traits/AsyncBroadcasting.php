<?php

namespace App\Traits;

use App\Jobs\BroadcastEventJob;
use App\Jobs\BroadcastToDriversJob;
use Illuminate\Support\Facades\Log;

/**
 * Async Broadcasting Trait
 *
 * Provides methods to broadcast events asynchronously
 * to improve API response times.
 *
 * Updated: 2026-01-14 - Removed dispatchSync to prevent blocking at scale
 */
trait AsyncBroadcasting
{
    /**
     * Broadcast an event asynchronously via queue
     *
     * @param string $channel Channel name (e.g., 'private-driver.123')
     * @param string $event Event name
     * @param array $data Event data
     * @param bool $immediate If true, dispatch to high-priority queue (still async but faster)
     * @return void
     *
     * Updated: 2026-01-14 - Changed immediate flag to use high-priority queue instead of sync
     */
    protected function broadcastAsync(string $channel, string $event, array $data, bool $immediate = false): void
    {
        try {
            if ($immediate) {
                // Updated 2026-01-14: Use high-priority queue instead of sync to prevent blocking
                // OLD CODE: BroadcastEventJob::dispatchSync($channel, $event, $data); // Commented 2026-01-14 - blocks request thread
                BroadcastEventJob::dispatch($channel, $event, $data)->onQueue('high');
            } else {
                // Dispatch to default queue for async processing
                BroadcastEventJob::dispatch($channel, $event, $data);
            }
        } catch (\Exception $e) {
            Log::warning('AsyncBroadcasting: Failed to dispatch broadcast job', [
                'channel' => $channel,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast to multiple drivers asynchronously
     *
     * @param array $driverIds Array of driver user IDs
     * @param string $event Event name
     * @param array $tripData Trip data to broadcast
     * @return void
     */
    protected function broadcastToDriversAsync(array $driverIds, string $event, array $tripData): void
    {
        if (empty($driverIds)) {
            return;
        }

        try {
            BroadcastToDriversJob::dispatch($driverIds, $event, $tripData);
            
            Log::debug('AsyncBroadcasting: Queued broadcast to drivers', [
                'driver_count' => count($driverIds),
                'event' => $event
            ]);
        } catch (\Exception $e) {
            Log::warning('AsyncBroadcasting: Failed to queue driver broadcast', [
                'driver_count' => count($driverIds),
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast trip update to a customer
     *
     * @param string $customerId
     * @param array $tripData
     * @param string $event
     * @return void
     */
    protected function broadcastToCustomer(string $customerId, array $tripData, string $event = 'trip-updated'): void
    {
        $channel = 'private-customer.' . $customerId;
        $this->broadcastAsync($channel, $event, $tripData);
    }

    /**
     * Broadcast trip update to a driver
     *
     * @param string $driverId
     * @param array $tripData
     * @param string $event
     * @return void
     */
    protected function broadcastToDriver(string $driverId, array $tripData, string $event = 'trip-updated'): void
    {
        $channel = 'private-driver.' . $driverId;
        $this->broadcastAsync($channel, $event, $tripData);
    }

    /**
     * Broadcast new trip request to nearby drivers
     *
     * @param array $nearbyDrivers Collection or array of driver records
     * @param array $tripData
     * @return void
     */
    protected function broadcastNewTripToDrivers($nearbyDrivers, array $tripData): void
    {
        $driverIds = [];
        
        foreach ($nearbyDrivers as $driver) {
            if (isset($driver->user_id)) {
                $driverIds[] = $driver->user_id;
            } elseif (isset($driver->id)) {
                $driverIds[] = $driver->id;
            }
        }
        
        if (!empty($driverIds)) {
            $this->broadcastToDriversAsync($driverIds, 'new-trip-request', $tripData);
        }
    }
}
