<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\TripManagement\Entities\TripRequest;

class LeaderboardController extends Controller
{
    /**
     * Get leaderboard with rankings
     * GET /api/driver/auth/leaderboard
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:weekly,monthly,all_time',
            'category' => 'sometimes|in:trips,earnings,rating,points',
            'limit' => 'sometimes|integer|min:5|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        $type = $request->input('type', 'weekly');
        $category = $request->input('category', 'trips');
        $limit = $request->input('limit', 20);

        // Cache key for this specific leaderboard
        $cacheKey = "leaderboard_{$type}_{$category}_{$limit}";
        $cacheDuration = $type === 'all_time' ? 3600 : 900; // 1 hour for all_time, 15 min for others

        // Get leaderboard data (cached)
        $leaderboard = Cache::remember($cacheKey, $cacheDuration, function () use ($type, $category, $limit) {
            return $this->calculateLeaderboard($type, $category, $limit);
        });

        // Get current driver's position (always fresh)
        $driverPosition = $this->getDriverPosition($driver->id, $type, $category);

        // Check if driver is in visible leaderboard
        $driverInList = collect($leaderboard)->contains('driver_id', $driver->id);

        return response()->json(responseFormatter(DEFAULT_200, [
            'leaderboard' => $leaderboard,
            'my_position' => $driverPosition,
            'am_i_in_top' => $driverInList,
            'type' => $type,
            'category' => $category,
            'period' => $this->getPeriodDescription($type),
            'last_updated' => now()->toIso8601String(),
        ]));
    }

    /**
     * Get current driver's detailed ranking
     * GET /api/driver/auth/leaderboard/my-rank
     */
    public function myRank(Request $request): JsonResponse
    {
        $driver = auth('api')->user();

        // Get rankings across all categories
        $rankings = [
            'weekly' => [
                'trips' => $this->getDriverPosition($driver->id, 'weekly', 'trips'),
                'earnings' => $this->getDriverPosition($driver->id, 'weekly', 'earnings'),
                'rating' => $this->getDriverPosition($driver->id, 'weekly', 'rating'),
            ],
            'monthly' => [
                'trips' => $this->getDriverPosition($driver->id, 'monthly', 'trips'),
                'earnings' => $this->getDriverPosition($driver->id, 'monthly', 'earnings'),
                'rating' => $this->getDriverPosition($driver->id, 'monthly', 'rating'),
            ],
            'all_time' => [
                'trips' => $this->getDriverPosition($driver->id, 'all_time', 'trips'),
                'earnings' => $this->getDriverPosition($driver->id, 'all_time', 'earnings'),
                'rating' => $this->getDriverPosition($driver->id, 'all_time', 'rating'),
                'points' => $this->getDriverPosition($driver->id, 'all_time', 'points'),
            ],
        ];

        // Get gamification progress
        $progress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driver->id)
            ->first();

        // Calculate percentile (better than X% of drivers)
        $totalDrivers = DB::table('users')
            ->where('user_type', 'driver')
            ->whereNotNull('deleted_at')
            ->count();

        $bestRank = min(
            $rankings['all_time']['trips']['rank'] ?? PHP_INT_MAX,
            $rankings['all_time']['earnings']['rank'] ?? PHP_INT_MAX,
            $rankings['all_time']['rating']['rank'] ?? PHP_INT_MAX
        );

        $percentile = $totalDrivers > 0 && $bestRank < PHP_INT_MAX
            ? round((1 - ($bestRank / $totalDrivers)) * 100, 1)
            : 0;

