<?php

namespace App\Http\Controllers\Api\V2\Driver;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        if (!$driver || !Hash::check($request->password, $driver->password)) {
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

        return response()->json([
            'success' => true,
            'message' => translate('Your application is under review'),
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'token_scope' => 'onboarding',
                'is_approved' => false,
                'onboarding_state' => $onboardingState,
                'next_step' => $this->getNextStep($onboardingState),
            ],
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
