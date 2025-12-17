<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $locationData;

    /**
     * Create a new event instance.
     *
     * @param array $locationData
     */
    public function __construct(array $locationData)
    {
        $this->locationData = $locationData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver-location.{$this->locationData['ride_id']}"),
            new PrivateChannel("customer-ride.{$this->locationData['customer_id']}"),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return "driver-location-updated";
    }

    /**
     * Get the data to broadcast.
     *
     * @return array
     */
    public function broadcastWith()
    {
        return [
            'ride_id' => $this->locationData['ride_id'],
            'latitude' => $this->locationData['latitude'],
            'longitude' => $this->locationData['longitude'],
            'speed' => $this->locationData['speed'] ?? 0,
            'heading' => $this->locationData['heading'] ?? null,
            'eta_minutes' => $this->locationData['eta_minutes'] ?? null,
            'timestamp' => $this->locationData['timestamp'],
        ];
    }
}
