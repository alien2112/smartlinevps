<?php

namespace App\Services;

use App\Models\DriverNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TripManagement\Entities\TripRequest;

class AchievementService
{
    /**
     * Check and unlock achievements for a driver
     *
     * Uses database transaction with row locking to prevent race conditions
     * where concurrent requests could unlock the same achievement twice.
     *
     * @param string $driverId
     * @param string|null $triggerType Optional: Only check specific category
     * @return array Newly unlocked achievements
     */
    public function checkAndUnlockAchievements(string $driverId, ?string $triggerType = null): array
    {
        return DB::transaction(function () use ($driverId, $triggerType) {
            $unlockedAchievements = [];

            // Lock the driver's gamification progress row to serialize achievement checks
            DB::table('driver_gamification_progress')
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->first();

            // Get driver stats
            $stats = $this->getDriverStats($driverId);

            // Get already unlocked achievement IDs with lock
            $alreadyUnlockedIds = DB::table('driver_achievements')
                ->where('driver_id', $driverId)
                ->lockForUpdate()
                ->pluck('achievement_id')
                ->toArray();

            // Get achievements that driver hasn't unlocked yet
            $query = DB::table('achievements')
                ->where('is_active', true)
                ->whereNotIn('id', $alreadyUnlockedIds);

            if ($triggerType) {
                $query->where('category', $triggerType);
            }

            $achievements = $query->get();

            foreach ($achievements as $achievement) {
                $requirements = json_decode($achievement->requirements, true) ?? [];

                if ($this->meetsRequirements($stats, $requirements)) {
                    // Double-check not already unlocked (belt and suspenders)
                    $exists = DB::table('driver_achievements')
                        ->where('driver_id', $driverId)
                        ->where('achievement_id', $achievement->id)
                        ->exists();

                    if (!$exists) {
                        // Unlock achievement
                        $this->unlockAchievement($driverId, $achievement, $stats);
                        $unlockedAchievements[] = [
                            'id' => $achievement->id,
                            'key' => $achievement->key,
                            'title' => $achievement->title,
                            'points' => $achievement->points,
                            'tier' => $achievement->tier,
                        ];
                    }
                }
            }

            return $unlockedAchievements;
        });
    }

    /**
     * Get driver statistics for achievement checking
     */
    public function getDriverStats(string $driverId): array
    {
        // Total completed trips
        $totalTrips = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'completed')
            ->count();

        // Total earnings
        $totalEarnings = TripRequest::where('driver_id', $driverId)
            ->where('payment_status', PAID)
            ->sum('paid_fare');

        // Five-star ratings
        $fiveStarRatings = DB::table('reviews')
            ->where('received_by', $driverId)
            ->where('rating', 5)
            ->count();

        // Average rating
        $ratingData = DB::table('reviews')
            ->where('received_by', $driverId)
            ->selectRaw('AVG(rating) as avg_rating, COUNT(*) as total_reviews')
            ->first();

