<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use App\Services\BeOnOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\DriverDocument;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\Interface\DriverServiceInterface;
use Modules\UserManagement\Service\Interface\OtpVerificationServiceInterface;

/**
 * DriverOnboardingController
 * 
 * Handles the unified driver authentication and onboarding flow.
 * This is Uber-style: same flow for both login and registration,
 * with resume-from-where-you-left functionality.
 * 
 * Flow Steps:
 * 1. Phone Number Entry → 2. OTP Verification → 3. Password Creation →
 * 4. Registration Info → 5. Vehicle Type → 6. Documents → 7. Pending Approval → 8. Approved
 */
class DriverOnboardingController extends Controller
{
    // Onboarding step constants
    public const STEP_PHONE = 'phone';
    public const STEP_OTP = 'otp';
    public const STEP_PASSWORD = 'password';
    public const STEP_REGISTER_INFO = 'register_info';
    public const STEP_VEHICLE_TYPE = 'vehicle_type';
    public const STEP_DOCUMENTS = 'documents';
    public const STEP_PENDING_APPROVAL = 'pending_approval';
    public const STEP_APPROVED = 'approved';

    // OTP Cache prefix and expiry
    protected const OTP_CACHE_PREFIX = 'driver_onboarding_otp:';
    protected const OTP_EXPIRY_MINUTES = 5;

    protected DriverServiceInterface $driverService;
    protected BeOnOtpService $beonOtpService;

    public function __construct(
        DriverServiceInterface $driverService,
        BeOnOtpService $beonOtpService
    ) {
        $this->driverService = $driverService;
        $this->beonOtpService = $beonOtpService;
    }

