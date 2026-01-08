<?php

namespace App\Http\Controllers\Api\V2\Driver;

use App\Http\Controllers\Controller;
use Modules\UserManagement\Entities\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * Driver Authentication Controller (V2)
 * 
 * Handles login for approved drivers and returning drivers in onboarding.
 */
class DriverAuthController extends Controller
{
    /**
     * Login for approved drivers
     * POST /api/v2/driver/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:20',
            'password' => 'required|string',
            'device_id' => 'nullable|string|max:100',
            'fcm_token' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => translate('Validation failed'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        // Normalize phone
        $phone = $this->normalizePhone($request->phone);

        // Find driver
        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        // Always return same error message to prevent enumeration
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => translate('Invalid credentials'),
            ], 401);
        }
        
        // Verify password using bcrypt
        $storedPassword = $driver->password;

        if (!$storedPassword) {
            Log::warning('Login attempt with driver that has no password', [
                'driver_id' => $driver->id,
                'phone' => $driver->phone,
            ]);
            return response()->json([
                'success' => false,
                'message' => translate('Invalid credentials'),
            ], 401);
        }

        // Verify password with bcrypt
        if (!Hash::check($request->password, $storedPassword)) {
            Log::info('Failed login attempt for driver', [
                'driver_id' => $driver->id,
                'phone' => $this->maskPhone($driver->phone),
            ]);
            return response()->json([
                'success' => false,
                'message' => translate('Invalid credentials'),
            ], 401);
        }

        // Check if driver is approved
        if ($driver->is_approved) {
            // Update device info
            if ($request->device_id) {
                $driver->update(['device_fingerprint' => $request->device_id]);
            }

            // Update FCM token if provided
            if ($request->fcm_token) {
                // TODO: Store FCM token in appropriate table
                // $driver->fcmTokens()->updateOrCreate(['device_id' => $request->device_id], ['token' => $request->fcm_token]);
            }

            // Create driver token (full access)
            $token = $driver->createToken('driver-app', ['driver'])->accessToken;
            $tokenExpiresAt = now()->addDays(config('driver_onboarding.session.driver_token_ttl_days', 30));

            Log::info('Driver logged in', [
                'driver_id' => $driver->id,
                'phone' => $this->maskPhone($driver->phone),
            ]);

            return response()->json([
                'success' => true,
                'message' => translate('Login successful'),
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'token_expires_at' => $tokenExpiresAt->toIso8601String(),
                    'token_scope' => 'driver',
                    'driver' => [
                        'id' => $driver->id,
                        'first_name' => $driver->first_name,
                        'last_name' => $driver->last_name,
                        'phone' => $driver->phone,
                        'email' => $driver->email,
                        'profile_image' => $driver->profile_image ?? null,
                        'rating' => $driver->receivedReviews()->avg('rating') ?? 0,
                        'is_online' => $driver->is_active ?? false,
                        'is_approved' => true,
                    ],
                ],
            ]);
        }

        // Driver not yet approved - return onboarding token
        $onboardingState = $driver->onboarding_state ?? 'otp_pending';
        
        // Create onboarding token if doesn't exist
        $token = $driver->createToken('driver-onboarding', ['onboarding'])->accessToken;
        $tokenExpiresAt = now()->addHours(config('driver_onboarding.session.token_ttl_hours', 48));

        return response()->json([
            'success' => true,
            'message' => translate('Your application is under review'),
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'token_expires_at' => $tokenExpiresAt->toIso8601String(),
                'token_scope' => 'onboarding',
                'is_approved' => false,
                'onboarding_state' => $onboardingState,
                'next_step' => $this->getNextStep($onboardingState),
                'state_version' => $driver->onboarding_state_version ?? 1,
            ],
        ]);
    }

    /**
     * Forgot Password - Request OTP
     * POST /api/v2/driver/auth/forgot-password
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => translate('Validation failed'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        // Find driver
        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            // Don't reveal if user exists or not (security)
            return response()->json([
                'success' => true,
                'message' => translate('If this phone number is registered, you will receive a verification code.'),
            ]);
        }

        // Generate OTP and store in cache
        $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $cacheKey = 'forgot_password_otp:' . $phone;

        Cache::put($cacheKey, [
            'otp' => $otp,
            'phone' => $phone,
            'attempts' => 0,
            'created_at' => now(),
        ], now()->addMinutes(5));

        // Send OTP via SMS (using BeOn service)
        try {
            $beonService = app(\App\Services\BeOnOtpService::class);
            $result = $beonService->sendOtp($phone, $otp);

            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to send OTP');
            }
        } catch (\Exception $e) {
            Log::error('Failed to send forgot password OTP', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => translate('Failed to send verification code. Please try again.'),
            ], 500);
        }

        Log::info('Forgot password OTP sent', [
            'phone' => $this->maskPhone($phone),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Verification code sent to your phone'),
            'data' => [
                'phone_masked' => $this->maskPhone($phone),
                'expires_in_seconds' => 300,
            ],
        ]);
    }

    /**
     * Verify Forgot Password OTP
     * POST /api/v2/driver/auth/verify-forgot-password-otp
     */
    public function verifyForgotPasswordOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:20',
            'otp' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => translate('Validation failed'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $cacheKey = 'forgot_password_otp:' . $phone;
        $data = Cache::get($cacheKey);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => translate('Verification code expired or not found. Please request a new one.'),
            ], 400);
        }

        // Check attempts
        if ($data['attempts'] >= 3) {
            Cache::forget($cacheKey);
            return response()->json([
                'success' => false,
                'message' => translate('Too many failed attempts. Please request a new verification code.'),
            ], 429);
        }

        // Verify OTP
        if ($data['otp'] !== $request->otp) {
            $data['attempts']++;
            Cache::put($cacheKey, $data, now()->addMinutes(5));

            return response()->json([
                'success' => false,
                'message' => translate('Invalid verification code. Please try again.'),
                'data' => [
                    'attempts_remaining' => 3 - $data['attempts'],
                ],
            ], 401);
        }

        // OTP verified - generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetCacheKey = 'password_reset_token:' . $resetToken;

        Cache::put($resetCacheKey, [
            'phone' => $phone,
            'verified_at' => now(),
        ], now()->addMinutes(15));

        // Clear OTP cache
        Cache::forget($cacheKey);

        Log::info('Forgot password OTP verified', [
            'phone' => $this->maskPhone($phone),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Verification successful'),
            'data' => [
                'reset_token' => $resetToken,
                'expires_in_seconds' => 900,
            ],
        ]);
    }

    /**
     * Reset Password
     * POST /api/v2/driver/auth/reset-password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reset_token' => 'required|string|size:64',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/^[a-zA-Z0-9]+$/',
            ],
        ], [
            'password.regex' => translate('Password can only contain letters and numbers. No special characters allowed.'),
            'password.min' => translate('Password must be at least 8 characters long.'),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => translate('Validation failed'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $resetCacheKey = 'password_reset_token:' . $request->reset_token;
        $data = Cache::get($resetCacheKey);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => translate('Reset token expired or invalid. Please start over.'),
            ], 400);
        }

        // Find driver
        $driver = User::where('phone', $data['phone'])
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            Cache::forget($resetCacheKey);
            return response()->json([
                'success' => false,
                'message' => translate('Driver not found'),
            ], 404);
        }

        // Update password
        $driver->update([
            'password' => Hash::make($request->password),
        ]);

        // Clear reset token
        Cache::forget($resetCacheKey);

        Log::info('Password reset successful', [
            'driver_id' => $driver->id,
            'phone' => $this->maskPhone($driver->phone),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Password reset successfully. You can now login with your new password.'),
        ]);
    }

    /**
     * Get next step based on onboarding state
     */
    private function getNextStep(string $state): string
    {
        return match ($state) {
            'otp_pending' => 'verify_otp',
            'otp_verified' => 'set_password',
            'password_set' => 'submit_profile',
            'profile_complete' => 'select_vehicle',
            'vehicle_selected' => 'upload_documents',
            'documents_pending' => 'submit_for_review',
            'pending_approval' => 'wait_for_approval',
            'approved' => 'go_online',
            default => 'contact_support',
        };
    }

    /**
     * Normalize phone number
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '0')) {
                $phone = '+20' . substr($phone, 1);
            } elseif (str_starts_with($phone, '20')) {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhone(string $phone): string
    {
        $length = strlen($phone);
        if ($length <= 6) {
            return $phone;
        }

        $visible = 4;
        $masked = str_repeat('*', $length - $visible - 4);
        return substr($phone, 0, 4) . $masked . substr($phone, -$visible);
    }
}
