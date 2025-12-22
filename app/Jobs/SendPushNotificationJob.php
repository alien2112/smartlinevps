<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Modules\UserManagement\Entities\AppNotification;

class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    private const MAX_TOKENS_PER_BATCH = 500;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected $notification,
        protected $notify = null)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $recipients = $this->normalizeRecipients();

        if (empty($recipients)) {
            return;
        }

        $this->persistNotifications($recipients);

        $messaging = $this->getMessagingClient();

        if (!$messaging) {
            $this->sendViaHttp($recipients);
            return;
        }

        $this->sendMulticast($messaging, $recipients);
    }

    private function normalizeRecipients(): array
    {
        $recipients = [];

        if ($this->notify) {
            foreach ($this->notify as $user) {
                $userProfile = $user->user ?? $user;
                $token = $userProfile?->fcm_token ?? null;
                $isActive = $userProfile?->is_active ?? true;

                if (!$isActive || !$token) {
                    continue;
                }

                $recipients[$token] = [
                    'token' => $token,
                    'user_id' => $userProfile?->id ?? null,
                    'user_name' => $userProfile?->first_name ?? $userProfile?->name ?? null,
                ];
            }
        } elseif (isset($this->notification['user'])) {
            foreach ($this->notification['user'] as $user) {
                $token = $user['fcm_token'] ?? null;

                if (!$token) {
                    continue;
                }

                $recipients[$token] = [
                    'token' => $token,
                    'user_id' => $user['user_id'] ?? null,
                    'user_name' => $user['user_name'] ?? null,
                ];
            }
        }

        return $recipients;
    }

    private function persistNotifications(array $recipients): void
    {
        foreach ($recipients as $recipient) {
            if (empty($recipient['user_id'])) {
                continue;
            }

            AppNotification::create([
                'user_id' => $recipient['user_id'],
                'ride_request_id' => $this->notification['ride_request_id'] ?? null,
                'title' => $this->notification['title'] ?? 'Title Not Found',
                'description' => $this->notification['description'] ?? 'Description Not Found',
                'type' => $this->notification['type'] ?? null,
                'action' => $this->notification['action'] ?? null,
            ]);
        }
    }

    private function getMessagingClient()
    {
        if (!app()->bound('firebase.messaging')) {
            return null;
        }

        try {
            $messaging = app('firebase.messaging');
        } catch (\Throwable $exception) {
            Log::warning('Unable to resolve firebase.messaging client', ['error' => $exception->getMessage()]);
            return null;
        }

        return $messaging ?: null;
    }

    private function sendMulticast($messaging, array $recipients): void
    {
        $message = $this->buildCloudMessage();
        $tokens = array_keys($recipients);

        foreach (array_chunk($tokens, self::MAX_TOKENS_PER_BATCH) as $batch) {
            try {
                $report = $messaging->sendMulticast($message, $batch);
                $failedTokens = $this->extractFailedTokens($report);

                if (!empty($failedTokens)) {
                    $this->sendViaHttp($this->filterRecipientsByToken($recipients, $failedTokens));
                }
            } catch (\Throwable $exception) {
                Log::error('FCM multicast send failed; falling back to single HTTP sends', [
                    'error' => $exception->getMessage(),
                ]);

                $this->sendViaHttp($this->filterRecipientsByToken($recipients, $batch));
            }
        }
    }

    private function sendViaHttp(array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $message = $this->buildHttpPayload($recipient['token']);

            try {
                sendNotificationToHttp($message);
            } catch (\Throwable $exception) {
                Log::warning('FCM HTTP send failed', [
                    'token' => $recipient['token'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function buildCloudMessage(): CloudMessage
    {
        $imageUrl = $this->getImageUrl();
        $notification = FirebaseNotification::create(
            (string) ($this->notification['title'] ?? 'Notification'),
            (string) ($this->notification['description'] ?? ''),
            $imageUrl
        );

        return CloudMessage::new()
            ->withNotification($notification)
            ->withData($this->buildDataPayload($imageUrl));
    }

    private function buildHttpPayload(string $token): array
    {
        $imageUrl = $this->getImageUrl();
        $data = $this->buildDataPayload($imageUrl);

        return [
            'message' => [
                'token' => $token,
                'data' => array_merge($data, [
                    'title_loc_key' => (string) ($this->notification['ride_request_id'] ?? ''),
                    'body_loc_key' => (string) ($this->notification['type'] ?? ''),
                ]),
                'notification' => [
                    'title' => (string) ($this->notification['title'] ?? 'Notification'),
                    'body' => (string) ($this->notification['description'] ?? ''),
                    'image' => (string) ($imageUrl ?? ''),
                ],
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'hexaride',
                        'sound' => 'notification.wav',
                        'icon' => 'notification_icon',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'notification.wav',
                        ],
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ],
        ];
    }

    private function buildDataPayload(?string $imageUrl = null): array
    {
        return [
            'title' => (string) ($this->notification['title'] ?? 'Notification'),
            'body' => (string) ($this->notification['description'] ?? ''),
            'status' => (string) ($this->notification['status'] ?? ''),
            'ride_request_id' => (string) ($this->notification['ride_request_id'] ?? ''),
            'type' => (string) ($this->notification['type'] ?? ''),
            'action' => (string) ($this->notification['action'] ?? ''),
            'image' => (string) ($imageUrl ?? ''),
            'sound' => 'notification.wav',
            'android_channel_id' => 'hexaride',
        ];
    }

    private function extractFailedTokens($report): array
    {
        if (!method_exists($report, 'failures')) {
            return [];
        }

        return collect($report->failures() ?? [])
            ->map(function ($failure) {
                $target = method_exists($failure, 'target') ? $failure->target() : null;

                if (is_object($target) && method_exists($target, 'value')) {
                    return $target->value();
                }

                return $target ? (string) $target : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function filterRecipientsByToken(array $recipients, array $tokens): array
    {
        $filtered = [];

        foreach ($tokens as $token) {
            if (isset($recipients[$token])) {
                $filtered[$token] = $recipients[$token];
            }
        }

        return $filtered;
    }

    private function getImageUrl(): ?string
    {
        if (empty($this->notification['image'])) {
            return null;
        }

        return asset('storage/app/public/push-notification') . '/' . $this->notification['image'];
    }
}
