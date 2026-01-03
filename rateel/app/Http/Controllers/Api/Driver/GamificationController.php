<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    protected AchievementService $achievementService;

    public function __construct(AchievementService $achievementService)
    {
        $this->achievementService = $achievementService;
    }

    /**
     * Get driver achievements
     * GET /api/driver/auth/gamification/achievements
     */
    public function achievements(): JsonResponse
    {
        $driver = auth('api')->user();

        // Get all achievements
        $allAchievements = DB::table('achievements')
            ->where('is_active', true)
            ->orderBy('tier')
            ->orderBy('order')
            ->get();

        // Get unlocked achievements
        $unlockedIds = DB::table('driver_achievements')
            ->where('driver_id', $driver->id)
            ->pluck('unlocked_at', 'achievement_id')
            ->toArray();

        $achievements = $allAchievements->map(function($achievement) use ($unlockedIds) {
            $isUnlocked = isset($unlockedIds[$achievement->id]);

            return [
                'id' => $achievement->id,
                'key' => $achievement->key,
                'title' => $achievement->title,
                'description' => $achievement->description,
                'icon' => $achievement->icon ? asset('storage/' . $achievement->icon) : null,
                'category' => $achievement->category,
                'points' => $achievement->points,
                'tier' => $achievement->tier,
                'tier_name' => $this->getTierName($achievement->tier),
                'is_unlocked' => $isUnlocked,
                'unlocked_at' => $isUnlocked ? $unlockedIds[$achievement->id] : null,
                'requirements' => json_decode($achievement->requirements),
            ];
        });

        // Group by category
        $grouped = $achievements->groupBy('category');

        // Get progress stats
        $progress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driver->id)
            ->first();

        if (!$progress) {
            $progress = (object) [
                'total_points' => 0,
                'achievements_unlocked' => 0,
                'badges_earned' => 0,
            ];
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'achievements' => $grouped,
            'summary' => [
                'total_available' => $allAchievements->count(),
                'unlocked' => count($unlockedIds),
                'locked' => $allAchievements->count() - count($unlockedIds),
                'completion_percentage' => $allAchievements->count() > 0
                    ? round((count($unlockedIds) / $allAchievements->count()) * 100, 2)
                    : 0,
                'total_points' => $progress->total_points,
            ],
        ]));
    }

    /**
     * Get driver badges
     * GET /api/driver/auth/gamification/badges
     */
    public function badges(): JsonResponse
    {
        $driver = auth('api')->user();

        // Get all badges
        $allBadges = DB::table('badges')
            ->where('is_active', true)
            ->get();

        // Get earned badges
        $earnedBadges = DB::table('driver_badges')
            ->where('driver_id', $driver->id)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->get()
            ->keyBy('badge_id');

        $badges = $allBadges->map(function($badge) use ($earnedBadges) {
            $earned = $earnedBadges->get($badge->id);

            return [
                'id' => $badge->id,
                'key' => $badge->key,
                'title' => $badge->title,
                'description' => $badge->description,
                'icon' => $badge->icon ? asset('storage/' . $badge->icon) : null,
                'color' => $badge->color,
                'rarity' => $badge->rarity,
                'is_earned' => (bool) $earned,
                'earned_at' => $earned?->earned_at,
                'expires_at' => $earned?->expires_at,
                'criteria' => json_decode($badge->criteria),
            ];
        });

        // Group by rarity
        $grouped = $badges->groupBy('rarity');

        return response()->json(responseFormatter(DEFAULT_200, [
            'badges' => $grouped,
            'summary' => [
                'total_available' => $allBadges->count(),
                'earned' => $earnedBadges->count(),
                'common' => $badges->where('rarity', 'common')->where('is_earned', true)->count(),
                'rare' => $badges->where('rarity', 'rare')->where('is_earned', true)->count(),
                'epic' => $badges->where('rarity', 'epic')->where('is_earned', true)->count(),
                'legendary' => $badges->where('rarity', 'legendary')->where('is_earned', true)->count(),
            ],
        ]));
    }

    /**
     * Get gamification progress
     * GET /api/driver/auth/gamification/progress
     */
    public function progress(): JsonResponse
    {
        $driver = auth('api')->user();

        // Get or create progress
        $progress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driver->id)
            ->first();

        if (!$progress) {
            // Create initial progress
            DB::table('driver_gamification_progress')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'driver_id' => $driver->id,
                'total_points' => 0,
                'achievements_unlocked' => 0,
                'badges_earned' => 0,
                'current_streak_days' => 0,
                'longest_streak_days' => 0,
                'last_activity_date' => null,
                'statistics' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $progress = DB::table('driver_gamification_progress')
                ->where('driver_id', $driver->id)
                ->first();
        }

        // Get recent achievements (last 5)
        $recentAchievements = DB::table('driver_achievements as da')
            ->join('achievements as a', 'da.achievement_id', '=', 'a.id')
            ->where('da.driver_id', $driver->id)
            ->select('a.title', 'a.icon', 'a.points', 'da.unlocked_at')
            ->orderBy('da.unlocked_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($ach) {
                return [
                    'title' => $ach->title,
                    'icon' => $ach->icon ? asset('storage/' . $ach->icon) : null,
                    'points' => $ach->points,
                    'unlocked_at' => $ach->unlocked_at,
                    'time_ago' => \Carbon\Carbon::parse($ach->unlocked_at)->diffForHumans(),
                ];
            });

        // Calculate level and next level progress
        $level = $this->calculateLevel($progress->total_points);
        $nextLevelPoints = $this->getPointsForLevel($level + 1);
        $currentLevelPoints = $this->getPointsForLevel($level);
        $pointsToNextLevel = $nextLevelPoints - $progress->total_points;
        $progressToNextLevel = $nextLevelPoints > $currentLevelPoints
            ? round((($progress->total_points - $currentLevelPoints) / ($nextLevelPoints - $currentLevelPoints)) * 100, 2)
            : 100;

        // Leaderboard position (optional)
        $rank = DB::table('driver_gamification_progress')
            ->where('total_points', '>', $progress->total_points)
            ->count() + 1;

        return response()->json(responseFormatter(DEFAULT_200, [
            'level' => [
                'current' => $level,
                'next' => $level + 1,
                'progress_percentage' => $progressToNextLevel,
                'points_to_next_level' => $pointsToNextLevel,
            ],
            'points' => [
                'total' => $progress->total_points,
                'current_level_min' => $currentLevelPoints,
                'next_level_min' => $nextLevelPoints,
            ],
            'achievements' => [
                'total_unlocked' => $progress->achievements_unlocked,
                'recent' => $recentAchievements,
            ],
            'badges' => [
                'total_earned' => $progress->badges_earned,
            ],
            'streak' => [
                'current_days' => $progress->current_streak_days,
                'longest_days' => $progress->longest_streak_days,
                'last_activity' => $progress->last_activity_date,
            ],
            'rank' => [
                'position' => $rank,
            ],
            'statistics' => json_decode($progress->statistics ?? '{}'),
        ]));
    }

    /**
     * Helper: Calculate level from points
     */
    private function calculateLevel(int $points): int
    {
        // Simple formula: Level = floor(sqrt(points / 100))
        // Level 1 = 0-99 points
        // Level 2 = 100-399 points
        // Level 3 = 400-899 points
        // etc.
        return max(1, floor(sqrt($points / 100)) + 1);
    }

    /**
     * Helper: Get points required for a level
     */
    private function getPointsForLevel(int $level): int
    {
        return pow($level - 1, 2) * 100;
    }

    /**
     * Helper: Get tier name
     */
    private function getTierName(int $tier): string
    {
        return match($tier) {
            1 => 'Bronze',
            2 => 'Silver',
            3 => 'Gold',
            4 => 'Platinum',
            default => 'Bronze',
        };
    }

    /**
     * Manually check and unlock achievements
     * POST /api/driver/auth/gamification/check-achievements
     */
    public function checkAchievements(): JsonResponse
    {
        $driver = auth('api')->user();

        // Check and unlock achievements
        $unlockedAchievements = $this->achievementService->checkAndUnlockAchievements($driver->id);

        // Check and award badges
        $awardedBadges = $this->achievementService->checkAndAwardBadges($driver->id);

        // Update streak
        $this->achievementService->updateStreak($driver->id);

        // Get updated stats
        $stats = $this->achievementService->getDriverStats($driver->id);

        return response()->json(responseFormatter(DEFAULT_200, [
            'newly_unlocked_achievements' => $unlockedAchievements,
            'newly_awarded_badges' => $awardedBadges,
            'current_stats' => $stats,
            'message' => count($unlockedAchievements) > 0 || count($awardedBadges) > 0
                ? translate('Congratulations! You unlocked :count achievements and :badges badges', [
                    'count' => count($unlockedAchievements),
                    'badges' => count($awardedBadges)
                ])
                : translate('Keep going! Complete more trips to unlock achievements.'),
        ]));
    }
}
