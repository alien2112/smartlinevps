<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Service to manage and cleanup WebSocket connections
 * Prevents dead connections from accumulating
 */
class WebSocketCleanupService
{
    protected string $connectionPrefix = 'ws:connection:';
    protected string $userConnectionsPrefix = 'ws:user:';
    protected int $connectionTTL = 3600; // 1 hour
    protected int $heartbeatTimeout = 120; // 2 minutes

    /**
     * Register a new WebSocket connection
     *
     * @param string $connectionId
     * @param string $userId
     * @param array $metadata
     * @return void
     */
    public function registerConnection(string $connectionId, string $userId, array $metadata = []): void
    {
        $key = $this->connectionPrefix . $connectionId;
        $userKey = $this->userConnectionsPrefix . $userId;

        $connectionData = array_merge($metadata, [
            'user_id' => $userId,
            'connected_at' => now()->toIso8601String(),
            'last_heartbeat' => now()->toIso8601String(),
        ]);

        // Store connection data with TTL
        Cache::put($key, $connectionData, $this->connectionTTL);

        // Track connection for this user
        Cache::put($userKey . ':' . $connectionId, true, $this->connectionTTL);

        Log::info('WebSocket connection registered', [
            'connection_id' => $connectionId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Update heartbeat timestamp for a connection
     *
     * @param string $connectionId
     * @return bool
     */
    public function heartbeat(string $connectionId): bool
    {
        $key = $this->connectionPrefix . $connectionId;
        $connection = Cache::get($key);

        if (!$connection) {
            return false; // Connection not found or expired
        }

        $connection['last_heartbeat'] = now()->toIso8601String();
        Cache::put($key, $connection, $this->connectionTTL);

        return true;
    }

    /**
     * Remove a WebSocket connection
     *
     * @param string $connectionId
     * @return void
     */
    public function removeConnection(string $connectionId): void
    {
        $key = $this->connectionPrefix . $connectionId;
        $connection = Cache::get($key);

        if ($connection && isset($connection['user_id'])) {
            $userId = $connection['user_id'];
            $userKey = $this->userConnectionsPrefix . $userId . ':' . $connectionId;
            Cache::forget($userKey);
        }

        Cache::forget($key);

        Log::info('WebSocket connection removed', [
            'connection_id' => $connectionId,
        ]);
    }

    /**
     * Cleanup stale connections (no heartbeat for X seconds)
     *
     * @return int Number of connections cleaned up
     */
    public function cleanupStaleConnections(): int
    {
        $cleaned = 0;
        $pattern = $this->connectionPrefix . '*';

        // This is a simplified version - in production, use Redis SCAN
        $connections = Cache::get('all_ws_connections', []);

        foreach ($connections as $connectionId) {
            $key = $this->connectionPrefix . $connectionId;
            $connection = Cache::get($key);

            if (!$connection) {
                continue;
            }

            $lastHeartbeat = \Carbon\Carbon::parse($connection['last_heartbeat']);

            if ($lastHeartbeat->addSeconds($this->heartbeatTimeout)->isPast()) {
                $this->removeConnection($connectionId);
                $cleaned++;

                Log::warning('Stale WebSocket connection cleaned up', [
                    'connection_id' => $connectionId,
                    'user_id' => $connection['user_id'] ?? null,
                    'last_heartbeat' => $connection['last_heartbeat'],
                ]);
            }
        }

        return $cleaned;
    }

    /**
     * Get all active connections for a user
     *
     * @param string $userId
     * @return array
     */
    public function getUserConnections(string $userId): array
    {
        $pattern = $this->userConnectionsPrefix . $userId . ':*';
        $connections = [];

        // In production, use Redis SCAN to find all keys matching pattern
        // For simplicity, tracking in a separate key
        $userConnectionsKey = $this->userConnectionsPrefix . $userId . ':list';
        $connectionIds = Cache::get($userConnectionsKey, []);

        foreach ($connectionIds as $connectionId) {
            $key = $this->connectionPrefix . $connectionId;
            $connection = Cache::get($key);

            if ($connection) {
                $connections[] = array_merge($connection, ['connection_id' => $connectionId]);
            }
        }

        return $connections;
    }

    /**
     * Check if a connection is alive
     *
     * @param string $connectionId
     * @return bool
     */
    public function isConnectionAlive(string $connectionId): bool
    {
        $key = $this->connectionPrefix . $connectionId;
        $connection = Cache::get($key);

        if (!$connection) {
            return false;
        }

        $lastHeartbeat = \Carbon\Carbon::parse($connection['last_heartbeat']);

        return !$lastHeartbeat->addSeconds($this->heartbeatTimeout)->isPast();
    }

    /**
     * Get connection statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        // In production, maintain counters in Redis
        return [
            'total_connections' => $this->getTotalConnections(),
            'stale_connections' => $this->getStaleConnectionsCount(),
            'active_connections' => $this->getActiveConnectionsCount(),
        ];
    }

    protected function getTotalConnections(): int
    {
        // Implementation depends on how you're tracking connections
        return Cache::get('ws_total_connections', 0);
    }

    protected function getStaleConnectionsCount(): int
    {
        // Implementation depends on how you're tracking connections
        return Cache::get('ws_stale_connections', 0);
    }

    protected function getActiveConnectionsCount(): int
    {
        return $this->getTotalConnections() - $this->getStaleConnectionsCount();
    }
}
