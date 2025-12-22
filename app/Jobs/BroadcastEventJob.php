<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;
use Pusher\PusherException;

/**
 * Queue job for broadcasting real-time events
 * 
 * Moves Pusher broadcasting out of the request cycle
 * to improve API response times.
 */
class BroadcastEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;
    public int $timeout = 30;

    private string $channel;
    private string $event;
    private array $data;

    /**
     * Create a new job instance.
     *
     * @param string $channel
     * @param string $event
     * @param array $data
     */
    public function __construct(string $channel, string $event, array $data)
    {
        $this->channel = $channel;
        $this->event = $event;
        $this->data = $data;
        $this->onQueue('broadcasting');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                config('broadcasting.connections.pusher.options')
            );

            $pusher->trigger($this->channel, $this->event, $this->data);
            
            Log::debug('BroadcastEventJob: Event broadcast successfully', [
                'channel' => $this->channel,
                'event' => $this->event
            ]);
            
        } catch (PusherException $e) {
            Log::error('BroadcastEventJob: Pusher error', [
                'channel' => $this->channel,
                'event' => $this->event,
                'error' => $e->getMessage()
            ]);
            
            // Re-throw to trigger retry
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('BroadcastEventJob: General error', [
                'channel' => $this->channel,
                'event' => $this->event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BroadcastEventJob: Job failed after all retries', [
            'channel' => $this->channel,
            'event' => $this->event,
            'error' => $exception->getMessage()
        ]);
    }
}
