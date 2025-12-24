<?php

namespace App\Console\Commands;

use App\Services\WebSocketCleanupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupStaleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:stale-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup stale WebSocket connections and expired idempotency keys';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of stale data...');

        // Cleanup stale WebSocket connections
        $this->info('Cleaning up stale WebSocket connections...');
        $wsCleanupService = app(WebSocketCleanupService::class);
        $staleConnections = $wsCleanupService->cleanupStaleConnections();
        $this->info("Removed {$staleConnections} stale WebSocket connections");

        // Cleanup expired idempotency keys
        $this->info('Cleaning up expired idempotency keys...');
        $expiredKeys = DB::table('idempotency_keys')
            ->where('expires_at', '<', now())
            ->delete();
        $this->info("Removed {$expiredKeys} expired idempotency keys");

        // Cleanup old route points (older than retention period)
        $this->info('Cleaning up old route points...');
        $retentionDays = config('tracking.route_retention_days', 90);
        $oldRoutePoints = DB::table('trip_route_points')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
        $this->info("Removed {$oldRoutePoints} old route points");

        // Release abandoned trip locks (locked > 1 hour but not progressed)
        $this->info('Releasing abandoned trip locks...');
        $abandonedTrips = DB::table('trip_requests')
            ->where('locked_at', '<', now()->subHour())
            ->whereIn('current_status', ['accepted'])
            ->whereNull('trip_start_time')
            ->update([
                'driver_id' => null,
                'current_status' => 'pending',
                'locked_at' => null,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);
        $this->info("Released {$abandonedTrips} abandoned trip locks");

        Log::info('Stale data cleanup completed', [
            'stale_websocket_connections' => $staleConnections,
            'expired_idempotency_keys' => $expiredKeys,
            'old_route_points' => $oldRoutePoints,
            'abandoned_trips' => $abandonedTrips,
        ]);

        $this->info('Cleanup completed successfully!');

        return Command::SUCCESS;
    }
}
