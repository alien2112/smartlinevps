<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cleanup stale FCM tokens to reduce failed notification attempts.
 * 
 * This job removes FCM tokens from users who haven't been active in 90 days,
 * reducing the number of failed push notification attempts and improving
 * overall notification delivery rates.
 * 
 * Schedule: Weekly on Sundays at 3:00 AM
 */
class CleanupInvalidFcmTokensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    /**
     * Number of days of inactivity before clearing a token.
     */
    private const STALE_DAYS = 90;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cutoffDate = now()->subDays(self::STALE_DAYS);
        
        // Count tokens before cleanup
        $totalTokens = DB::table('users')
            ->whereNotNull('fcm_token')
            ->count();
        
        // Clear stale tokens
        $clearedCount = DB::table('users')
            ->whereNotNull('fcm_token')
            ->where('updated_at', '<', $cutoffDate)
            ->update(['fcm_token' => null]);

        // Log the results
        Log::info('FCM Token Cleanup completed', [
            'total_tokens_before' => $totalTokens,
            'stale_tokens_cleared' => $clearedCount,
            'remaining_tokens' => $totalTokens - $clearedCount,
            'cutoff_date' => $cutoffDate->toDateTimeString(),
        ]);

        // Also log to Sentry if available for monitoring
        if (app()->bound('sentry')) {
            app('sentry')->captureMessage('FCM Token Cleanup: Cleared ' . $clearedCount . ' stale tokens', \Sentry\Severity::info());
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FCM Token Cleanup failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
