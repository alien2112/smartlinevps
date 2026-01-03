<?php

namespace App\Http\Controllers\Api\V2\Driver;

use App\Http\Controllers\Controller;
use App\Services\Driver\DriverOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'otp' => 'required|string|size:' . config('driver_onboarding.otp.length', 6),
            'device_id' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

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
            'password' => [
                'required',
                'string',
                'min:' . ($passwordConfig['min_length'] ?? 8),
                'confirmed',
            ],
        ];

        // Add password complexity rules
        if ($passwordConfig['require_uppercase'] ?? true) {
            $rules['password'][] = 'regex:/[A-Z]/';
        }
        if ($passwordConfig['require_lowercase'] ?? true) {
            $rules['password'][] = 'regex:/[a-z]/';
        }
        if ($passwordConfig['require_number'] ?? true) {
            $rules['password'][] = 'regex:/[0-9]/';
        }
        if ($passwordConfig['require_special'] ?? false) {
            $rules['password'][] = 'regex:/[@$!%*#?&]/';
        }

        $validator = Validator::make($request->all(), $rules, [
            'password.regex' => translate('Password does not meet complexity requirements'),
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $driver = $request->user();
        $result = $this->onboardingService->setPassword($driver, $request->password);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']['message'],
                'error' => $result['error'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => translate('Password set successfully'),
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
            'first_name' => 'required|string|min:2|max:50',
            'last_name' => 'required|string|min:2|max:50',
            'national_id' => 'required|string|min:10|max:20',
            'city_id' => 'required|string|exists:cities,id',
            'email' => 'nullable|email|max:100',
            'date_of_birth' => "nullable|date|before:-{$minAge} years|after:-{$maxAge} years",
            'gender' => 'nullable|in:male,female',
            'first_name_ar' => 'nullable|string|min:2|max:50',
            'last_name_ar' => 'nullable|string|min:2|max:50',
        ], [
            'date_of_birth.before' => translate('You must be at least :min years old', ['min' => $minAge]),
            'date_of_birth.after' => translate('You must be under :max years old', ['max' => $maxAge]),
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
            'brand_id' => 'required|string|exists:vehicle_brands,id',
            'model_id' => 'required|string|exists:vehicle_models,id',
            'year' => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:30',
            'licence_plate' => 'nullable|string|max:20',
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
            'SESSION_NOT_FOUND', 'SESSION_EXPIRED', 'UNAUTHORIZED' => 401,
            'INVALID_STATE_TRANSITION', 'INVALID_STATE' => 409,
            'INVALID_OTP', 'OTP_EXPIRED', 'INVALID_DOCUMENT_TYPE', 'FILE_TOO_LARGE', 'INVALID_FILE_TYPE' => 400,
            'MAX_UPLOADS_REACHED', 'MAX_RESENDS', 'DOCUMENTS_INCOMPLETE' => 400,
            default => 400,
        };
    }
}
