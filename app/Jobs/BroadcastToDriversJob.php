<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Queue job for broadcasting to multiple drivers
 * 
 * Efficiently broadcasts trip events to multiple drivers
 * without blocking the main request.
 */
class BroadcastToDriversJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    private array $driverIds;
    private string $event;
    private array $tripData;

    /**
     * Create a new job instance.
     *
     * @param array $driverIds Array of driver user IDs
     * @param string $event Event name
     * @param array $tripData Trip data to broadcast
     */
    public function __construct(array $driverIds, string $event, array $tripData)
    {
        $this->driverIds = $driverIds;
        $this->event = $event;
        $this->tripData = $tripData;
        $this->onQueue('broadcasting');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->driverIds)) {
            return;
        }

        try {
            $pusher = new \Pusher\Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                config('broadcasting.connections.pusher.options')
            );

            // Batch trigger - Pusher supports up to 100 channels per request
            $channels = array_map(fn($id) => 'private-driver.' . $id, $this->driverIds);
            
            // Split into batches of 100
            $channelBatches = array_chunk($channels, 100);
            
            foreach ($channelBatches as $batch) {
                try {
                    $pusher->trigger($batch, $this->event, $this->tripData);
                } catch (\Exception $e) {
                    Log::warning('BroadcastToDriversJob: Batch broadcast failed', [
                        'batch_size' => count($batch),
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Log::debug('BroadcastToDriversJob: Broadcast completed', [
                'driver_count' => count($this->driverIds),
                'event' => $this->event
            ]);
            
        } catch (\Exception $e) {
            Log::error('BroadcastToDriversJob: Failed', [
                'driver_count' => count($this->driverIds),
                'event' => $this->event,
                'error' => $e->getMessage()
            ]);
        }
    }
}
