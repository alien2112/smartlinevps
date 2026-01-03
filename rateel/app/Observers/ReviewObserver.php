<?php

namespace App\Observers;

use App\Services\AchievementService;
use Illuminate\Support\Facades\Log;
use Modules\ReviewModule\Entities\Review;

/**
 * Review Observer for processing rating-based achievements
 */
class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     */
    public function created(Review $review): void
    {
        // Only process if this is a review for a driver
        if (!$review->received_by) {
            return;
        }

        $this->processRatingAchievements($review);
    }

    /**
     * Process rating achievements for the driver who received the review
     */
    private function processRatingAchievements(Review $review): void
    {
        dispatch(function () use ($review) {
            try {
                $achievementService = app(AchievementService::class);

                // Check rating-related achievements
                $unlocked = $achievementService->checkAndUnlockAchievements($review->received_by, 'ratings');

                if (count($unlocked) > 0) {
                    Log::info('Driver rating achievements unlocked', [
                        'driver_id' => $review->received_by,
                        'review_id' => $review->id,
                        'rating' => $review->rating,
                        'achievements' => array_column($unlocked, 'key'),
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Rating achievement processing failed', [
                    'review_id' => $review->id,
                    'driver_id' => $review->received_by,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}
