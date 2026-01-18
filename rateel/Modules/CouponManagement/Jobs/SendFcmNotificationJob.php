<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Service\FcmService;

/**
 * Job to send FCM notifications asynchronously with retry logic
 *
 * Created: 2026-01-14 - Performance optimization to handle FCM retries in background
 * Prevents blocking HTTP requests with exponential backoff delays
 */
class SendFcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Calculate the number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        // Exponential backoff: 5s, 15s, 30s
        return [5, 15, 30];
    }

    public function __construct(
        protected string $type, // 'token', 'tokens', 'topic', 'user'
        protected string|array $target, // token, array of tokens, topic name, or user_id
        protected array $notification,
        protected array $data = []
    ) {
        $this->onQueue('notifications');
    }

    public function handle(FcmService $fcmService): void
    {
        Log::info('SendFcmNotificationJob: Processing', [
            'type' => $this->type,
            'attempt' => $this->attempts(),
        ]);

        $result = match ($this->type) {
            'token' => $fcmService->sendToTokenDirect($this->target, $this->notification, $this->data),
            'tokens' => $fcmService->sendToTokensDirect($this->target, $this->notification, $this->data),
            'topic' => $fcmService->sendToTopicDirect($this->target, $this->notification, $this->data),
            'user' => $fcmService->sendToUserDirect($this->target, $this->notification, $this->data),
            default => ['success' => false, 'error' => 'Invalid type'],
        };

        if (!$result['success'] && !($result['should_deactivate'] ?? false)) {
            // If failed and not due to invalid token, throw exception to trigger retry
            throw new \Exception($result['error'] ?? 'FCM send failed');
        }

        Log::info('SendFcmNotificationJob: Completed', [
            'type' => $this->type,
            'success' => $result['success'] ?? false,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendFcmNotificationJob: Failed permanently', [
            'type' => $this->type,
            'target' => is_array($this->target) ? count($this->target) . ' tokens' : $this->target,
            'error' => $exception->getMessage(),
        ]);
    }
}
