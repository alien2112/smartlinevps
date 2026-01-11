<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver\V2;

use App\Jobs\CreateDriverVehicleJob;
use App\Services\BeOnOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Modules\UserManagement\Entities\DriverDocument;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Http\Controllers\Api\New\Driver\DriverOnboardingController as BaseController;

class DriverOnboardingV2Controller extends Controller
{
    // Onboarding step constants
    public const STEP_PHONE = 'phone';
    public const STEP_OTP = 'otp';
    public const STEP_PASSWORD = 'password';
    public const STEP_REGISTER_INFO = 'register_info';
    public const STEP_VEHICLE_TYPE = 'vehicle_type';
    public const STEP_DOCUMENTS = 'documents';
    public const STEP_KYC_VERIFICATION = 'kyc_verification';
    public const STEP_PENDING_APPROVAL = 'pending_approval';
    public const STEP_APPROVED = 'approved';

    // OTP Cache prefix and expiry
    protected const OTP_CACHE_PREFIX = 'driver_onboarding_otp:';
    protected const OTP_EXPIRY_MINUTES = 5;

    protected BeOnOtpService $beonOtpService;

    public function __construct(BeOnOtpService $beonOtpService)
    {
        $this->beonOtpService = $beonOtpService;
    }

    /**
     * Step 1: Start - Phone Number Entry
     * POST /api/v2/driver/onboarding/start
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

            // First check if phone exists for any user type
            $existingUser = User::where('phone', $phone)->first();

            if ($existingUser && $existingUser->user_type !== 'driver') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'This phone number is already registered as a ' . $existingUser->user_type . '. Please use a different phone number or contact support.',
                ], 400);
            }

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

                Log::info('New driver created for onboarding V2', ['driver_id' => $driver->id, 'phone' => $phone]);
            }

            // Check if phone number is already verified
            if ($driver->otp_verified_at) {
                // Phone already verified
                // If driver has password, require password login - don't auto-authenticate
                if ($driver->password) {
                    $driver->onboarding_step = self::STEP_PASSWORD;
                    $driver->save();

                    DB::commit();

                    return response()->json([
                        'status' => 'success',
                        'message' => 'Phone number verified. Please enter your password to continue.',
                        'data' => [
                            'next_step' => self::STEP_PASSWORD,
                            'phone' => $phone,
                            'is_new_driver' => false,
                            'phone_verified' => true,
                            'requires_password' => true,
                        ],
                    ]);
                }

                // Phone verified but no password set yet - continue onboarding
                $nextStep = $this->determineNextStep($driver);
                $driver->onboarding_step = $nextStep;
                $driver->save();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Phone number already verified. Continue onboarding.',
                    'data' => [
                        'next_step' => $nextStep,
                        'phone' => $phone,
                        'is_new_driver' => false,
                        'phone_verified' => true,
                    ],
                ]);
            }

            // New driver or unverified phone - send OTP
            $driver->onboarding_step = self::STEP_OTP;
            $driver->save();

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
            Log::error('Driver onboarding start failed V2', ['error' => $e->getMessage(), 'phone' => $phone]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to start onboarding: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 2: Verify OTP
     * POST /api/v2/driver/onboarding/verify-otp
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

        // Verify OTP from cache
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
                'phone_verified' => true,
            ],
        ];

        // Issue token for any driver who has verified OTP (regardless of approval status)
        if ($driver->password) {
            $token = $driver->createToken('driver-token')->accessToken;
            $response['data']['token'] = $token;
            $response['data']['driver'] = $this->getDriverProfile($driver);

            if ($nextStep === self::STEP_APPROVED && $driver->is_active) {
                $response['message'] = 'OTP verified. Driver approved and authenticated successfully.';
            } else {
                $response['message'] = 'OTP verified. Continue onboarding.';
            }
        }

        return response()->json($response);
    }

    /**
     * Resend OTP - Only requires phone
     * POST /api/v2/driver/onboarding/resend-otp
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

        // Send OTP via BeOn
        $otpResult = $this->beonOtpService->sendOtp($phone);

        if (!$otpResult['success']) {
            return response()->json([
                'status' => 'error',
                'message' => $otpResult['message'] ?? 'Failed to send OTP. Please try again.',
            ], 500);
        }

        // Cache the OTP
        $otp = $otpResult['otp'] ?? null;
        if ($otp) {
            Cache::put(self::OTP_CACHE_PREFIX . $phone, $otp, now()->addMinutes(self::OTP_EXPIRY_MINUTES));
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP sent successfully',
            'data' => [
                'phone' => $phone,
                'message' => 'Please enter the OTP sent to your phone',
            ],
        ]);
    }

    /**
     * Step 3: Set Password
     * POST /api/v2/driver/onboarding/set-password
     */
    public function setPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'password' => 'required|string|min:8',
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
     * Verify Password - For returning drivers (login)
     * POST /api/v2/driver/onboarding/verify-password
     */
    public function verifyPassword(Request $request): JsonResponse
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

