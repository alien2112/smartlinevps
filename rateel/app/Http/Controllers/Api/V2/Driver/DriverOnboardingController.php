<?php

namespace App\Http\Controllers\Api\V2\Driver;

use App\Http\Controllers\Controller;
use App\Services\Driver\DriverOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\ZoneManagement\Entities\Zone;

/**
 * Driver Onboarding Controller (V2)
 *
 * Handles the complete driver onboarding flow with:
 * - Secure OTP verification (no phone in query params)
 * - State machine enforcement
 * - Token-based authentication after OTP verification
 * - Consistent response format with next_step
 */
class DriverOnboardingController extends Controller
{
    protected DriverOnboardingService $onboardingService;

    public function __construct(DriverOnboardingService $onboardingService)
    {
        $this->onboardingService = $onboardingService;
    }

    /**
     * Start onboarding - submit phone and receive OTP
     * POST /api/v2/driver/onboarding/start
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:20',
            'device_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $result = $this->onboardingService->startOnboarding(
            $request->phone,
            $request->ip(),
            $request->device_id
        );

        if (!$result['success']) {
            $statusCode = $this->getErrorStatusCode($result['error']['code'] ?? 'UNKNOWN');
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Verification code sent to your phone'),
            'data' => $result['data'],
        ]);
    }

    /**
     * Verify OTP and receive onboarding token
     * POST /api/v2/driver/onboarding/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'onboarding_id' => 'required|string|min:19|max:24', // onb_ + 16 chars (flexible for future changes)
            'otp' => 'required|string|size:' . config('driver_onboarding.otp.length', 4),
            'device_id' => 'nullable|string|max:100',
        ], [
            'otp.size' => translate('Verification code must be :size digits', ['size' => config('driver_onboarding.otp.length', 4)]),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $result = $this->onboardingService->verifyOtp(
                $request->onboarding_id,
                $request->otp,
                $request->device_id
            );

            if (!$result['success']) {
                $statusCode = $this->getErrorStatusCode($result['error']['code'] ?? 'UNKNOWN');
                return response()->json([
                    'success' => false,
                    'message' => $result['error']['message'],
                    'error' => $result['error'],
                ], $statusCode);
            }
        } catch (\Exception $e) {
            \Log::error('Exception in verifyOtp controller', [
                'onboarding_id' => $request->onboarding_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => translate('An error occurred while verifying the code. Please try again.'),
                'error' => [
                    'code' => 'VERIFICATION_ERROR',
                    'message' => translate('An error occurred while verifying the code. Please try again.'),
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['data']['is_returning']
                ? translate('Welcome back! Continue your registration.')
                : translate('Phone verified successfully'),
            'data' => $result['data'],
        ]);
    }

    /**
     * Resend OTP
     * POST /api/v2/driver/onboarding/resend-otp
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'onboarding_id' => 'required|string|min:19|max:24',
            'device_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $result = $this->onboardingService->resendOtp(
            $request->onboarding_id,
            $request->ip(),
            $request->device_id
        );

        if (!$result['success']) {
            $statusCode = $this->getErrorStatusCode($result['error']['code'] ?? 'UNKNOWN');
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => translate('New verification code sent'),
            'data' => $result['data'],
        ]);
    }

    /**
     * Get current onboarding status (requires token)
     * GET /api/v2/driver/onboarding/status
     */
    public function status(Request $request): JsonResponse
    {
        $driver = $request->user();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => translate('Unauthorized'),
                'error' => ['code' => 'UNAUTHORIZED'],
            ], 401);
        }

        $status = $this->onboardingService->getStatus($driver);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    /**
     * Set password
     * POST /api/v2/driver/onboarding/password
     */
    public function setPassword(Request $request): JsonResponse
    {
        $passwordConfig = config('driver_onboarding.password', []);

        $rules = [
            'phone' => 'required|string|min:10|max:20',
            'password' => [
                'required',
                'string',
                'min:8',
                'max:128',
                'regex:/^[a-zA-Z0-9]+$/', // Only letters and digits, no special characters
            ],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'phone.required' => translate('Phone number is required.'),
            'password.regex' => translate('Password can only contain letters and numbers. No special characters allowed.'),
            'password.min' => translate('Password must be at least 8 characters long.'),
            'password.required' => translate('Password is required.'),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        // Find driver by phone
        $phone = $this->normalizePhone($request->phone);
        $driver = \Modules\UserManagement\Entities\User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => translate('Driver not found'),
                'error' => [
                    'code' => 'DRIVER_NOT_FOUND',
                    'message' => translate('Driver not found with this phone number'),
                ],
            ], 404);
        }

        $result = $this->onboardingService->setPassword($driver, $request->password);

        if (!$result['success']) {
            $statusCode = $result['error']['code'] === 'INVALID_PASSWORD' ? 401 : 409;
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], $statusCode);
        }

        // Check if this was a returning driver login or new password setup
        $isReturning = $result['data']['is_returning'] ?? false;
        $message = $isReturning
            ? translate('Login successful. Welcome back!')
            : translate('Password set successfully');

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $result['data'],
        ]);
    }

    /**
     * Submit profile information
     * POST /api/v2/driver/onboarding/profile
     */
    public function submitProfile(Request $request): JsonResponse
    {
        $profileConfig = config('driver_onboarding.profile', []);
        $minAge = $profileConfig['min_age'] ?? 21;
        $maxAge = $profileConfig['max_age'] ?? 65;

        $validator = Validator::make($request->all(), [
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s]+$/u', // Only letters (including Arabic) and spaces, no numbers or emojis
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s]+$/u', // Only letters (including Arabic) and spaces, no numbers or emojis
            ],
            'national_id' => 'required|string|min:10|max:20',
            'email' => 'nullable|email|max:100',
            'date_of_birth' => "nullable|date|before:-{$minAge} years|after:-{$maxAge} years",
            'gender' => 'nullable|in:male,female',
            'first_name_ar' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s]+$/u', // Only letters (including Arabic) and spaces
            ],
            'last_name_ar' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\p{L}\s]+$/u', // Only letters (including Arabic) and spaces
            ],
            // Address fields
            'address' => 'nullable|string|max:500',
            'street' => 'nullable|string|max:191',
            'house' => 'nullable|string|max:191',
            'city' => 'nullable|string|max:191',
            'zip_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'zone_id' => 'nullable|string|exists:zones,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address_label' => 'nullable|string|max:50',
            'government' => 'nullable|string|max:100',
        ], [
            'date_of_birth.before' => translate('You must be at least :min years old', ['min' => $minAge]),
            'date_of_birth.after' => translate('You must be under :max years old', ['max' => $maxAge]),
            'first_name.regex' => translate('First name must contain only letters and spaces. No numbers or special characters allowed.'),
            'first_name.max' => translate('First name must not exceed 50 characters.'),
            'last_name.regex' => translate('Last name must contain only letters and spaces. No numbers or special characters allowed.'),
            'last_name.max' => translate('Last name must not exceed 50 characters.'),
            'first_name_ar.regex' => translate('Arabic first name must contain only letters and spaces.'),
            'last_name_ar.regex' => translate('Arabic last name must contain only letters and spaces.'),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $driver = $request->user();
        $result = $this->onboardingService->submitProfile($driver, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Profile saved successfully'),
            'data' => $result['data'],
        ]);
    }

    /**
     * Select vehicle type
     * POST /api/v2/driver/onboarding/vehicle
     */
    public function selectVehicle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_category_id' => 'required|string|exists:vehicle_categories,id',
            // Brand, model and other details are collected in documents phase
            'brand_id' => 'nullable|string|exists:vehicle_brands,id',
            'model_id' => 'nullable|string|exists:vehicle_models,id',
            'year_id' => 'nullable|string|exists:vehicle_years,id',
            'licence_plate' => 'nullable|string|max:20',
            'request_travel' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $driver = $request->user();
        $result = $this->onboardingService->selectVehicle($driver, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Vehicle information saved'),
            'data' => $result['data'],
        ]);
    }

    /**
     * Upload document
     * POST /api/v2/driver/onboarding/documents/{type}
     */
    public function uploadDocument(Request $request, string $type): JsonResponse
    {
        $docConfig = config("driver_onboarding.documents.required.{$type}");

        if (!$docConfig) {
            return response()->json([
                'success' => false,
                'message' => translate('Invalid document type'),
                'error' => [
                    'code' => 'INVALID_DOCUMENT_TYPE',
                    'provided' => $type,
                    'allowed' => array_keys(config('driver_onboarding.documents.required', [])),
                ],
            ], 400);
        }

        $maxSizeKb = ($docConfig['max_size_mb'] ?? 5) * 1024;
        $allowedMimes = implode(',', $docConfig['allowed_mimes'] ?? ['image/jpeg', 'image/png', 'application/pdf']);

        $validator = Validator::make($request->all(), [
            'file' => "required|file|max:{$maxSizeKb}|mimetypes:{$allowedMimes}",
        ], [
            'file.max' => translate('File size must not exceed :max MB', ['max' => $docConfig['max_size_mb'] ?? 5]),
            'file.mimetypes' => translate('Invalid file type. Allowed: :types', ['types' => $allowedMimes]),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $driver = $request->user();
        $result = $this->onboardingService->uploadDocument($driver, $type, $request->file('file'));

        if (!$result['success']) {
            $statusCode = $this->getErrorStatusCode($result['error']['code'] ?? 'UNKNOWN');
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], $statusCode);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Document uploaded successfully'),
            'data' => $result['data'],
        ]);
    }

    /**
     * Get available cities (zones) for profile selection
     * GET /api/v2/driver/onboarding/cities
     */
    public function getCities(): JsonResponse
    {
        $cities = Zone::where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($zone) {
                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => translate('Cities retrieved successfully'),
            'data' => [
                'cities' => $cities,
                'total' => $cities->count(),
            ],
        ]);
    }

    /**
     * Submit application for review
     * POST /api/v2/driver/onboarding/submit
     */
    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'terms_accepted' => 'required|accepted',
            'privacy_accepted' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $driver = $request->user();
        $result = $this->onboardingService->submitForReview(
            $driver,
            $request->boolean('terms_accepted'),
            $request->boolean('privacy_accepted')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Application submitted for review'),
            'data' => $result['data'],
        ]);
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => translate('Validation failed'),
            'errors' => $validator->errors()->toArray(),
        ], 422);
    }

    private function getErrorStatusCode(string $code): int
    {
        return match ($code) {
            'RATE_LIMITED', 'RESEND_COOLDOWN', 'VERIFY_LOCKED' => 429,
            'SESSION_NOT_FOUND', 'SESSION_EXPIRED', 'SESSION_INVALID', 'UNAUTHORIZED' => 401,
            'INVALID_STATE_TRANSITION', 'INVALID_STATE' => 409,
            'INVALID_OTP', 'OTP_EXPIRED', 'INVALID_DOCUMENT_TYPE', 'FILE_TOO_LARGE', 'INVALID_FILE_TYPE' => 400,
            'MAX_UPLOADS_REACHED', 'MAX_RESENDS', 'DOCUMENTS_INCOMPLETE' => 400,
            'DUPLICATE_ENTRY' => 409,
            'DATABASE_ERROR', 'UNEXPECTED_ERROR', 'VERIFICATION_ERROR' => 500,
            default => 400,
        };
    }

    /**
     * Normalize phone number to +20 format
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
}
