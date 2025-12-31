<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Issue #21 FIX: Admin Dashboard Cache Service
 *
 * Provides caching for dashboard statistics to avoid expensive
 * queries on every page load. Cache TTLs are short (5-15 minutes)
 * to ensure data freshness while reducing database load.
 */
class AdminDashboardCacheService
{
    // Cache TTLs in seconds
    private const TTL_TRIP_METRICS = 300;      // 5 minutes
    private const TTL_USER_COUNTS = 600;       // 10 minutes
    private const TTL_EARNING_STATS = 300;     // 5 minutes
    private const TTL_ZONE_STATS = 900;        // 15 minutes
    private const TTL_RECENT_TRIPS = 60;       // 1 minute
    private const TTL_LEADERBOARDS = 600;      // 10 minutes
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

    // =========================================================================
    // Issue #21 FIX: Cached Dashboard Data Getters
    // =========================================================================

    /**
     * Get cached trip metrics (today's stats)
     */
    public static function getTripMetrics(): array
    {
        return Cache::remember('admin_dashboard_trip_metrics', self::TTL_TRIP_METRICS, function () {
            $today = Carbon::today();

            return [
                'total_today' => DB::table('trip_requests')
                    ->whereDate('created_at', $today)
                    ->count(),
                'completed_today' => DB::table('trip_requests')
                    ->whereDate('created_at', $today)
                    ->where('current_status', 'completed')
                    ->count(),
                'cancelled_today' => DB::table('trip_requests')
                    ->whereDate('created_at', $today)
                    ->where('current_status', 'cancelled')
                    ->count(),
                'ongoing' => DB::table('trip_requests')
                    ->whereIn('current_status', ['pending', 'accepted', 'ongoing'])
                    ->count(),
                'revenue_today' => DB::table('trip_requests')
                    ->whereDate('created_at', $today)
                    ->where('payment_status', 'paid')
                    ->sum('paid_fare'),
            ];
        });
    }

    /**
     * Get cached user counts
     */
    public static function getUserCounts(): array
    {
        return Cache::remember('admin_dashboard_user_counts', self::TTL_USER_COUNTS, function () {
            return [
                'total_customers' => DB::table('users')
                    ->where('user_type', 'customer')
                    ->whereNull('deleted_at')
                    ->count(),
                'active_customers' => DB::table('users')
                    ->where('user_type', 'customer')
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->count(),
                'total_drivers' => DB::table('users')
                    ->where('user_type', 'driver')
                    ->whereNull('deleted_at')
                    ->count(),
                'active_drivers' => DB::table('users')
                    ->where('user_type', 'driver')
                    ->where('is_active', 1)
                    ->whereNull('deleted_at')
                    ->count(),
                'online_drivers' => DB::table('user_last_locations')
                    ->where('updated_at', '>=', Carbon::now()->subMinutes(5))
                    ->count(),
            ];
        });
    }

    /**
     * Get cached earning statistics
     */
    public static function getEarningStats(string $period = 'today'): array
    {
        $cacheKey = 'admin_dashboard_earning_stats_' . $period;

        return Cache::remember($cacheKey, self::TTL_EARNING_STATS, function () use ($period) {
            $startDate = match($period) {
                'today' => Carbon::today(),
                'week' => Carbon::now()->startOfWeek(),
                'month' => Carbon::now()->startOfMonth(),
                'year' => Carbon::now()->startOfYear(),
                default => Carbon::today(),
            };

            $tripRevenue = DB::table('trip_requests')
                ->where('created_at', '>=', $startDate)
                ->where('payment_status', 'paid')
                ->sum('paid_fare');

            $adminCommission = DB::table('trip_request_fees')
                ->join('trip_requests', 'trip_requests.id', '=', 'trip_request_fees.trip_request_id')
                ->where('trip_requests.created_at', '>=', $startDate)
                ->where('trip_requests.payment_status', 'paid')
                ->sum('trip_request_fees.admin_commission');

            return [
                'period' => $period,
                'total_revenue' => round($tripRevenue, 2),
                'admin_commission' => round($adminCommission, 2),
                'trip_count' => DB::table('trip_requests')
                    ->where('created_at', '>=', $startDate)
                    ->where('payment_status', 'paid')
                    ->count(),
            ];
        });
    }

