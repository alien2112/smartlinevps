<?php


use Illuminate\Support\Facades\Http;
use Modules\UserManagement\Entities\AppNotification;

if (!function_exists('sendDeviceNotification')) {
    function sendDeviceNotification($fcm_token, $title, $description, $status, $image = null, $ride_request_id = null, $type = null, $action = null, $user_id = null, $user_name = null, array $notificationData = []): bool|string
    {
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
        $rewardType = $notification && array_key_exists('reward_type', $notificationData) ? $notificationData['reward_type'] : null;
        $rewardAmount = $notification && array_key_exists('reward_amount', $notificationData) ? $notificationData['reward_amount'] : 0;
        $nextLevel = $notification && array_key_exists('next_level', $notificationData) ? $notificationData['next_level'] : null;

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
    $key = json_decode(businessConfig('server_key')->value);
    if (getAccessToken($key)['status']) {
        $url = 'https://fcm.googleapis.com/v1/projects/' . $key->project_id . '/messages:send';
        $headers = [
            'Authorization' => 'Bearer ' . getAccessToken($key)['data'],
            'Content-Type' => 'application/json',
        ];
        try {
            return Http::withHeaders($headers)->post($url, $data);
        } catch (\Exception $exception) {
            return false;
        }
    } else {
        return false;
    }

}

function getAccessToken($key): array|string
{
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

    $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    if ($response->failed()) {
        return [
            'status' => false,
            'data' => $response->json()
        ];

    }
    return [
        'status' => true,
        'data' => $response->json('access_token')
    ];
}
