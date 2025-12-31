<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Rateel OTP Service
 * 
 * External OTP verification service integration for customer signup.
 * API Documentation: https://documenter.getpostman.com/view/9924527/2sB2x6nsUP
 * 
 * This service handles:
 * - Sending OTP to phone numbers
 * - Verifying OTP codes
 * - Rate limiting and caching
 */
class RateelOtpService
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
     * Request timeout in seconds
     */
    protected int $timeout = 30;

    public function __construct()
    {
        $this->baseUrl = config('services.rateel_otp.base_url', 'https://otp.rateel.app/api');
        $this->apiToken = config('services.rateel_otp.api_token', '');
    }

    /**
     * Send OTP to a phone number
     * 
     * @param string $phone Phone number with country code (e.g., +201234567890)
     * @param string|null $otp Optional OTP to send (if null, service generates one)
     * @return array ['success' => bool, 'message' => string, 'otp_id' => string|null]
     */
    public function sendOtp(string $phone, ?string $otp = null): array
    {
        try {
            // Clean phone number - remove spaces and ensure proper format
            $phone = $this->formatPhoneNumber($phone);

            // Check rate limiting
            $rateLimitKey = "rateel_otp_rate_limit:{$phone}";
            $attempts = Cache::get($rateLimitKey, 0);
            
            if ($attempts >= 5) {
                return [
                    'success' => false,
                    'message' => 'Too many OTP requests. Please try again later.',
                    'otp_id' => null,
                ];
            }

            // Build request payload
            $payload = [
                'phone' => $phone,
            ];

            // If custom OTP is provided, include it (for backward compatibility)
            if ($otp !== null) {
                $payload['otp'] = $otp;
            }

            Log::info('RateelOTP: Sending OTP request', [
                'phone' => $this->maskPhone($phone),
                'has_custom_otp' => $otp !== null,
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/otp/send", $payload);

            // Increment rate limit
            Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(5));

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('RateelOTP: OTP sent successfully', [
                    'phone' => $this->maskPhone($phone),
                    'response_code' => $response->status(),
                ]);

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'OTP sent successfully',
                    'otp_id' => $data['otp_id'] ?? $data['id'] ?? null,
                    'expires_in' => $data['expires_in'] ?? 300,
                ];
            }

            $errorData = $response->json();
            Log::warning('RateelOTP: Failed to send OTP', [
                'phone' => $this->maskPhone($phone),
                'status' => $response->status(),
                'error' => $errorData['message'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'message' => $errorData['message'] ?? 'Failed to send OTP',
                'otp_id' => null,
            ];

        } catch (\Exception $e) {
            Log::error('RateelOTP: Exception while sending OTP', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to OTP service',
                'otp_id' => null,
            ];
        }
    }

    /**
     * Verify OTP code
     * 
     * @param string $phone Phone number with country code
     * @param string $otp The OTP code to verify
     * @param string|null $otpId Optional OTP ID from send response
     * @return array ['success' => bool, 'message' => string, 'verified' => bool]
     */
    public function verifyOtp(string $phone, string $otp, ?string $otpId = null): array
    {
        try {
            $phone = $this->formatPhoneNumber($phone);

            $payload = [
                'phone' => $phone,
                'otp' => $otp,
            ];

            if ($otpId !== null) {
                $payload['otp_id'] = $otpId;
            }

            Log::info('RateelOTP: Verifying OTP', [
                'phone' => $this->maskPhone($phone),
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/otp/verify", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $verified = $data['verified'] ?? $data['success'] ?? false;

                Log::info('RateelOTP: OTP verification result', [
                    'phone' => $this->maskPhone($phone),
                    'verified' => $verified,
                ]);

                // Clear rate limit on successful verification
                if ($verified) {
                    Cache::forget("rateel_otp_rate_limit:{$phone}");
                }

                return [
                    'success' => true,
                    'message' => $verified ? 'OTP verified successfully' : 'Invalid OTP',
                    'verified' => $verified,
                ];
            }

            $errorData = $response->json();
            Log::warning('RateelOTP: OTP verification failed', [
                'phone' => $this->maskPhone($phone),
                'status' => $response->status(),
                'error' => $errorData['message'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'message' => $errorData['message'] ?? 'OTP verification failed',
                'verified' => false,
            ];

        } catch (\Exception $e) {
            Log::error('RateelOTP: Exception while verifying OTP', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to connect to OTP service',
                'verified' => false,
            ];
        }
    }

    /**
     * Resend OTP to a phone number
     * 
     * @param string $phone Phone number
     * @param string|null $otpId Previous OTP ID
     * @return array
     */
    public function resendOtp(string $phone, ?string $otpId = null): array
    {
        // Resend is essentially the same as send
        return $this->sendOtp($phone);
    }

    /**
     * Format phone number to expected format
     * 
     * @param string $phone
     * @return string
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure it starts with + for international format
        if (!str_starts_with($phone, '+')) {
            // Assume Egypt if no country code (common case)
            if (str_starts_with($phone, '0')) {
                $phone = '+2' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Mask phone number for logging (privacy)
     * 
     * @param string $phone
     * @return string
     */
    protected function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 4) {
            return '****';
        }
        return substr($phone, 0, 4) . str_repeat('*', $length - 8) . substr($phone, -4);
    }

    /**
     * Check if the service is configured and available
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiToken);
    }

    /**
     * Get service status/health
     * 
     * @return array
     */
    public function getStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'available' => false,
                'message' => 'Rateel OTP service not configured',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiToken,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/status");

            return [
                'available' => $response->successful(),
                'message' => $response->successful() ? 'Service available' : 'Service unavailable',
            ];
        } catch (\Exception $e) {
            return [
                'available' => false,
                'message' => 'Cannot connect to service',
            ];
        }
    }
}