    /**
     * Step 1: Start - Phone Number Entry
     * POST /api/driver/auth/start
     * 
     * Finds or creates driver, sends OTP, sets step to 'otp'
     */
    public function start(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        try {
            DB::beginTransaction();

            // Find or create driver
            $driver = User::where('phone', $phone)
                ->where('user_type', 'driver')
                ->first();

            if (!$driver) {
                // Create new driver with phone only
                $driver = User::create([
                    'phone' => $phone,
                    'user_type' => 'driver',
                    'onboarding_step' => self::STEP_OTP,
                    'is_active' => false,
                    'ref_code' => $this->generateRefCode(),
                ]);

                Log::info('New driver created for onboarding', ['driver_id' => $driver->id, 'phone' => $phone]);
            } else {
                // Existing driver - set step to OTP for re-verification
                $driver->onboarding_step = self::STEP_OTP;
                $driver->save();
            }

            // Send OTP via Beon OTP Service
            $otpResult = $this->sendOtpViaBeon($phone);

            if (!$otpResult['success']) {
                throw new \Exception($otpResult['message'] ?? 'Failed to send OTP');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'OTP sent successfully',
                'data' => [
                    'next_step' => self::STEP_OTP,
                    'phone' => $phone,
                    'is_new_driver' => $driver->wasRecentlyCreated,
                    'otp_expiry_seconds' => self::OTP_EXPIRY_MINUTES * 60,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Driver onboarding start failed', ['error' => $e->getMessage(), 'phone' => $phone]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to start onboarding: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Verify OTP
     * POST /api/driver/auth/verify-otp
     *
     * Verifies OTP using Beon OTP service and determines next step based on driver's state
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'otp' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found. Please start over.',
            ], 404);
        }

        // Verify OTP from cache (Beon sends OTP, we store and verify locally)
        $otpValid = $this->verifyOtpFromCache($phone, $request->otp);

        if (!$otpValid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired OTP',
            ], 400);
        }

        // Clear the OTP from cache after successful verification
        Cache::forget(self::OTP_CACHE_PREFIX . $phone);

        // Mark OTP as verified
        $driver->otp_verified_at = now();
        
        // Determine next step based on current state
        $nextStep = $this->determineNextStep($driver);
        $driver->onboarding_step = $nextStep;
        $driver->save();

        $response = [
            'status' => 'success',
            'message' => 'OTP verified successfully',
            'data' => [
                'next_step' => $nextStep,
            ],
        ];

        // If fully approved, issue token
        if ($nextStep === self::STEP_APPROVED) {
            $token = $driver->createToken('driver-token')->accessToken;
            $response['data']['token'] = $token;
            $response['data']['driver'] = $this->getDriverProfile($driver);
        }

        return response()->json($response);
    }

    /**
     * Step 3: Set Password
     * POST /api/driver/auth/set-password
     */
    public function setPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Validate flow - OTP must be verified first
        if (!$driver->otp_verified_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please verify OTP first',
                'data' => ['next_step' => self::STEP_OTP],
            ], 400);
        }

        $driver->password = Hash::make($request->password);
        $driver->password_set_at = now();
        $driver->onboarding_step = self::STEP_REGISTER_INFO;
        $driver->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password set successfully',
            'data' => [
                'next_step' => self::STEP_REGISTER_INFO,
            ],
        ]);
    }

    /**
     * Step 4: Registration Info
     * POST /api/driver/auth/register-info
     */
    public function registerInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'first_name_ar' => 'required|string|max:100',
            'last_name_ar' => 'required|string|max:100',
            'national_id' => 'required|string|min:10|max:20',
            'city_id' => 'required|integer|exists:zones,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Validate flow - password must be set first
        if (!$driver->password_set_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please set password first',
                'data' => ['next_step' => self::STEP_PASSWORD],
            ], 400);
        }

        $driver->first_name = $request->first_name_ar;
        $driver->last_name = $request->last_name_ar;
        $driver->first_name_ar = $request->first_name_ar;
        $driver->last_name_ar = $request->last_name_ar;
        $driver->identification_number = $request->national_id;
        $driver->city_id = $request->city_id;
        $driver->register_completed_at = now();
        $driver->onboarding_step = self::STEP_VEHICLE_TYPE;
        $driver->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Registration info saved successfully',
            'data' => [
                'next_step' => self::STEP_VEHICLE_TYPE,
            ],
        ]);
    }

    /**
     * Step 5: Vehicle Type Selection
     * POST /api/driver/auth/vehicle-type
     */
    public function vehicleType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'vehicle_type' => 'required|in:car,taxi,scooter',
            'travel_enabled' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Validate flow - registration must be completed first
        if (!$driver->register_completed_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please complete registration first',
                'data' => ['next_step' => self::STEP_REGISTER_INFO],
            ], 400);
        }

        $driver->selected_vehicle_type = $request->vehicle_type;
        $driver->travel_enabled = $request->boolean('travel_enabled', false);
        $driver->vehicle_selected_at = now();
        $driver->onboarding_step = self::STEP_DOCUMENTS;
        $driver->save();

        // Get required documents for this vehicle type
        $requiredDocs = DriverDocument::getRequiredDocuments($request->vehicle_type);

        return response()->json([
            'status' => 'success',
            'message' => 'Vehicle type selected successfully',
            'data' => [
                'next_step' => self::STEP_DOCUMENTS,
                'required_documents' => $requiredDocs,
                'vehicle_type' => $request->vehicle_type,
            ],
        ]);
    }

    /**
     * Step 6: Document Upload
     * POST /api/driver/auth/upload/{type}
     */
    public function uploadDocument(Request $request, string $type): JsonResponse
    {
        // Validate document type
        if (!DriverDocument::isValidType($type)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid document type',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Validate flow - vehicle type must be selected first
        if (!$driver->vehicle_selected_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select vehicle type first',
                'data' => ['next_step' => self::STEP_VEHICLE_TYPE],
            ], 400);
        }

        try {
            $file = $request->file('document');
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('driver-documents/' . $driver->id, $fileName, 'public');

            // Create or update document record
            $document = DriverDocument::updateOrCreate(
                [
                    'driver_id' => $driver->id,
                    'type' => $type,
                ],
                [
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'verified' => false,
                    'verified_at' => null,
                    'rejection_reason' => null,
                ]
            );

            // Check if all required documents are uploaded
            $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
            $uploadedDocs = DriverDocument::where('driver_id', $driver->id)
                ->pluck('type')
                ->toArray();
            
            $missingDocs = array_diff($requiredDocs, $uploadedDocs);

            if (empty($missingDocs)) {
                // All documents uploaded - move to pending approval
                $driver->documents_completed_at = now();
                $driver->onboarding_step = self::STEP_PENDING_APPROVAL;
                $driver->is_active = false; // Ensure driver is not active until approved
                $driver->save();

                return response()->json([
                    'status' => 'success',
                    'message' => 'All documents uploaded. Your application is pending approval.',
                    'data' => [
                        'next_step' => self::STEP_PENDING_APPROVAL,
                        'document_type' => $type,
                        'document_id' => $document->uuid,
                    ],
                ]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Document uploaded successfully',
                'data' => [
                    'next_step' => self::STEP_DOCUMENTS,
                    'document_type' => $type,
                    'document_id' => $document->uuid,
                    'uploaded_documents' => $uploadedDocs,
                    'missing_documents' => array_values($missingDocs),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'error' => $e->getMessage(),
                'driver_id' => $driver->id,
                'type' => $type,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload document',
            ], 500);
        }
    }

    /**
     * Get current status / Resume endpoint
     * GET /api/driver/auth/status
     * 
     * This is the MOST IMPORTANT endpoint - Flutter calls this on app open
     * to determine where to resume the onboarding flow.
     */
    public function status(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'success',
                'message' => 'Phone number not registered',
                'data' => [
                    'next_step' => self::STEP_PHONE,
                    'is_registered' => false,
                ],
            ]);
        }

        $response = [
            'status' => 'success',
            'message' => 'Driver status retrieved',
            'data' => [
                'next_step' => $driver->onboarding_step,
                'is_registered' => true,
                'is_approved' => $driver->onboarding_step === self::STEP_APPROVED && $driver->is_active,
            ],
        ];

        // Add additional data based on step
        switch ($driver->onboarding_step) {
            case self::STEP_DOCUMENTS:
            case self::STEP_PENDING_APPROVAL:
                $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
                $uploadedDocs = DriverDocument::where('driver_id', $driver->id)
                    ->pluck('type')
                    ->toArray();
                $missingDocs = array_diff($requiredDocs, $uploadedDocs);
                
                $response['data']['uploaded_documents'] = $uploadedDocs;
                $response['data']['missing_documents'] = array_values($missingDocs);
                $response['data']['vehicle_type'] = $driver->selected_vehicle_type;
                break;

            case self::STEP_APPROVED:
                // Driver is approved - include token if this is being used for auto-login
                // Note: Only include token if verified session (future enhancement)
                $response['data']['driver'] = $this->getDriverProfile($driver);
                break;
        }

        return response()->json($response);
    }

    /**
     * Login (Only for approved drivers)
     * POST /api/driver/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Check password
        if (!Hash::check($request->password, $driver->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check if driver is approved
        if ($driver->onboarding_step !== self::STEP_APPROVED) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account not approved yet',
                'data' => [
                    'next_step' => $driver->onboarding_step,
                    'is_approved' => false,
                ],
            ], 403);
        }

        // Check if driver is active
        if (!$driver->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is deactivated. Please contact support.',
            ], 403);
        }

        // Issue token
        $token = $driver->createToken('driver-token')->accessToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'driver' => $this->getDriverProfile($driver),
            ],
        ]);
    }

    /**
     * Resend OTP
     * POST /api/driver/auth/resend-otp
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found. Please start over.',
            ], 404);
        }

        // Send OTP via Beon
        $otpResult = $this->sendOtpViaBeon($phone);

        if (!$otpResult['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $otpResult['message'] ?? 'Failed to send OTP. Please try again.',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent successfully',
            'data' => [
                'otp_expiry_seconds' => self::OTP_EXPIRY_MINUTES * 60,
            ],
        ]);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Determine the next step based on driver's current state
     */
    protected function determineNextStep(User $driver): string
    {
        // If password not set → password step
        if (!$driver->password) {
            return self::STEP_PASSWORD;
        }

        // If registration not complete → register_info step
        if (!$driver->register_completed_at) {
            return self::STEP_REGISTER_INFO;
        }

        // If vehicle not selected → vehicle_type step
        if (!$driver->vehicle_selected_at) {
            return self::STEP_VEHICLE_TYPE;
        }

        // If documents not complete → documents step
        if (!$driver->documents_completed_at) {
            return self::STEP_DOCUMENTS;
        }

        // If not approved → pending_approval step
        if ($driver->onboarding_step !== self::STEP_APPROVED || !$driver->is_active) {
            return self::STEP_PENDING_APPROVAL;
        }

        // Fully approved
        return self::STEP_APPROVED;
    }

    /**
     * Normalize phone number format
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ensure it starts with +
        if (!str_starts_with($phone, '+')) {
            // Assume Egypt if no country code
            if (str_starts_with($phone, '0')) {
                $phone = '+20' . substr($phone, 1);
            } else {
                $phone = '+' . $phone;
            }
        }
        
        return $phone;
    }

    /**
     * Generate unique referral code
     */
    protected function generateRefCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('ref_code', $code)->exists());

        return $code;
    }

    /**
     * Send OTP via Beon OTP Service
     *
     * @param string $phone Phone number
     * @return array ['success' => bool, 'message' => string]
     */
    protected function sendOtpViaBeon(string $phone): array
    {
        try {
            // Generate 4-digit OTP
            $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            // Store OTP in cache for verification
            Cache::put(
                self::OTP_CACHE_PREFIX . $phone,
                $otp,
                now()->addMinutes(self::OTP_EXPIRY_MINUTES)
            );

            // In development, log the OTP for testing
            if (app()->environment('local', 'development', 'staging')) {
                Log::info('Driver Onboarding OTP', [
                    'phone' => substr($phone, 0, 5) . '****' . substr($phone, -2),
                    'otp' => $otp,
                ]);
            }

            // Send OTP via Beon service
            $result = $this->beonOtpService->sendOtp($phone, $otp, 'Rateel');

            if (!$result['success']) {
                Log::warning('Beon OTP send failed', [
                    'phone' => substr($phone, 0, 5) . '****',
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'Failed to send OTP',
                ];
            }

            return [
                'success' => true,
                'message' => 'OTP sent successfully',
            ];

        } catch (\Exception $e) {
            Log::error('Beon OTP exception', [
                'phone' => substr($phone, 0, 5) . '****',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ];
        }
    }

    /**
     * Verify OTP from cache
     *
     * @param string $phone Phone number
     * @param string $otp OTP code to verify
     * @return bool
     */
    protected function verifyOtpFromCache(string $phone, string $otp): bool
    {
        $cachedOtp = Cache::get(self::OTP_CACHE_PREFIX . $phone);

        if (!$cachedOtp) {
            Log::warning('OTP not found or expired', [
                'phone' => substr($phone, 0, 5) . '****',
            ]);
            return false;
        }

        if ($cachedOtp !== $otp) {
            Log::warning('OTP mismatch', [
                'phone' => substr($phone, 0, 5) . '****',
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get basic driver profile
     */
    protected function getDriverProfile(User $driver): array
    {
        return [
            'id' => $driver->uuid ?? $driver->id,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'phone' => $driver->phone,
            'email' => $driver->email,
            'profile_image' => $driver->profile_image ? asset('storage/' . $driver->profile_image) : null,
            'has_vehicle' => $driver->website_selected_at ? true : false,
            'is_approved' => $driver->is_active,
            'ref_code' => $driver->ref_code,
        ];
    }
}