        // Check if driver has a password set
        if (!$driver->password) {
            return response()->json([
                'status' => 'error',
                'message' => 'No password set for this account',
            ], 400);
        }

        // Verify password
        if (!Hash::check($request->password, $driver->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid password',
            ], 401);
        }

        // Password correct - issue token and return profile
        $token = $driver->createToken('driver-token')->accessToken;
        $nextStep = $this->determineNextStep($driver);

        // Update onboarding step
        $driver->onboarding_step = $nextStep;
        $driver->save();

        $response = [
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'driver' => $this->getDriverProfile($driver),
                'next_step' => $nextStep,
                'phone_verified' => true,
            ],
        ];

        if ($nextStep === self::STEP_APPROVED && $driver->is_active) {
            $response['message'] = 'Driver approved and authenticated successfully';
        }

        return response()->json($response);
    }

    /**
     * Step 4: Registration Info
     * POST /api/v2/driver/onboarding/register-info
     */
    public function registerInfo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'first_name_ar' => 'required|string|max:100',
            'last_name_ar' => 'required|string|max:100',
            'national_id' => 'required|string|min:10|max:20',
            'city_id' => 'required|string',
            'city_name' => 'nullable|string|max:100',
            'referral_code' => 'nullable|string|size:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->phone);
        $referralCode = $request->referral_code ? strtoupper(trim($request->referral_code)) : null;

        // Resolve zone: accept both zone ID (UUID) and zone name
        $zoneInput = $request->city_id;
        $zone = DB::table('zones')->where('id', $zoneInput)->first();

        if (!$zone) {
            // Try to find by name if not found by ID
            $zone = DB::table('zones')->where('name', $zoneInput)->first();
        }

        if (!$zone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid city/zone. Please select a valid zone.',
                'errors' => ['city_id' => ['The selected city is invalid.']],
            ], 422);
        }

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

        // Validate referral code if provided
        $referrer = null;
        if ($referralCode) {
            $referrer = User::where('ref_code', $referralCode)
                ->where('is_active', true)
                ->first();

            if (!$referrer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid referral code. Please check and try again.',
                ], 400);
            }

            if ($referrer->id === $driver->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot refer yourself.',
                ], 400);
            }

            if ($driver->referred_by) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have already been referred by another user.',
                ], 400);
            }
        }

        $driver->first_name = $request->first_name_ar;
        $driver->last_name = $request->last_name_ar;
        $driver->first_name_ar = $request->first_name_ar;
        $driver->last_name_ar = $request->last_name_ar;
        $driver->identification_number = $request->national_id;
        $driver->city_id = $zone->id;
        $driver->city_name = $request->city_name ?? $zone->name;
        $driver->register_completed_at = now();
        $driver->onboarding_step = self::STEP_VEHICLE_TYPE;

        // Set referrer if provided
        if ($referrer) {
            $driver->referred_by = $referrer->id;
            $referrer->increment('referral_count');

            // Create referral invite record
            \Modules\UserManagement\Entities\ReferralInvite::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $driver->id,
                'invite_code' => $referralCode,
                'invite_channel' => 'code',
                'status' => 'signed_up',
                'signup_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('Driver referred by another user', [
                'driver_id' => $driver->id,
                'referrer_id' => $referrer->id,
                'referral_code' => $referralCode
            ]);
        }

        $driver->save();

        $response = [
            'status' => 'success',
            'message' => 'Registration info saved successfully',
            'data' => [
                'next_step' => self::STEP_VEHICLE_TYPE,
            ],
        ];

        // Add referral confirmation if code was used
        if ($referrer) {
            $response['data']['referral_applied'] = true;
            $response['data']['referred_by'] = [
                'id' => $referrer->id,
                'name' => trim($referrer->first_name . ' ' . $referrer->last_name),
                'ref_code' => $referrer->ref_code,
            ];
        }

        return response()->json($response);
    }

    /**
     * Step 5: Vehicle Type Selection
     * POST /api/v2/driver/onboarding/vehicle-type
     */
    public function vehicleType(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'vehicle_category_id' => 'required|exists:vehicle_categories,id',
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

        // Get the vehicle category
        $vehicleCategory = DB::table('vehicle_categories')
            ->where('id', $request->vehicle_category_id)
            ->first();

        // Define auto-approved categories
        $autoApprovedCategories = [
            'd4d1e8f1-c716-4cff-96e1-c0b312a1a58b', // Taxi
            '89060926-153c-4c43-a881-c2ea0eb47402', // Scooter
            'd8e5a6e1-bf60-46a8-959a-22a18bdcd764', // Uncategorized
        ];

        $requiresAdminApproval = !in_array($request->vehicle_category_id, $autoApprovedCategories);

        $driver->vehicle_category_id = $request->vehicle_category_id;
        $driver->selected_vehicle_type = $vehicleCategory->type;
        $driver->travel_enabled = $request->boolean('travel_enabled', false);
        $driver->vehicle_selected_at = now();
        $driver->onboarding_step = self::STEP_DOCUMENTS;
        $driver->vehicle_category_requires_approval = $requiresAdminApproval;
        $driver->save();

        // Get required documents
        $requiredDocs = DriverDocument::getRequiredDocuments($vehicleCategory->type);

        $message = 'Vehicle category selected successfully';
        if ($requiresAdminApproval) {
            $message .= '. Your account will require admin approval after document submission.';
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => [
                'next_step' => self::STEP_DOCUMENTS,
                'required_documents' => $requiredDocs,
                'vehicle_category_id' => $request->vehicle_category_id,
                'vehicle_category_name' => $vehicleCategory->name,
                'vehicle_type' => $vehicleCategory->type,
                'requires_admin_approval' => $requiresAdminApproval,
            ],
        ]);
    }

    /**
     * Upload ID Documents
     * POST /api/v2/driver/onboarding/upload/id
     */
    public function uploadId(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'id_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'id_back' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->handleMultiDocumentUpload($request, [
            'id_front' => DriverDocument::TYPE_ID_FRONT,
            'id_back' => DriverDocument::TYPE_ID_BACK,
        ]);
    }

    /**
     * Upload License Documents
     * POST /api/v2/driver/onboarding/upload/license
     */
    public function uploadLicense(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'license_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'license_back' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',

            // Optional vehicle information
            'brand_id' => 'sometimes|required|exists:vehicle_brands,id',
            'model_id' => 'sometimes|required|exists:vehicle_models,id',
            'category_id' => 'sometimes|exists:vehicle_categories,id',
            'licence_plate_number' => 'sometimes|required|string|max:255',
            'licence_expire_date' => 'sometimes|date',
            'ownership' => 'sometimes|nullable|string|in:owned,rented,leased',
            'fuel_type' => 'sometimes|nullable|string|in:petrol,diesel,electric,hybrid',
            'vin_number' => 'sometimes|nullable|string|max:255',
            'transmission' => 'sometimes|nullable|string|in:manual,automatic',
            'parcel_weight_capacity' => 'sometimes|nullable|numeric',
            'year_id' => 'sometimes|nullable|exists:vehicle_years,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->handleMultiDocumentUploadWithVehicle($request, [
            'license_front' => DriverDocument::TYPE_LICENSE_FRONT,
            'license_back' => DriverDocument::TYPE_LICENSE_BACK,
        ]);
    }

    /**
     * Upload Car Photo Documents
     * POST /api/v2/driver/onboarding/upload/car_photo
     */
    public function uploadCarPhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'car_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'car_back' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->handleMultiDocumentUpload($request, [
            'car_front' => DriverDocument::TYPE_CAR_FRONT,
            'car_back' => DriverDocument::TYPE_CAR_BACK,
        ]);
    }

    /**
     * Upload Selfie Document
     * POST /api/v2/driver/onboarding/upload/selfie
     */
    public function uploadSelfie(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'selfie' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        return $this->handleMultiDocumentUpload($request, [
            'selfie' => DriverDocument::TYPE_SELFIE,
        ]);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get driver profile information
     */
    protected function getDriverProfile(User $driver): array
    {
        return [
            'id' => $driver->id,
            'phone' => $driver->phone,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'email' => $driver->email,
            'profile_image' => $driver->profile_image,
            'is_active' => $driver->is_active,
            'is_approved' => $driver->is_approved,
            'onboarding_step' => $driver->onboarding_step,
        ];
    }

    /**
     * Determine the next step based on driver's current state
     */
    protected function determineNextStep(User $driver): string
    {
        if (!$driver->password) {
            return self::STEP_PASSWORD;
        }

        if (!$driver->register_completed_at) {
            return self::STEP_REGISTER_INFO;
        }

        if (!$driver->vehicle_selected_at) {
            return self::STEP_VEHICLE_TYPE;
        }

        if (!$driver->documents_completed_at) {
            return self::STEP_DOCUMENTS;
        }

        if (!$driver->kyc_verified_at) {
            return self::STEP_KYC_VERIFICATION;
        }

        if ($driver->onboarding_step !== self::STEP_APPROVED || !$driver->is_active) {
            return self::STEP_PENDING_APPROVAL;
        }

        return self::STEP_APPROVED;
    }

    /**
     * Normalize phone number format
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (!str_starts_with($phone, '+')) {
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
     * Send OTP via Beon service
     */
    protected function sendOtpViaBeon(string $phone): array
    {
        try {
            $otp = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            Cache::put(
                self::OTP_CACHE_PREFIX . $phone,
                $otp,
                now()->addMinutes(self::OTP_EXPIRY_MINUTES)
            );

            if (app()->environment('local', 'development', 'staging')) {
                Log::info('Driver Onboarding OTP V2', [
                    'phone' => substr($phone, 0, 5) . '****' . substr($phone, -2),
                    'otp' => $otp,
                ]);
            }

            $result = $this->beonOtpService->sendOtp($phone, $otp, 'Rateel');

            if (!$result['success']) {
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
            Log::error('Beon OTP exception V2', [
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
     */
    protected function verifyOtpFromCache(string $phone, string $otp): bool
    {
        $cachedOtp = Cache::get(self::OTP_CACHE_PREFIX . $phone);

        if (!$cachedOtp) {
            return false;
        }

        if ($cachedOtp !== $otp) {
            return false;
        }

        return true;
    }

    /**
     * Handle multiple document upload
     */
    protected function handleMultiDocumentUpload(Request $request, array $documentMap): JsonResponse
    {
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

        if (!$driver->vehicle_selected_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select vehicle type first',
                'data' => ['next_step' => self::STEP_VEHICLE_TYPE],
            ], 400);
        }

        try {
            DB::beginTransaction();

            $uploadedDocuments = [];

            foreach ($documentMap as $fieldName => $documentType) {
                if (!$request->hasFile($fieldName)) {
                    continue;
                }

                $file = $request->file($fieldName);
                $extension = $file->getClientOriginalExtension();
                
                $existingDoc = DriverDocument::where('driver_id', $driver->id)
                    ->where('type', $documentType)
                    ->first();

                $oldPath = $existingDoc?->file_path;
                $fileName = fileUploader('driver/document/', $extension, $file, $oldPath);

                $document = DriverDocument::updateOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'type' => $documentType,
                    ],
                    [
                        'file_path' => $fileName,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'verified' => false,
                        'verified_at' => null,
                        'rejection_reason' => null,
                    ]
                );

                $uploadedDocuments[] = [
                    'type' => $documentType,
                    'id' => $document->uuid,
                    'file_url' => $document->file_url,
                    'original_name' => $document->original_name,
                ];
            }

            $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
            $uploadedDocTypes = DriverDocument::where('driver_id', $driver->id)
                ->pluck('type')
                ->toArray();

            $missingDocs = array_diff($requiredDocs, $uploadedDocTypes);

            if (empty($missingDocs)) {
                $driver->documents_completed_at = now();
                $driver->onboarding_step = self::STEP_KYC_VERIFICATION;
                $driver->is_active = false;
                $driver->save();

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'All documents uploaded successfully. Please proceed to KYC verification.',
                    'data' => [
                        'next_step' => self::STEP_KYC_VERIFICATION,
                        'uploaded_documents' => $uploadedDocuments,
                        'requires_admin_approval' => (bool) $driver->vehicle_category_requires_approval,
                    ],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Documents uploaded successfully',
                'data' => [
                    'next_step' => self::STEP_DOCUMENTS,
                    'uploaded_documents' => $uploadedDocuments,
                    'all_uploaded_types' => $uploadedDocTypes,
                    'missing_documents' => array_values($missingDocs),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Multi-document upload failed V2', [
                'error' => $e->getMessage(),
                'driver_id' => $driver->id ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload documents: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle multiple document upload with optional vehicle information
     */
    protected function handleMultiDocumentUploadWithVehicle(Request $request, array $documentMap): JsonResponse
    {
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

        if (!$driver->vehicle_selected_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select vehicle type first',
                'data' => ['next_step' => self::STEP_VEHICLE_TYPE],
            ], 400);
        }

        try {
            DB::beginTransaction();

            $uploadedDocuments = [];
            $vehicleCreated = false;

            $documentTypes = array_values($documentMap);
            $existingDocs = DriverDocument::where('driver_id', $driver->id)
                ->whereIn('type', $documentTypes)
                ->get()
                ->keyBy('type');

            foreach ($documentMap as $fieldName => $documentType) {
                if (!$request->hasFile($fieldName)) {
                    continue;
                }

                $file = $request->file($fieldName);
                $extension = $file->getClientOriginalExtension();
                
                $existingDoc = $existingDocs->get($documentType);
                $oldPath = $existingDoc?->file_path;
                $fileName = fileUploader('driver/document/', $extension, $file, $oldPath);

                $document = DriverDocument::updateOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'type' => $documentType,
                    ],
                    [
                        'file_path' => $fileName,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'verified' => false,
                        'verified_at' => null,
                        'rejection_reason' => null,
                    ]
                );

                $uploadedDocuments[] = [
                    'type' => $documentType,
                    'id' => $document->uuid,
                    'file_url' => $document->file_url,
                    'original_name' => $document->original_name,
                ];
            }

            // Check if vehicle information was provided
            if ($request->has('brand_id') && $request->has('model_id') && $request->has('licence_plate_number')) {
                $vehicleData = [
                    'driver_id' => $driver->id,
                    'brand_id' => $request->brand_id,
                    'model_id' => $request->model_id,
                    'category_id' => $request->category_id ?? $driver->vehicle_category_id,
                    'licence_plate_number' => $request->licence_plate_number,
                    'licence_expire_date' => $request->licence_expire_date,
                    'ownership' => $request->ownership,
                    'fuel_type' => $request->fuel_type,
                    'vin_number' => $request->vin_number,
                    'transmission' => $request->transmission,
                    'parcel_weight_capacity' => $request->parcel_weight_capacity,
                    'year_id' => $request->year_id,
                ];

                CreateDriverVehicleJob::dispatch($vehicleData);
                $vehicleCreated = true;
            }

            $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
            $uploadedDocTypes = DriverDocument::where('driver_id', $driver->id)
                ->pluck('type')
                ->toArray();

            $missingDocs = array_diff($requiredDocs, $uploadedDocTypes);

            if (empty($missingDocs)) {
                $driver->documents_completed_at = now();
                $driver->onboarding_step = self::STEP_KYC_VERIFICATION;
                $driver->is_active = false;
                $driver->save();

                DB::commit();

                $message = 'All documents uploaded successfully. Please proceed to KYC verification.';
                if ($vehicleCreated) {
                    $message .= ' Vehicle information has been saved.';
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $message,
                    'data' => [
                        'next_step' => self::STEP_KYC_VERIFICATION,
                        'uploaded_documents' => $uploadedDocuments,
                        'vehicle_created' => $vehicleCreated,
                        'requires_admin_approval' => (bool) $driver->vehicle_category_requires_approval,
                    ],
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Documents uploaded successfully',
                'data' => [
                    'next_step' => self::STEP_DOCUMENTS,
                    'uploaded_documents' => $uploadedDocuments,
                    'vehicle_created' => $vehicleCreated,
                    'all_uploaded_types' => $uploadedDocTypes,
                    'missing_documents' => array_values($missingDocs),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Document upload with vehicle failed V2', [
                'error' => $e->getMessage(),
                'driver_id' => $driver->id ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload documents: ' . $e->getMessage(),
            ], 500);
        }
    }
}
