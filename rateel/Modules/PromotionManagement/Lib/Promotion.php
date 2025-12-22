<?php


use Illuminate\Support\Facades\Http;
use Modules\UserManagement\Entities\AppNotification;

if (!function_exists('sendDeviceNotification')) {
    function sendDeviceNotification($fcm_token, $title, $description, $status, $image = null, $ride_request_id = null, $type = null, $action = null, $user_id = null, $user_name = null, array $notificationData = []): bool|string
    {
        $notification = null;
        if ($user_id) {
            $notification = new AppNotification();
            $notification->user_id = $user_id;
            $notification->ride_request_id = $ride_request_id ?? null;
            $notification->title = $title ?? 'Title Not Found';
            $notification->description = $description ?? 'Description Not Found';
            $notification->type = $type ?? null;
            $notification->action = $action ?? null;
            $notification->save();
        }
        $image = asset('storage/app/public/push-notification') . '/' . $image;
        $rewardType = array_key_exists('reward_type', $notificationData) ? $notificationData['reward_type'] : null;
        $rewardAmount = array_key_exists('reward_amount', $notificationData) ? $notificationData['reward_amount'] : 0;
        $nextLevel = array_key_exists('next_level', $notificationData) ? $notificationData['next_level'] : null;

        $postData = [
            'message' => [
                'token' => $fcm_token,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    'status' => (string)$status,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "user_name" => (string)$user_name,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$image,
                    "action" => (string)$action,
                    "reward_type" => (string)$rewardType,
                    "reward_amount" => (string)$rewardAmount,
                    "next_level" => (string)$nextLevel,
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride"
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$image,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channel_id" => "hexaride",
                        "sound" => "notification.wav",
                        "icon" => "notification_icon",
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}

if (!function_exists('sendTopicNotification')) {
    function sendTopicNotification($topic, $title, $description, $image = null, $ride_request_id = null, $type = null, $sentBy = null, $tripReferenceId = null,  $route = null, ): bool|string
    {

        $image = asset('storage/app/public/push-notification') . '/' . $image;
        $postData = [
            'message' => [
                'topic' => $topic,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$image,
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride",
                    "sent_by" => (string)$sentBy,
                    "trip_reference_id" => (string)$tripReferenceId,
                    "route" => (string)$route,
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$image,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channelId" => "hexaride"
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}

/**
 * @param string $url
 * @param string $postdata
 * @param array $header
 * @return bool|string
 */
function sendCurlRequest(string $url, string $postdata, array $header): string|bool
{
    $ch = curl_init();
    $timeout = 120;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    // Get URL content
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function sendNotificationToHttp(array|null $data): bool|string|null
{
    // Cache the server_key config to avoid repeated database lookups
    static $cachedServerKey = null;
    static $cachedKey = null;
    
    if ($cachedServerKey === null) {
        $cachedServerKey = businessConfig('server_key')?->value ?? false;
    }
    
    if (empty($cachedServerKey) || !is_string($cachedServerKey)) {
        if (config('app.debug')) {
            \Log::warning('FCM server_key not configured; skipping push notification');
        }
        return false;
    }

    if ($cachedKey === null) {
        $cachedKey = json_decode($cachedServerKey);
    }
    
    $key = $cachedKey;
    if (!is_object($key) || empty($key->project_id) || empty($key->client_email) || empty($key->private_key)) {
        if (config('app.debug')) {
            \Log::warning('FCM server_key invalid JSON or missing required fields; skipping push notification', [
                'has_project_id' => is_object($key) && isset($key->project_id),
                'has_client_email' => is_object($key) && isset($key->client_email),
                'has_private_key' => is_object($key) && isset($key->private_key),
            ]);
        }
        return false;
    }

    $access = getAccessToken($key);
    if (!is_array($access) || empty($access['status']) || empty($access['data'])) {
        if (config('app.debug')) {
            \Log::warning('Unable to obtain FCM access token; skipping push notification', [
                'error' => is_array($access) ? ($access['data'] ?? null) : $access,
            ]);
        }
        return false;
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . $key->project_id . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . $access['data'],
            'Content-Type' => 'application/json',
        ];
        try {
            // Use timeout to prevent long waits
            return Http::timeout(5)->withHeaders($headers)->post($url, $data);
        } catch (\Exception $exception) {
            return false;
        }
}

function getAccessToken($key): array|string
{
    if (!is_object($key) || empty($key->client_email) || empty($key->private_key)) {
        return [
            'status' => false,
            'data' => ['message' => 'Invalid server_key credentials']
        ];
    }

    // Cache key based on project_id to support multiple Firebase projects
    $cacheKey = 'fcm_access_token_' . ($key->project_id ?? 'default');
    
    // Try to get cached token first (cache for 50 minutes, token valid for 60)
    $cachedToken = \Illuminate\Support\Facades\Cache::get($cacheKey);
    if ($cachedToken) {
        return [
            'status' => true,
            'data' => $cachedToken
        ];
    }

    $jwtToken = [
        'iss' => $key->client_email,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => time() + 3600,
        'iat' => time(),
    ];
    $jwtHeader = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtPayload = base64_encode(json_encode($jwtToken));
    $unsignedJwt = $jwtHeader . '.' . $jwtPayload;
    openssl_sign($unsignedJwt, $signature, $key->private_key, OPENSSL_ALGO_SHA256);
    $jwt = $unsignedJwt . '.' . base64_encode($signature);

    $response = Http::timeout(10)->asForm()->post('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    if ($response->failed()) {
        return [
            'status' => false,
            'data' => $response->json()
        ];

    }
    
    $accessToken = $response->json('access_token');
    
    // Cache the token for 50 minutes (token is valid for 60 minutes)
    \Illuminate\Support\Facades\Cache::put($cacheKey, $accessToken, now()->addMinutes(50));
    
    return [
        'status' => true,
        'data' => $accessToken
    ];
}