        return response()->json(responseFormatter(DEFAULT_200, [
            'rankings' => $rankings,
            'gamification' => [
                'total_points' => $progress->total_points ?? 0,
                'achievements_unlocked' => $progress->achievements_unlocked ?? 0,
                'badges_earned' => $progress->badges_earned ?? 0,
                'current_streak' => $progress->current_streak_days ?? 0,
                'longest_streak' => $progress->longest_streak_days ?? 0,
            ],
            'summary' => [
                'best_category' => $this->getBestCategory($rankings),
                'percentile' => $percentile,
                'better_than_percentage' => $percentile,
                'total_drivers' => $totalDrivers,
            ],
        ]));
    }

    /**
     * Get drivers near current driver's position
     * GET /api/driver/auth/leaderboard/nearby
     */
    public function nearby(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:weekly,monthly,all_time',
            'category' => 'sometimes|in:trips,earnings,rating,points',
            'range' => 'sometimes|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();
        $type = $request->input('type', 'weekly');
        $category = $request->input('category', 'trips');
        $range = $request->input('range', 5);

        // Get current position
        $myPosition = $this->getDriverPosition($driver->id, $type, $category);

        if (!$myPosition['rank']) {
            return response()->json(responseFormatter(DEFAULT_200, [
                'above' => [],
                'my_position' => null,
                'below' => [],
                'message' => translate('You are not yet ranked. Complete more trips to appear on the leaderboard.'),
            ]));
        }

        $rank = $myPosition['rank'];

        // Get full leaderboard to extract nearby drivers
        $fullLeaderboard = $this->calculateLeaderboard($type, $category, 1000);

        $myIndex = collect($fullLeaderboard)->search(function ($item) use ($driver) {
            return $item['driver_id'] === $driver->id;
        });

        $above = [];
        $below = [];

        if ($myIndex !== false) {
            // Get drivers above (better rank)
            $startAbove = max(0, $myIndex - $range);
            $above = array_slice($fullLeaderboard, $startAbove, $myIndex - $startAbove);

            // Get drivers below (worse rank)
            $below = array_slice($fullLeaderboard, $myIndex + 1, $range);
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'above' => $above,
            'my_position' => $myPosition,
            'below' => $below,
            'type' => $type,
            'category' => $category,
        ]));
    }

    /**
     * Calculate leaderboard for a given type and category
     */
    private function calculateLeaderboard(string $type, string $category, int $limit): array
    {
        $dateRange = $this->getDateRange($type);

        switch ($category) {
            case 'trips':
                return $this->getTripLeaderboard($dateRange, $limit);
            case 'earnings':
                return $this->getEarningsLeaderboard($dateRange, $limit);
            case 'rating':
                return $this->getRatingLeaderboard($dateRange, $limit);
            case 'points':
                return $this->getPointsLeaderboard($limit);
            default:
                return $this->getTripLeaderboard($dateRange, $limit);
        }
    }

    /**
     * Get trip count leaderboard
     */
    private function getTripLeaderboard(array $dateRange, int $limit): array
    {
        $query = TripRequest::select(
            'driver_id',
            DB::raw('COUNT(*) as total_trips'),
            DB::raw('SUM(CASE WHEN current_status = "completed" THEN 1 ELSE 0 END) as completed_trips')
        )
            ->where('current_status', 'completed')
            ->groupBy('driver_id')
            ->orderByDesc('completed_trips')
            ->limit($limit);

        if ($dateRange['start']) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $results = $query->get();

        return $this->formatLeaderboardResults($results, 'completed_trips', 'trips');
    }

    /**
     * Get earnings leaderboard
     */
    private function getEarningsLeaderboard(array $dateRange, int $limit): array
    {
        $query = TripRequest::select(
            'driver_id',
            DB::raw('SUM(paid_fare) as total_earnings'),
            DB::raw('COUNT(*) as trip_count')
        )
            ->where('payment_status', PAID)
            ->groupBy('driver_id')
            ->orderByDesc('total_earnings')
            ->limit($limit);

        if ($dateRange['start']) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $results = $query->get();

        return $this->formatLeaderboardResults($results, 'total_earnings', 'earnings');
    }

    /**
     * Get rating leaderboard
     */
    private function getRatingLeaderboard(array $dateRange, int $limit): array
    {
        $query = DB::table('reviews')
            ->select(
                'received_by as driver_id',
                DB::raw('AVG(rating) as avg_rating'),
                DB::raw('COUNT(*) as review_count')
            )
            ->whereNotNull('received_by')
            ->groupBy('received_by')
            ->having('review_count', '>=', 5) // Minimum 5 reviews to qualify
            ->orderByDesc('avg_rating')
            ->orderByDesc('review_count')
            ->limit($limit);

        if ($dateRange['start']) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $results = $query->get();

        return $this->formatLeaderboardResults(collect($results), 'avg_rating', 'rating');
    }

    /**
     * Get points leaderboard (all-time only)
     */
    private function getPointsLeaderboard(int $limit): array
    {
        $results = DB::table('driver_gamification_progress')
            ->select(
                'driver_id',
                'total_points',
                'achievements_unlocked',
                'badges_earned'
            )
            ->orderByDesc('total_points')
            ->limit($limit)
            ->get();

        return $this->formatLeaderboardResults(collect($results), 'total_points', 'points');
    }

    /**
     * Format leaderboard results with driver details
     */
    private function formatLeaderboardResults($results, string $valueField, string $category): array
    {
        $leaderboard = [];
        $rank = 1;

        foreach ($results as $result) {
            $driver = DB::table('users')
                ->where('id', $result->driver_id)
                ->select('id', 'first_name', 'last_name', 'profile_image')
                ->first();

            if (!$driver) {
                continue;
            }

            // Check privacy settings
            $privacySettings = DB::table('driver_privacy_settings')
                ->where('driver_id', $result->driver_id)
                ->first();

            $showOnLeaderboard = !$privacySettings || $privacySettings->show_on_leaderboard;

            if (!$showOnLeaderboard) {
                continue; // Skip drivers who opted out
            }

            $value = $result->$valueField;
            $formattedValue = $this->formatValue($value, $category);

            $leaderboard[] = [
                'rank' => $rank,
                'driver_id' => $driver->id,
                'name' => $driver->first_name . ' ' . substr($driver->last_name ?? '', 0, 1) . '.',
                'profile_image' => $driver->profile_image ? asset('storage/' . $driver->profile_image) : null,
                'value' => $value,
                'formatted_value' => $formattedValue,
                'category' => $category,
                'extra' => $this->getExtraInfo($result, $category),
            ];

            $rank++;
        }

        return $leaderboard;
    }

    /**
     * Get driver's position in leaderboard
     */
    private function getDriverPosition(string $driverId, string $type, string $category): array
    {
        $dateRange = $this->getDateRange($type);

        switch ($category) {
            case 'trips':
                return $this->getDriverTripPosition($driverId, $dateRange);
            case 'earnings':
                return $this->getDriverEarningsPosition($driverId, $dateRange);
            case 'rating':
                return $this->getDriverRatingPosition($driverId, $dateRange);
            case 'points':
                return $this->getDriverPointsPosition($driverId);
            default:
                return $this->getDriverTripPosition($driverId, $dateRange);
        }
    }

    /**
     * Get driver's trip ranking position
     */
    private function getDriverTripPosition(string $driverId, array $dateRange): array
    {
        $query = TripRequest::where('current_status', 'completed');

        if ($dateRange['start']) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $driverTrips = (clone $query)->where('driver_id', $driverId)->count();

        if ($driverTrips === 0) {
            return ['rank' => null, 'value' => 0, 'formatted_value' => '0 trips'];
        }

        $rank = $query->select('driver_id', DB::raw('COUNT(*) as trip_count'))
                ->groupBy('driver_id')
                ->having('trip_count', '>', $driverTrips)
                ->get()
                ->count() + 1;

        return [
            'rank' => $rank,
            'value' => $driverTrips,
            'formatted_value' => $driverTrips . ' trips',
        ];
    }

    /**
     * Get driver's earnings ranking position
     */
    private function getDriverEarningsPosition(string $driverId, array $dateRange): array
    {
        $query = TripRequest::where('payment_status', PAID);

        if ($dateRange['start']) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $driverEarnings = (clone $query)->where('driver_id', $driverId)->sum('paid_fare');

        if ($driverEarnings == 0) {
            return ['rank' => null, 'value' => 0, 'formatted_value' => getCurrencyFormat(0)];
        }

        $rank = $query->select('driver_id', DB::raw('SUM(paid_fare) as total'))
                ->groupBy('driver_id')
                ->having('total', '>', $driverEarnings)
                ->get()
                ->count() + 1;

        return [
            'rank' => $rank,
            'value' => $driverEarnings,
            'formatted_value' => getCurrencyFormat($driverEarnings),
        ];
    }

    /**
     * Get driver's rating ranking position
     */
    private function getDriverRatingPosition(string $driverId, array $dateRange): array
    {
        $query = DB::table('reviews')->whereNotNull('received_by');

        if ($dateRange['start']) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }

        $driverData = (clone $query)->where('received_by', $driverId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as review_count')
            ->first();

        if (!$driverData || $driverData->review_count < 5) {
            return ['rank' => null, 'value' => 0, 'formatted_value' => 'Not enough reviews'];
        }

        $avgRating = round($driverData->avg_rating, 2);

        $rank = $query->select('received_by', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as cnt'))
                ->groupBy('received_by')
                ->having('cnt', '>=', 5)
                ->having('avg_rating', '>', $avgRating)
                ->get()
                ->count() + 1;

        return [
            'rank' => $rank,
            'value' => $avgRating,
            'formatted_value' => number_format($avgRating, 2) . ' stars',
        ];
    }

    /**
     * Get driver's points ranking position
     */
    private function getDriverPointsPosition(string $driverId): array
    {
        $driverProgress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driverId)
            ->first();

        if (!$driverProgress) {
            return ['rank' => null, 'value' => 0, 'formatted_value' => '0 points'];
        }

        $points = $driverProgress->total_points;

        $rank = DB::table('driver_gamification_progress')
                ->where('total_points', '>', $points)
                ->count() + 1;

        return [
            'rank' => $rank,
            'value' => $points,
            'formatted_value' => number_format($points) . ' points',
        ];
    }

    /**
     * Get date range for leaderboard type
     */
    private function getDateRange(string $type): array
    {
        switch ($type) {
            case 'weekly':
                return [
                    'start' => now()->startOfWeek(),
                    'end' => now()->endOfWeek(),
                ];
            case 'monthly':
                return [
                    'start' => now()->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            case 'all_time':
            default:
                return ['start' => null, 'end' => null];
        }
    }

    /**
     * Get period description
     */
    private function getPeriodDescription(string $type): array
    {
        switch ($type) {
            case 'weekly':
                return [
                    'label' => 'This Week',
                    'start' => now()->startOfWeek()->toDateString(),
                    'end' => now()->endOfWeek()->toDateString(),
                ];
            case 'monthly':
                return [
                    'label' => now()->format('F Y'),
                    'start' => now()->startOfMonth()->toDateString(),
                    'end' => now()->endOfMonth()->toDateString(),
                ];
            case 'all_time':
            default:
                return [
                    'label' => 'All Time',
                    'start' => null,
                    'end' => null,
                ];
        }
    }

    /**
     * Format value based on category
     */
    private function formatValue($value, string $category): string
    {
        switch ($category) {
            case 'trips':
                return number_format($value) . ' trips';
            case 'earnings':
                return getCurrencyFormat($value);
            case 'rating':
                return number_format($value, 2) . ' stars';
            case 'points':
                return number_format($value) . ' pts';
            default:
                return (string) $value;
        }
    }

    /**
     * Get extra info for leaderboard entry
     */
    private function getExtraInfo($result, string $category): array
    {
        switch ($category) {
            case 'trips':
                return [
                    'total_trips' => $result->total_trips ?? $result->completed_trips,
                ];
            case 'earnings':
                return [
                    'trip_count' => $result->trip_count ?? 0,
                ];
            case 'rating':
                return [
                    'review_count' => $result->review_count ?? 0,
                ];
            case 'points':
                return [
                    'achievements' => $result->achievements_unlocked ?? 0,
                    'badges' => $result->badges_earned ?? 0,
                ];
            default:
                return [];
        }
    }

    /**
     * Determine best category for a driver
     */
    private function getBestCategory(array $rankings): ?string
    {
        $best = null;
        $bestRank = PHP_INT_MAX;

        foreach ($rankings['all_time'] as $category => $data) {
            if ($data['rank'] && $data['rank'] < $bestRank) {
                $bestRank = $data['rank'];
                $best = $category;
            }
        }

        return $best;
    }
}
