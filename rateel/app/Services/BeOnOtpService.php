<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * BeOn OTP Service
 * 
 * BeOn V3 API integration for SMS OTP messaging.
 * API Documentation: https://documenter.getpostman.com/view/9924527/2sB2x6nsUP
 * 
 * This service handles:
 * - Sending OTP via SMS
 * - Sending OTP via WhatsApp
 * - Checking message status
 */
class BeOnOtpService
{
    /**
     * API Base URL
     */
    protected string $baseUrl;

    /**
     * API Token for authentication
     */
    protected string $apiToken;

    /**
     * Default language
     */
    protected string $lang;

    /**
     * OTP length
     */
    protected int $otpLength;

    /**
     * Request timeout in seconds
     */
    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = config('services.beon_otp.base_url', 'https://v3.api.beon.chat/api/v3');
        $this->apiToken = config('services.beon_otp.api_token', '');
        $this->lang = config('services.beon_otp.lang', 'ar');
        $this->otpLength = config('services.beon_otp.otp_length', 6);
    }

    /**
     * Send OTP to a phone number via SMS
     * 
     * @param string $phone Phone number with country code (e.g., +201234567890)
     * @param string|null $customOtp Optional custom OTP code (if null, BeOn generates one)
     * @param string|null $name Optional sender name
     * @return array ['success' => bool, 'message' => string, 'data' => array|null]
     */
    public function sendOtp(string $phone, ?string $customOtp = null, ?string $name = 'SmartLine'): array
    {
        try {
            $phone = $this->formatPhoneNumber($phone);

            // Check rate limiting
            $rateLimitKey = "beon_otp_rate_limit:{$phone}";
            $attempts = Cache::get($rateLimitKey, 0);
            
            if ($attempts >= 5) {
                return [
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again later.',
                    'data' => null,
                ];
            }

            Log::info('BeOnOTP: Sending OTP request', [
                'phone' => $this->maskPhone($phone),
                'has_custom_otp' => $customOtp !== null,
            ]);

            // Build form data for BeOn API (multipart/form-data)
            $formData = [
                ['name' => 'phoneNumber', 'contents' => $phone],
                ['name' => 'name', 'contents' => $name],
                ['name' => 'type', 'contents' => 'sms'],
                ['name' => 'otp_length', 'contents' => (string) $this->otpLength],
                ['name' => 'lang', 'contents' => $this->lang],
                ['name' => 'reference', 'contents' => 'smartline_' . uniqid()],
            ];

            // If custom OTP provided, use it
            if ($customOtp !== null) {
                $formData[] = ['name' => 'custom_code', 'contents' => $customOtp];
            }

            $client = new Client();
            $response = $client->post("{$this->baseUrl}/messages/otp", [
                'headers' => [
                    'beon-token' => $this->apiToken,
                ],
                'multipart' => $formData,
                'http_errors' => false,
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // Increment rate limit
            Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(5));

            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($body, true);
                
                Log::info('BeOnOTP: OTP sent successfully', [
                    'phone' => $this->maskPhone($phone),
                    'response_code' => $statusCode,
                ]);

                return [
                    'success' => true,
                    'message' => 'OTP sent successfully',
                    'data' => $data,
                ];
            }

            $responseData = json_decode($body, true);
            Log::warning('BeOnOTP: Failed to send OTP', [
                'phone' => $this->maskPhone($phone),
                'status' => $statusCode,
                'response' => $body,
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP: ' . ($responseData['message'] ?? 'Unknown error'),
                'data' => null,
            ];

        } catch (\Exception $e) {
            Log::error('BeOnOTP: Exception while sending OTP', [
                'phone' => $this->maskPhone($phone ?? ''),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to OTP service',
                'data' => null,
            ];
        }
    }

    /**
     * Send SMS message (not OTP)
     * 
     * @param string $phone Phone number
     * @param string $message Message content
     * @param string|null $templateId Template ID if using template
     * @return array
     */
    public function sendSms(string $phone, string $message, ?string $templateId = null): array
    {
        try {
            $phone = $this->formatPhoneNumber($phone);

            Log::info('BeOnSMS: Sending SMS', [
                'phone' => $this->maskPhone($phone),
                'has_template' => $templateId !== null,
            ]);

            $formData = [
                ['name' => 'phoneNumber', 'contents' => $phone],
                ['name' => 'message', 'contents' => $message],
                ['name' => 'lang', 'contents' => $this->lang],
            ];

            $endpoint = $templateId 
                ? "{$this->baseUrl}/messages/sms/template"
                : "{$this->baseUrl}/messages/sms";

            if ($templateId) {
                $formData[] = ['name' => 'template_id', 'contents' => $templateId];
            }

            $client = new Client();
            $response = $client->post($endpoint, [
                'headers' => [
                    'beon-token' => $this->apiToken,
                ],
                'multipart' => $formData,
                'http_errors' => false,
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'data' => json_decode($body, true),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to send SMS',
                'data' => null,
            ];

        } catch (\Exception $e) {
            Log::error('BeOnSMS: Exception', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to SMS service',
                'data' => null,
            ];
        }
    }

    /**
     * Get message status
     * 
     * @param string $messageId Message ID from send response
     * @return array
     */
    public function getMessageStatus(string $messageId): array
    {
        try {
            $client = new Client();
            $response = $client->get("{$this->baseUrl}/message/status/{$messageId}", [
                'headers' => [
                    'beon-token' => $this->apiToken,
                ],
                'http_errors' => false,
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode >= 200 && $statusCode < 300) {
                return [
                    'success' => true,
                    'data' => json_decode($body, true),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get message status',
                'data' => null,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to connect to service',
                'data' => null,
            ];
        }
    }

    /**
     * Format phone number to expected format
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure it starts with + for international format
        if (!str_starts_with($phone, '+')) {
            // Assume Egypt if no country code
            if (str_starts_with($phone, '0')) {
                $phone = '+2' . $phone; // Egypt: 01xxx -> +201xxx
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Mask phone number for logging (privacy)
     */
    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 4) {
            return '****';
        }
        return substr($phone, 0, 5) . str_repeat('*', $length - 9) . substr($phone, -4);
    }

    /**
     * Check if the service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken);
    }
}
