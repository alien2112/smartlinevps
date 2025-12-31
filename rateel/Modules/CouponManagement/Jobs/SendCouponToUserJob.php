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
use Modules\CouponManagement\Service\FcmService;
use Modules\UserManagement\Entities\User;

class SendCouponToUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 60;

    public function __construct(
        public string $userId,
        public string $couponId,
        public ?string $messageTemplate = null
    ) {
        $this->onQueue('notifications');
    }

    public function handle(FcmService $fcmService): void
    {
        $logContext = [
            'user_id' => $this->userId,
            'coupon_id' => $this->couponId,
            'job_attempt' => $this->attempts(),
        ];

        Log::info('SendCouponToUserJob: Starting', $logContext);

        $user = User::find($this->userId);
        $coupon = Coupon::find($this->couponId);

        if (!$user || !$coupon) {
            Log::warning('SendCouponToUserJob: User or coupon not found', $logContext);
            return;
        }

        // Build notification
        $notification = $this->buildNotification($coupon);
        $data = $this->buildData($coupon);

        // Send to user's devices
        $result = $fcmService->sendToUser($this->userId, $notification, $data);

        if ($result['success_count'] > 0) {
            // Mark as notified in coupon_target_users if exists
            CouponTargetUser::where('coupon_id', $this->couponId)
                ->where('user_id', $this->userId)
                ->update([
                    'notified' => true,
                    'notified_at' => now(),
                ]);

            Log::info('SendCouponToUserJob: Notification sent', array_merge($logContext, [
                'success_count' => $result['success_count'],
                'failure_count' => $result['failure_count'],
            ]));
        } else {
            Log::warning('SendCouponToUserJob: No successful sends', array_merge($logContext, $result));
        }
    }

    private function buildNotification(Coupon $coupon): array
    {
        if ($this->messageTemplate) {
            return [
                'title' => 'You have a new coupon!',
                'body' => str_replace(
                    ['{code}', '{value}', '{name}'],
                    [$coupon->code, $this->formatValue($coupon), $coupon->name],
                    $this->messageTemplate
                ),
            ];
        }

        $valueText = $this->formatValue($coupon);

        return [
            'title' => '🎉 New Coupon Available!',
            'body' => "Use code {$coupon->code} to get {$valueText} off your next ride!",
        ];
    }

    private function formatValue(Coupon $coupon): string
    {
        return match ($coupon->type) {
            Coupon::TYPE_PERCENT => "{$coupon->value}%",
            Coupon::TYPE_FIXED => "\${$coupon->value}",
            Coupon::TYPE_FREE_RIDE_CAP => "FREE RIDE (up to \${$coupon->max_discount})",
        };
    }

    private function buildData(Coupon $coupon): array
    {
        return [
            'type' => 'coupon',
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'action' => 'view_coupon',
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCouponToUserJob: Failed', [
            'user_id' => $this->userId,
            'coupon_id' => $this->couponId,
            'error' => $exception->getMessage(),
        ]);
    }
}
