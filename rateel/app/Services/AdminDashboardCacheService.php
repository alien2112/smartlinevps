<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class AdminDashboardCacheService
{
    /**
     * Clear all admin dashboard caches
     */
    public static function clearAll(): void
    {
        self::clearTripMetrics();
        self::clearUserCounts();
        self::clearZones();
        self::clearTransactions();
        self::clearRecentTrips();
        self::clearLeaderBoards();
        self::clearStatistics();
    }

    /**
     * Clear trip metrics cache
     */
    public static function clearTripMetrics(): void
    {
        Cache::forget('admin_dashboard_trip_metrics');
    }

    /**
     * Clear user count caches (customers and drivers)
     */
    public static function clearUserCounts(): void
    {
        Cache::forget('admin_dashboard_customer_count');
        Cache::forget('admin_dashboard_driver_count');
    }

    /**
     * Clear zones cache
     */
    public static function clearZones(): void
    {
        Cache::forget('admin_dashboard_zones');
    }

    /**
     * Clear transactions cache for a specific user
     */
    public static function clearTransactions(?int $userId = null): void
    {
        if ($userId) {
            Cache::forget("admin_dashboard_transactions_{$userId}");
        } else {
            // Clear all transaction caches (pattern-based deletion)
            self::clearCacheByPattern('admin_dashboard_transactions_*');
        }
    }

    /**
     * Clear recent trips cache
     */
    public static function clearRecentTrips(): void
    {
        Cache::forget('admin_dashboard_recent_trips');
    }

    /**
     * Clear all leader board caches
     */
    public static function clearLeaderBoards(): void
    {
        self::clearCacheByPattern('admin_dashboard_leaderboard_driver_*');
        self::clearCacheByPattern('admin_dashboard_leaderboard_customer_*');
    }

    /**
     * Clear all statistics caches
     */
    public static function clearStatistics(): void
    {
        self::clearCacheByPattern('admin_dashboard_earning_stats_*');
        self::clearCacheByPattern('admin_dashboard_zone_stats_*');
    }

    /**
     * Clear cache by pattern (supports file and redis drivers)
     */
    private static function clearCacheByPattern(string $pattern): void
    {
        $cacheDriver = config('cache.default');

        if ($cacheDriver === 'redis') {
            // For Redis, use keys pattern matching
            $prefix = config('cache.prefix') . ':';
            $keys = Cache::getRedis()->keys($prefix . $pattern);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    // Remove prefix for Cache::forget()
                    $cleanKey = str_replace($prefix, '', $key);
                    Cache::forget($cleanKey);
                }
            }
        } elseif ($cacheDriver === 'file') {
            // For file cache, we need to manually scan and delete matching files
            $cachePath = config('cache.stores.file.path');
            if (file_exists($cachePath)) {
                $files = glob($cachePath . '/*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
        // For other cache drivers, clearing all might be needed
        // or implement specific logic per driver
    }

    /**
     * Add manual cache clear button/endpoint support
     */
    public static function clearDashboardCache(): array
    {
        try {
            self::clearAll();
            return [
                'success' => true,
                'message' => 'Dashboard cache cleared successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to clear dashboard cache: ' . $e->getMessage()
            ];
        }
    }
}