    /**
     * Get cached zone statistics
     */
    public static function getZoneStats(): array
    {
        return Cache::remember('admin_dashboard_zone_stats', self::TTL_ZONE_STATS, function () {
            return DB::table('zones')
                ->select([
                    'zones.id',
                    'zones.name',
                    DB::raw('COUNT(trip_requests.id) as trip_count'),
                    DB::raw('COALESCE(SUM(trip_requests.paid_fare), 0) as revenue'),
                ])
                ->leftJoin('trip_requests', function ($join) {
                    $join->on('zones.id', '=', 'trip_requests.zone_id')
                        ->where('trip_requests.created_at', '>=', Carbon::now()->subDays(30));
                })
                ->where('zones.is_active', 1)
                ->whereNull('zones.deleted_at')
                ->groupBy('zones.id', 'zones.name')
                ->orderByDesc('trip_count')
                ->limit(10)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get cached recent trips
     */
    public static function getRecentTrips(int $limit = 10): array
    {
        return Cache::remember('admin_dashboard_recent_trips', self::TTL_RECENT_TRIPS, function () use ($limit) {
            return DB::table('trip_requests')
                ->select([
                    'trip_requests.id',
                    'trip_requests.ref_id',
                    'trip_requests.current_status',
                    'trip_requests.type',
                    'trip_requests.paid_fare',
                    'trip_requests.created_at',
                    'customers.first_name as customer_name',
                    'drivers.first_name as driver_name',
                ])
                ->leftJoin('users as customers', 'trip_requests.customer_id', '=', 'customers.id')
                ->leftJoin('users as drivers', 'trip_requests.driver_id', '=', 'drivers.id')
                ->orderByDesc('trip_requests.created_at')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get cached driver leaderboard
     */
    public static function getDriverLeaderboard(string $period = 'month', int $limit = 10): array
    {
        $cacheKey = "admin_dashboard_leaderboard_driver_{$period}";

        return Cache::remember($cacheKey, self::TTL_LEADERBOARDS, function () use ($period, $limit) {
            $startDate = match($period) {
                'week' => Carbon::now()->startOfWeek(),
                'month' => Carbon::now()->startOfMonth(),
                'year' => Carbon::now()->startOfYear(),
                default => Carbon::now()->startOfMonth(),
            };

            return DB::table('users')
                ->select([
                    'users.id',
                    'users.first_name',
                    'users.last_name',
                    'users.profile_image',
                    DB::raw('COUNT(trip_requests.id) as trip_count'),
                    DB::raw('COALESCE(SUM(trip_requests.paid_fare), 0) as total_earnings'),
                    DB::raw('COALESCE(AVG(received_reviews.rating), 0) as avg_rating'),
                ])
                ->leftJoin('trip_requests', function ($join) use ($startDate) {
                    $join->on('users.id', '=', 'trip_requests.driver_id')
                        ->where('trip_requests.created_at', '>=', $startDate)
                        ->where('trip_requests.current_status', 'completed');
                })
                ->leftJoin('reviews as received_reviews', 'users.id', '=', 'received_reviews.receiver_id')
                ->where('users.user_type', 'driver')
                ->where('users.is_active', 1)
                ->whereNull('users.deleted_at')
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.profile_image')
                ->orderByDesc('trip_count')
                ->limit($limit)
                ->get()
                ->toArray();
        });
    }

    /**
     * Get all dashboard data in a single call (reduces multiple cache hits)
     */
    public static function getAllDashboardData(): array
    {
        return [
            'trip_metrics' => self::getTripMetrics(),
            'user_counts' => self::getUserCounts(),
            'earning_stats' => self::getEarningStats('today'),
            'zone_stats' => self::getZoneStats(),
            'recent_trips' => self::getRecentTrips(),
            'driver_leaderboard' => self::getDriverLeaderboard(),
        ];
    }
}
