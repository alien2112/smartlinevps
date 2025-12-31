<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\Coupon;
use Modules\CouponManagement\Entities\CouponTargetUser;
use Modules\CouponManagement\Entities\UserDevice;
use Modules\CouponManagement\Service\FcmService;
use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class SendCouponBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;
    public int $timeout = 600; // 10 minutes for bulk operations

    private const CHUNK_SIZE = 100;

    public function __construct(
        public string $couponId,
        public ?array $userIds = null,
        public ?string $segmentKey = null,
        public ?string $messageTemplate = null
    ) {
        $this->onQueue('notifications-bulk');
    }

    public function handle(FcmService $fcmService): void
    {
        $logContext = [
            'coupon_id' => $this->couponId,
            'user_ids_count' => $this->userIds ? count($this->userIds) : null,
            'segment_key' => $this->segmentKey,
            'job_attempt' => $this->attempts(),
        ];

        Log::info('SendCouponBulkJob: Starting', $logContext);

        $coupon = Coupon::find($this->couponId);
        if (!$coupon) {
            Log::warning('SendCouponBulkJob: Coupon not found', $logContext);
            return;
        }

        // Determine target users
        $userQuery = $this->buildUserQuery($coupon);

        $totalUsers = 0;
        $totalSuccess = 0;
        $totalFailure = 0;

        // Process in chunks
        $userQuery->chunk(self::CHUNK_SIZE, function ($users) use ($fcmService, $coupon, &$totalUsers, &$totalSuccess, &$totalFailure) {
            $totalUsers += $users->count();

            foreach ($users as $user) {
                // Dispatch individual job for each user
                // This allows better retry handling and load distribution
                SendCouponToUserJob::dispatch(
                    $user->id,
                    $coupon->id,
                    $this->messageTemplate
                )->onQueue('notifications');

                $totalSuccess++; // Count dispatched jobs
            }

            Log::info('SendCouponBulkJob: Chunk processed', [
                'coupon_id' => $coupon->id,
                'chunk_size' => $users->count(),
                'total_processed' => $totalUsers,
            ]);
        });

        Log::info('SendCouponBulkJob: Completed', array_merge($logContext, [
            'total_users' => $totalUsers,
            'jobs_dispatched' => $totalSuccess,
        ]));
    }

    /**
     * Build query for target users based on segment or explicit list
     */
    private function buildUserQuery(Coupon $coupon)
    {
        // If explicit user IDs provided
        if ($this->userIds && !empty($this->userIds)) {
            return User::whereIn('id', $this->userIds)
                ->where('is_active', true);
        }

        // If segment key provided
        if ($this->segmentKey) {
            return $this->buildSegmentQuery($this->segmentKey);
        }

        // For TARGETED eligibility, get from coupon_target_users
        if ($coupon->eligibility_type === Coupon::ELIGIBILITY_TARGETED) {
            return User::whereIn('id', function ($query) use ($coupon) {
                $query->select('user_id')
                    ->from('coupon_target_users')
                    ->where('coupon_id', $coupon->id)
                    ->where('notified', false);
            })->where('is_active', true);
        }

        // For ALL eligibility, get all active users with devices
        return User::where('is_active', true)
            ->whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('user_devices')
                    ->where('is_active', true);
            });
    }

    /**
     * Build query for segment-based targeting
     */
    private function buildSegmentQuery(string $segmentKey)
    {
        return match ($segmentKey) {
            Coupon::SEGMENT_INACTIVE_30_DAYS => $this->getInactive30DaysUsers(),
            Coupon::SEGMENT_NEW_USER => $this->getNewUsers(),
            Coupon::SEGMENT_HIGH_VALUE => $this->getHighValueUsers(),
            default => User::where('is_active', true)->limit(0), // Empty query for unknown segments
        };
    }

    /**
     * Get users inactive for 30 days
     */
    private function getInactive30DaysUsers()
    {
        $thirtyDaysAgo = now()->subDays(30);

        return User::where('is_active', true)
            ->where('user_type', 'customer')
            ->whereNotIn('id', function ($query) use ($thirtyDaysAgo) {
                $query->select('customer_id')
                    ->from('trip_requests')
                    ->where('current_status', 'completed')
                    ->where('created_at', '>=', $thirtyDaysAgo)
                    ->distinct();
            })
            ->whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('user_devices')
                    ->where('is_active', true);
            });
    }

    /**
     * Get users registered in last 7 days
     */
    private function getNewUsers()
    {
        return User::where('is_active', true)
            ->where('user_type', 'customer')
            ->where('created_at', '>=', now()->subDays(7))
            ->whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('user_devices')
                    ->where('is_active', true);
            });
    }

    /**
     * Get users with 10+ completed rides
     */
    private function getHighValueUsers()
    {
        return User::where('is_active', true)
            ->where('user_type', 'customer')
            ->whereIn('id', function ($query) {
                $query->select('customer_id')
                    ->from('trip_requests')
                    ->where('current_status', 'completed')
                    ->groupBy('customer_id')
                    ->havingRaw('COUNT(*) >= 10');
            })
            ->whereIn('id', function ($query) {
                $query->select('user_id')
                    ->from('user_devices')
                    ->where('is_active', true);
            });
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCouponBulkJob: Failed', [
            'coupon_id' => $this->couponId,
            'segment_key' => $this->segmentKey,
            'error' => $exception->getMessage(),
        ]);
    }
}
