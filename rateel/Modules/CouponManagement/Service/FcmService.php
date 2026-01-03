<?php

declare(strict_types=1);

namespace Modules\CouponManagement\Service;

use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Modules\CouponManagement\Entities\UserDevice;

class FcmService
{
    private Client $httpClient;
    private ?string $accessToken = null;
    private ?int $tokenExpiresAt = null;

    // FCM batch sending limits
    private const MAX_TOKENS_PER_REQUEST = 500;
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Get OAuth2 access token for FCM HTTP v1 API
     */
    private function getAccessToken(): string
    {
        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $credentials = $this->getServiceAccountCredentials();

        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
        $serviceAccount = new ServiceAccountCredentials($scopes, $credentials);

        $token = $serviceAccount->fetchAuthToken();

        $this->accessToken = $token['access_token'];
        $this->tokenExpiresAt = time() + ($token['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    /**
     * Get service account credentials from config
     */
    private function getServiceAccountCredentials(): array
    {
        // Option 1: JSON file path
        $jsonPath = config('services.firebase.credentials_path');
        if ($jsonPath && file_exists($jsonPath)) {
            return json_decode(file_get_contents($jsonPath), true);
        }

        // Option 2: Individual env vars
        return [
            'type' => 'service_account',
            'project_id' => config('services.firebase.project_id'),
            'private_key_id' => config('services.firebase.private_key_id'),
            'private_key' => str_replace('\\n', "\n", config('services.firebase.private_key', '')),
            'client_email' => config('services.firebase.client_email'),
            'client_id' => config('services.firebase.client_id'),
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ];
    }

    /**
     * Get FCM endpoint URL
     */
    private function getFcmEndpoint(): string
    {
        $projectId = config('services.firebase.project_id');
        return "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    }

    /**
     * Send notification to a single token
     *
     * @param string $token FCM token
     * @param array $notification ['title' => string, 'body' => string, 'image' => ?string]
     * @param array $data Custom data payload
     * @return array ['success' => bool, 'message_id' => ?string, 'error' => ?string, 'should_deactivate' => bool]
     */
    public function sendToToken(string $token, array $notification, array $data = []): array
    {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $notification['title'],
                    'body' => $notification['body'],
                ],
                'data' => $this->prepareData($data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'coupons',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        if (isset($notification['image'])) {
            $message['message']['notification']['image'] = $notification['image'];
        }

        return $this->sendRequest($message);
    }

    /**
     * Send notification to multiple tokens
     *
     * @param array $tokens Array of FCM tokens
     * @param array $notification ['title' => string, 'body' => string]
     * @param array $data Custom data payload
     * @return array ['success_count' => int, 'failure_count' => int, 'invalid_tokens' => array]
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): array
    {
        $results = [
            'success_count' => 0,
            'failure_count' => 0,
            'invalid_tokens' => [],
            'details' => [],
        ];

        // Chunk tokens to respect FCM limits
        $chunks = array_chunk($tokens, self::MAX_TOKENS_PER_REQUEST);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $token) {
                $result = $this->sendToToken($token, $notification, $data);

                $results['details'][$token] = $result;

                if ($result['success']) {
                    $results['success_count']++;
                } else {
                    $results['failure_count']++;

                    if ($result['should_deactivate']) {
                        $results['invalid_tokens'][] = $token;
                    }
                }
            }
        }

        // Deactivate invalid tokens
        if (!empty($results['invalid_tokens'])) {
            $this->deactivateTokens($results['invalid_tokens']);
        }

        Log::info('FcmService: Batch send completed', [
            'total_tokens' => count($tokens),
            'success_count' => $results['success_count'],
            'failure_count' => $results['failure_count'],
            'invalid_count' => count($results['invalid_tokens']),
        ]);

        return $results;
    }

    /**
     * Send notification to a topic
     *
     * @param string $topic Topic name
     * @param array $notification
     * @param array $data
     * @return array
     */
    public function sendToTopic(string $topic, array $notification, array $data = []): array
    {
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $notification['title'],
                    'body' => $notification['body'],
                ],
                'data' => $this->prepareData($data),
            ],
        ];

        return $this->sendRequest($message);
    }

    /**
     * Send FCM request with retry logic
     */
    private function sendRequest(array $message): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < self::MAX_RETRIES) {
            try {
                $response = $this->httpClient->post($this->getFcmEndpoint(), [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->getAccessToken(),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $message,
                ]);

                $body = json_decode($response->getBody()->getContents(), true);

                return [
                    'success' => true,
                    'message_id' => $body['name'] ?? null,
                    'error' => null,
                    'should_deactivate' => false,
                ];

            } catch (GuzzleException $e) {
                $lastError = $e;
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
                $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';

                // Check for unregistered/invalid token errors
                if ($this->isInvalidTokenError($statusCode, $errorBody)) {
                    return [
                        'success' => false,
                        'message_id' => null,
                        'error' => 'Invalid token',
                        'should_deactivate' => true,
                    ];
                }

                // Retry on server errors or rate limits
                if (in_array($statusCode, [429, 500, 502, 503, 504])) {
                    $attempts++;
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempts); // Exponential backoff
                    continue;
                }

                // Don't retry on client errors
                Log::error('FcmService: Send failed', [
                    'status_code' => $statusCode,
                    'error' => $errorBody,
                ]);

                return [
                    'success' => false,
                    'message_id' => null,
                    'error' => $errorBody,
                    'should_deactivate' => false,
                ];
            }
        }

        Log::error('FcmService: Max retries exceeded', [
            'error' => $lastError?->getMessage(),
        ]);

        return [
            'success' => false,
            'message_id' => null,
            'error' => 'Max retries exceeded: ' . $lastError?->getMessage(),
            'should_deactivate' => false,
        ];
    }

    /**
     * Check if error indicates invalid/unregistered token
     */
    private function isInvalidTokenError(int $statusCode, string $errorBody): bool
    {
        if ($statusCode !== 400 && $statusCode !== 404) {
            return false;
        }

        $invalidErrors = [
            'UNREGISTERED',
            'INVALID_ARGUMENT',
            'NOT_FOUND',
            'registration-token-not-registered',
            'invalid-registration-token',
        ];

        foreach ($invalidErrors as $error) {
            if (str_contains($errorBody, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prepare data payload (all values must be strings)
     */
    private function prepareData(array $data): array
    {
        return array_map(fn($value) => is_string($value) ? $value : json_encode($value), $data);
    }

    /**
     * Deactivate invalid tokens in database
     */
    private function deactivateTokens(array $tokens): void
    {
        UserDevice::whereIn('fcm_token', $tokens)
            ->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => 'invalid_token',
            ]);

        Log::info('FcmService: Deactivated invalid tokens', [
            'count' => count($tokens),
        ]);
    }

    /**
     * Get active tokens for a user
     */
    public function getUserTokens(string $userId): array
    {
        return UserDevice::forUser($userId)
            ->active()
            ->pluck('fcm_token')
            ->toArray();
    }

    /**
     * Send notification to all user's devices
     */
    public function sendToUser(string $userId, array $notification, array $data = []): array
    {
        $tokens = $this->getUserTokens($userId);

        if (empty($tokens)) {
            Log::info('FcmService: No active tokens for user', ['user_id' => $userId]);
            return [
                'success' => false,
                'error' => 'No active devices',
                'success_count' => 0,
                'failure_count' => 0,
            ];
        }

        return $this->sendToTokens($tokens, $notification, $data);
    }
}