        // Current streak
        $progress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driverId)
            ->first();

        $currentStreak = $progress->current_streak_days ?? 0;

        // Early morning trips (before 7 AM)
        $earlyTrips = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'completed')
            ->whereRaw('HOUR(created_at) < 7')
            ->count();

        // Night trips (after 10 PM)
        $nightTrips = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'completed')
            ->whereRaw('HOUR(created_at) >= 22')
            ->count();

        // Weekend trips
        $weekendTrips = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'completed')
            ->whereRaw('DAYOFWEEK(created_at) IN (1, 7)')
            ->count();

        // Cancelled trips
        $cancelledTrips = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'cancelled')
            ->count();

        // Longest single trip distance
        $longestTrip = TripRequest::where('driver_id', $driverId)
            ->where('current_status', 'completed')
            ->max('estimated_distance') ?? 0;

        // Successful referrals
        $referrals = DB::table('users')
            ->where('ref_by_id', $driverId)
            ->whereNotNull('ref_by_id')
            ->count();

        return [
            'trips' => $totalTrips,
            'total_earnings' => $totalEarnings,
            'five_star_ratings' => $fiveStarRatings,
            'avg_rating' => round($ratingData->avg_rating ?? 0, 2),
            'total_reviews' => $ratingData->total_reviews ?? 0,
            'streak_days' => $currentStreak,
            'early_trips' => $earlyTrips,
            'night_trips' => $nightTrips,
            'weekend_trips' => $weekendTrips,
            'cancelled_trips' => $cancelledTrips,
            'trips_without_cancel' => max(0, $totalTrips), // Simplified - would need more complex logic
            'trip_distance_km' => $longestTrip,
            'successful_referrals' => $referrals,
        ];
    }

    /**
     * Check if driver meets achievement requirements
     */
    private function meetsRequirements(array $stats, array $requirements): bool
    {
        foreach ($requirements as $key => $value) {
            // Handle special cases
            if ($key === 'min_reviews') {
                if (($stats['total_reviews'] ?? 0) < $value) {
                    return false;
                }
                continue;
            }

            // Standard comparison
            $statValue = $stats[$key] ?? 0;
            if ($statValue < $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Unlock an achievement for a driver
     */
    private function unlockAchievement(string $driverId, object $achievement, array $stats): void
    {
        // Insert driver_achievement record
        DB::table('driver_achievements')->insert([
            'id' => Str::uuid(),
            'driver_id' => $driverId,
            'achievement_id' => $achievement->id,
            'unlocked_at' => now(),
            'unlock_data' => json_encode([
                'stats_at_unlock' => $stats,
                'unlocked_via' => 'auto_check',
            ]),
            'is_featured' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update gamification progress
        $progress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driverId)
            ->first();

        if ($progress) {
            DB::table('driver_gamification_progress')
                ->where('driver_id', $driverId)
                ->update([
                    'total_points' => $progress->total_points + $achievement->points,
                    'achievements_unlocked' => $progress->achievements_unlocked + 1,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('driver_gamification_progress')->insert([
                'id' => Str::uuid(),
                'driver_id' => $driverId,
                'total_points' => $achievement->points,
                'achievements_unlocked' => 1,
                'badges_earned' => 0,
                'current_streak_days' => 0,
                'longest_streak_days' => 0,
                'last_activity_date' => now(),
                'statistics' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Send notification
        DriverNotification::notify(
            $driverId,
            'achievement',
            translate('Achievement Unlocked!'),
            translate('You earned ":title" (+:points pts)', [
                'title' => $achievement->title,
                'points' => $achievement->points
            ]),
            [
                'achievement_id' => $achievement->id,
                'achievement_key' => $achievement->key,
                'points' => $achievement->points,
            ],
            'high',
            'gamification'
        );
    }

    /**
     * Update driver streak on trip completion
     */
    public function updateStreak(string $driverId): void
    {
        $progress = DB::table('driver_gamification_progress')
            ->where('driver_id', $driverId)
            ->first();

        $today = now()->toDateString();

        if (!$progress) {
            // Create new progress record
            DB::table('driver_gamification_progress')->insert([
                'id' => Str::uuid(),
                'driver_id' => $driverId,
                'total_points' => 0,
                'achievements_unlocked' => 0,
                'badges_earned' => 0,
                'current_streak_days' => 1,
                'longest_streak_days' => 1,
                'last_activity_date' => $today,
                'statistics' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        $lastActivity = $progress->last_activity_date;

        if ($lastActivity === $today) {
            // Already active today, no update needed
            return;
        }

        $yesterday = now()->subDay()->toDateString();
        $currentStreak = $progress->current_streak_days;
        $longestStreak = $progress->longest_streak_days;

        if ($lastActivity === $yesterday) {
            // Consecutive day - increase streak
            $currentStreak++;
            if ($currentStreak > $longestStreak) {
                $longestStreak = $currentStreak;
            }
        } else {
            // Streak broken - reset to 1
            $currentStreak = 1;
        }

        DB::table('driver_gamification_progress')
            ->where('driver_id', $driverId)
            ->update([
                'current_streak_days' => $currentStreak,
                'longest_streak_days' => $longestStreak,
                'last_activity_date' => $today,
                'updated_at' => now(),
            ]);
    }

    /**
     * Award badge to a driver
     */
    public function awardBadge(string $driverId, string $badgeKey, ?array $earningData = null): bool
    {
        $badge = DB::table('badges')
            ->where('key', $badgeKey)
            ->where('is_active', true)
            ->first();

        if (!$badge) {
            return false;
        }

        // Check if already earned
        $existing = DB::table('driver_badges')
            ->where('driver_id', $driverId)
            ->where('badge_id', $badge->id)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return false; // Already has this badge
        }

        // Award badge
        DB::table('driver_badges')->insert([
            'id' => Str::uuid(),
            'driver_id' => $driverId,
            'badge_id' => $badge->id,
            'earned_at' => now(),
            'expires_at' => null, // Some badges could expire
            'is_active' => true,
            'earning_data' => json_encode($earningData ?? []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update progress
        DB::table('driver_gamification_progress')
            ->where('driver_id', $driverId)
            ->increment('badges_earned');

        // Send notification
        DriverNotification::notify(
            $driverId,
            'badge',
            translate('New Badge Earned!'),
            translate('You earned the ":title" badge', ['title' => $badge->title]),
            [
                'badge_id' => $badge->id,
                'badge_key' => $badge->key,
                'rarity' => $badge->rarity,
            ],
            'high',
            'gamification'
        );

        return true;
    }

    /**
     * Check and award automatic badges based on criteria
     */
    public function checkAndAwardBadges(string $driverId): array
    {
        $awardedBadges = [];
        $stats = $this->getDriverStats($driverId);

        // Check verified_driver badge
        $documentsVerified = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->where('verification_status', 'approved')
            ->count() >= 3; // Assuming 3 required documents

        if ($documentsVerified && $this->awardBadge($driverId, 'verified_driver', ['verified_at' => now()->toIso8601String()])) {
            $awardedBadges[] = 'verified_driver';
        }

        // Check high_acceptor badge (95%+ acceptance rate)
        $driver = DB::table('users')->find($driverId);
        $driverDetails = DB::table('driver_details')->where('user_id', $driverId)->first();
        if ($driverDetails) {
            // Calculate acceptance rate
            $totalRequests = TripRequest::where('driver_id', $driverId)->count();
            $acceptedRequests = TripRequest::where('driver_id', $driverId)
                ->whereNotIn('current_status', ['cancelled'])
                ->count();

            if ($totalRequests >= 50) { // Minimum 50 requests
                $acceptanceRate = ($acceptedRequests / $totalRequests) * 100;
                if ($acceptanceRate >= 95 && $this->awardBadge($driverId, 'high_acceptor', ['acceptance_rate' => $acceptanceRate])) {
                    $awardedBadges[] = 'high_acceptor';
                }
            }
        }

        // Check consistent_driver badge (30 day streak)
        if ($stats['streak_days'] >= 30 && $this->awardBadge($driverId, 'consistent_driver', ['streak' => $stats['streak_days']])) {
            $awardedBadges[] = 'consistent_driver';
        }

        return $awardedBadges;
    }
}
