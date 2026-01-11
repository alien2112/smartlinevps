<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use App\Http\Controllers\Controller;
use App\Jobs\CreateDriverVehicleJob;
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
 * 4. Registration Info → 5. Vehicle Type → 6. Documents → 7. KYC Verification →
 * 8. Pending Approval → 9. Approved
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
    public const STEP_KYC_VERIFICATION = 'kyc_verification';
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

                Log::info('New driver created for onboarding', ['driver_id' => $driver->id, 'phone' => $phone]);
            }

            // Check if phone number is already verified
            if ($driver->otp_verified_at) {
                // Phone already verified - determine next step and skip OTP
                $nextStep = $this->determineNextStep($driver);
                $driver->onboarding_step = $nextStep;
                $driver->save();

                DB::commit();

                $response = [
                    'status' => 'success',
                    'message' => 'Phone number already verified',
                    'data' => [
                        'next_step' => $nextStep,
                        'phone' => $phone,
                        'is_new_driver' => false,
                        'phone_verified' => true,
                    ],
                ];

                // Issue token for any driver who has set password (regardless of approval status)
                if ($driver->password) {
                    $token = $driver->createToken('driver-token')->accessToken;
                    $response['data']['token'] = $token;
                    $response['data']['driver'] = $this->getDriverProfile($driver);

                    if ($nextStep === self::STEP_APPROVED && $driver->is_active) {
                        $response['message'] = 'Driver approved and authenticated successfully';
                    } else {
                        $response['message'] = 'Driver authenticated. Continue onboarding.';
                    }
                }

                return response()->json($response);
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
     * Step 3: Set Password
     * POST /api/driver/auth/set-password
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
            'city_id' => 'required|exists:zones,id',
            'referral_code' => 'nullable|string|size:8', // Optional referral code
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

            // Check if driver can be referred by this user
            if ($referrer->id === $driver->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot refer yourself.',
                ], 400);
            }

            // Check if driver already has a referrer
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
        $driver->city_id = $request->city_id;
        $driver->register_completed_at = now();
        $driver->onboarding_step = self::STEP_VEHICLE_TYPE;

        // Set referrer if provided
        if ($referrer) {
            $driver->referred_by = $referrer->id;
            $referrer->increment('referral_count');

            // Create referral invite record for admin tracking
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
     * POST /api/driver/auth/vehicle-type
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

        // Define auto-approved categories (Taxi, Scooter, Uncategorized)
        // Other categories require admin approval after document submission
        $autoApprovedCategories = [
            'd4d1e8f1-c716-4cff-96e1-c0b312a1a58b', // Taxi
            '89060926-153c-4c43-a881-c2ea0eb47402', // سكوتر (Scooter)
            'd8e5a6e1-bf60-46a8-959a-22a18bdcd764', // Uncategorized
        ];

        // Check if this category requires admin approval
        $requiresAdminApproval = !in_array($request->vehicle_category_id, $autoApprovedCategories);

        $driver->vehicle_category_id = $request->vehicle_category_id;
        $driver->selected_vehicle_type = $vehicleCategory->type;
        $driver->travel_enabled = $request->boolean('travel_enabled', false);
        $driver->vehicle_selected_at = now();
        $driver->onboarding_step = self::STEP_DOCUMENTS;

        // Store whether this category requires admin approval
        // This will be checked after document upload
        $driver->vehicle_category_requires_approval = $requiresAdminApproval;

        $driver->save();

        // Get required documents for this vehicle type
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
     * Step 6a: Upload ID Documents (Front + Back)
     * POST /api/driver/auth/upload/id
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
     * Step 6b: Upload License Documents (Front + Back) + Optional Vehicle Info
     * POST /api/driver/auth/upload/license
     *
     * Can optionally include vehicle information:
     * - brand_id, model_id, category_id, licence_plate_number,
     *   licence_expire_date, ownership, fuel_type, etc.
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
     * Step 6b (Alternative): Upload Driving License Documents (Front + Back)
     * POST /api/driver/auth/upload/driving-license
     * Alternative endpoint using driving_license_front and driving_license_back field names
     */
    public function uploadDrivingLicense(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'driving_license_front' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'driving_license_back' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',

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
            'driving_license_front' => DriverDocument::TYPE_LICENSE_FRONT,
            'driving_license_back' => DriverDocument::TYPE_LICENSE_BACK,
        ]);
    }

    /**
     * Step 6c: Upload Car Photo Documents (Front + Back)
     * POST /api/driver/auth/upload/car_photo
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
     * Step 6d: Upload Selfie Document
     * POST /api/driver/auth/upload/selfie
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

    /**
     * Update ID Documents (Front and/or Back)
     * PUT /api/driver/auth/update/id
     */
    public function updateId(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'id_front' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'id_back' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // At least one file must be provided
        if (!$request->hasFile('id_front') && !$request->hasFile('id_back')) {
            return response()->json([
                'status' => 'error',
                'message' => 'At least one document file must be provided',
            ], 422);
        }

        $documentsToUpdate = [];
        if ($request->hasFile('id_front')) {
            $documentsToUpdate['id_front'] = DriverDocument::TYPE_ID_FRONT;
        }
        if ($request->hasFile('id_back')) {
            $documentsToUpdate['id_back'] = DriverDocument::TYPE_ID_BACK;
        }

        return $this->handleDocumentUpdate($request, $documentsToUpdate);
    }

    /**
     * Update License Documents (Front and/or Back)
     * PUT /api/driver/auth/update/license
     */
    public function updateLicense(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'license_front' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'license_back' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$request->hasFile('license_front') && !$request->hasFile('license_back')) {
            return response()->json([
                'status' => 'error',
                'message' => 'At least one document file must be provided',
            ], 422);
        }

        $documentsToUpdate = [];
        if ($request->hasFile('license_front')) {
            $documentsToUpdate['license_front'] = DriverDocument::TYPE_LICENSE_FRONT;
        }
        if ($request->hasFile('license_back')) {
            $documentsToUpdate['license_back'] = DriverDocument::TYPE_LICENSE_BACK;
        }

        return $this->handleDocumentUpdate($request, $documentsToUpdate);
    }

    /**
     * Update Car Photo Documents (Front and/or Back)
     * PUT /api/driver/auth/update/car_photo
     */
    public function updateCarPhoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'car_front' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'car_back' => 'sometimes|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$request->hasFile('car_front') && !$request->hasFile('car_back')) {
            return response()->json([
                'status' => 'error',
                'message' => 'At least one document file must be provided',
            ], 422);
        }

        $documentsToUpdate = [];
        if ($request->hasFile('car_front')) {
            $documentsToUpdate['car_front'] = DriverDocument::TYPE_CAR_FRONT;
        }
        if ($request->hasFile('car_back')) {
            $documentsToUpdate['car_back'] = DriverDocument::TYPE_CAR_BACK;
        }

        return $this->handleDocumentUpdate($request, $documentsToUpdate);
    }

    /**
     * Update Selfie Document
     * PUT /api/driver/auth/update/selfie
     */
    public function updateSelfie(Request $request): JsonResponse
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

        return $this->handleDocumentUpdate($request, [
            'selfie' => DriverDocument::TYPE_SELFIE,
        ]);
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
            case self::STEP_KYC_VERIFICATION:
            case self::STEP_PENDING_APPROVAL:
                $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
                $uploadedDocs = DriverDocument::where('driver_id', $driver->id)
                    ->pluck('type')
                    ->toArray();
                $missingDocs = array_diff($requiredDocs, $uploadedDocs);

                $response['data']['uploaded_documents'] = $uploadedDocs;
                $response['data']['vehicle_type'] = $driver->selected_vehicle_type;

                // Issue token even at these steps (not approved yet)
                if ($driver->password) {
                    $token = $driver->createToken('driver-token')->accessToken;
                    $response['data']['token'] = $token;
                    $response['data']['driver'] = $this->getDriverProfile($driver);
                }
                break;

            case self::STEP_APPROVED:
                // Driver is approved - issue token
                if ($driver->password) {
                    $token = $driver->createToken('driver-token')->accessToken;
                    $response['data']['token'] = $token;
                    $response['data']['driver'] = $this->getDriverProfile($driver);

                    if ($driver->is_active) {
                        $response['message'] = 'Driver approved and authenticated successfully';
                    } else {
                        $response['message'] = 'Driver approved but account is inactive. Please contact support.';
                    }
                }
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

        // Check if phone number is already verified
        if ($driver->otp_verified_at) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phone number already verified. No need to resend OTP.',
                'data' => [
                    'phone_verified' => true,
                    'next_step' => $this->determineNextStep($driver),
                ],
            ], 400);
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

    /**
     * Get all documents (uploaded + missing)
     * GET /api/driver/auth/documents
     *
     * Returns a comprehensive view of all documents including:
     * - Uploaded documents with their details
     * - Missing documents that need to be uploaded
     * - Summary statistics
     */
    public function getDocuments(Request $request): JsonResponse
    {
        // Accept phone from query parameter (GET request)
        $validator = Validator::make($request->query(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($request->query('phone'));

        $driver = User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Get required documents based on vehicle type
        $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);

        // Get all uploaded documents
        $uploadedDocuments = DriverDocument::where('driver_id', $driver->id)
            ->get();

        // Map uploaded documents with full details
        $uploadedDocsFormatted = $uploadedDocuments->map(function ($doc) {
            return [
                'id' => $doc->uuid,
                'type' => $doc->type,
                'type_name' => DriverDocument::getTypeName($doc->type),
                'file_url' => $doc->file_url,
                'original_name' => $doc->original_name,
                'mime_type' => $doc->mime_type,
                'file_size' => $doc->file_size,
                'status' => $doc->status,
                'verified' => $doc->verified,
                'verified_at' => $doc->verified_at?->toDateTimeString(),
                'rejection_reason' => $doc->rejection_reason,
                'uploaded_at' => $doc->created_at->toDateTimeString(),
            ];
        });

        // Get uploaded document types
        $uploadedTypes = $uploadedDocuments->pluck('type')->toArray();

        // Calculate missing documents
        $missingTypes = array_diff($requiredDocs, $uploadedTypes);

        // Calculate statistics
        $verifiedCount = $uploadedDocuments->where('verified', true)->count();
        $pendingCount = $uploadedDocuments->where('verified', false)
            ->whereNull('rejection_reason')
            ->count();
        $rejectedCount = $uploadedDocuments->where('verified', false)
            ->whereNotNull('rejection_reason')
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Documents retrieved successfully',
            'data' => [
                'uploaded_documents' => $uploadedDocsFormatted->values(),
                'summary' => [
                    'total_required' => count($requiredDocs),
                    'total_uploaded' => $uploadedDocuments->count(),
                    'total_missing' => count($missingTypes),
                    'total_verified' => $verifiedCount,
                    'total_pending' => $pendingCount,
                    'total_rejected' => $rejectedCount,
                    'all_documents_uploaded' => empty($missingTypes),
                    'all_documents_verified' => $verifiedCount === count($requiredDocs) && count($requiredDocs) > 0,
                ],
                'vehicle_type' => $driver->selected_vehicle_type,
                'required_document_types' => $requiredDocs,
            ],
        ]);
    }

    /**
     * Skip/Complete KYC verification and move to pending approval
     *
     * POST /api/driver/auth/skip-kyc
     * Body: { "phone": "01234567890" }
     */
    public function skipKycVerification(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
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

            // Check if driver is at KYC verification step
            if ($driver->onboarding_step !== self::STEP_KYC_VERIFICATION) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Driver is not at KYC verification step',
                    'current_step' => $driver->onboarding_step,
                ], 400);
            }

            // Mark KYC as verified (bypass) and move to pending approval
            $driver->update([
                'kyc_verified_at' => now(),
                'onboarding_step' => self::STEP_PENDING_APPROVAL,
                'onboarding_state' => 'pending_approval',
            ]);

            $driver->refresh();

            Log::info('Driver skipped KYC verification', [
                'driver_id' => $driver->id,
                'phone' => $phone,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'KYC verification skipped. Driver moved to pending approval.',
                'data' => [
                    'next_step' => self::STEP_PENDING_APPROVAL,
                    'onboarding_step' => $driver->onboarding_step,
                    'onboarding_state' => $driver->onboarding_state,
                    'kyc_verified_at' => $driver->kyc_verified_at ? $driver->kyc_verified_at->toIso8601String() : null,
                ],
            ], 200);

        } catch (\Exception $e) {
            Log::error('Skip KYC verification failed', [
                'phone' => $request->phone,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to skip KYC verification',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        // If KYC not completed → kyc_verification step
        if (!$driver->kyc_verified_at) {
            return self::STEP_KYC_VERIFICATION;
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
    public function sendOtpViaBeon(string $phone): array
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
     * Handle multiple document upload
     *
     * @param Request $request
     * @param array $documentMap Map of request field names to document types
     * @return JsonResponse
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

        // Validate flow - vehicle type must be selected first
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

            // Upload each document
            foreach ($documentMap as $fieldName => $documentType) {
                if (!$request->hasFile($fieldName)) {
                    continue;
                }

                $file = $request->file($fieldName);
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('driver-documents/' . $driver->id, $fileName, 'public');

                // Delete old file if exists
                $existingDoc = DriverDocument::where('driver_id', $driver->id)
                    ->where('type', $documentType)
                    ->first();

                if ($existingDoc && $existingDoc->file_path) {
                    Storage::disk('public')->delete($existingDoc->file_path);
                }

                // Create or update document record
                $document = DriverDocument::updateOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'type' => $documentType,
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

                $uploadedDocuments[] = [
                    'type' => $documentType,
                    'id' => $document->uuid,
                    'file_url' => $document->file_url,
                    'original_name' => $document->original_name,
                ];
            }

            // Check if all required documents are uploaded
            $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
            $uploadedDocTypes = DriverDocument::where('driver_id', $driver->id)
                ->pluck('type')
                ->toArray();

            // Handle license documents specially - accept either license_front/back OR driving_license_front/back
            $missingDocs = array_diff($requiredDocs, $uploadedDocTypes);

            // If driving_license is missing, check if old license_front/back was uploaded instead
            if (isset($missingDocs['driving_license_front']) || isset($missingDocs['driving_license_back'])) {
                if (DriverDocument::hasLicenseDocuments($driver->id)) {
                    unset($missingDocs['driving_license_front']);
                    unset($missingDocs['driving_license_back']);
                }
            }

            if (empty($missingDocs)) {
                // All documents uploaded - move to KYC verification
                $driver->documents_completed_at = now();
                $driver->onboarding_step = self::STEP_KYC_VERIFICATION;
                $driver->is_active = false; // Ensure driver is not active until approved
                $driver->save();

                DB::commit();

                $message = 'All documents uploaded successfully. Please proceed to KYC verification.';

                return response()->json([
                    'status' => 'success',
                    'message' => $message,
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

            Log::error('Multi-document upload failed', [
                'error' => $e->getMessage(),
                'driver_id' => $driver->id ?? null,
                'documents' => array_keys($documentMap),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload documents: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle document update
     *
     * @param Request $request
     * @param array $documentMap Map of request field names to document types
     * @return JsonResponse
     */
    protected function handleDocumentUpdate(Request $request, array $documentMap): JsonResponse
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

        try {
            DB::beginTransaction();

            $updatedDocuments = [];

            // Update each document
            foreach ($documentMap as $fieldName => $documentType) {
                if (!$request->hasFile($fieldName)) {
                    continue;
                }

                // Check if document exists
                $existingDoc = DriverDocument::where('driver_id', $driver->id)
                    ->where('type', $documentType)
                    ->first();

                if (!$existingDoc) {
                    DB::rollBack();
                    return response()->json([
                        'status' => 'error',
                        'message' => "Document type '{$documentType}' not found. Please upload it first.",
                    ], 404);
                }

                $file = $request->file($fieldName);
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('driver-documents/' . $driver->id, $fileName, 'public');

                // Delete old file
                if ($existingDoc->file_path) {
                    Storage::disk('public')->delete($existingDoc->file_path);
                }

                // Update document record
                $existingDoc->update([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'verified' => false,
                    'verified_at' => null,
                    'verified_by' => null,
                    'rejection_reason' => null,
                ]);

                $updatedDocuments[] = [
                    'type' => $documentType,
                    'id' => $existingDoc->uuid,
                    'file_url' => $existingDoc->file_url,
                    'original_name' => $existingDoc->original_name,
                ];
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Documents updated successfully',
                'data' => [
                    'updated_documents' => $updatedDocuments,
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Document update failed', [
                'error' => $e->getMessage(),
                'driver_id' => $driver->id ?? null,
                'documents' => array_keys($documentMap),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update documents: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle multiple document upload with optional vehicle information
     *
     * @param Request $request
     * @param array $documentMap Map of request field names to document types
     * @return JsonResponse
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

        // Validate flow - vehicle type must be selected first
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
            $vehicleData = null;

            // Fetch all existing documents for this driver in ONE query
            $documentTypes = array_values($documentMap);
            $existingDocs = DriverDocument::where('driver_id', $driver->id)
                ->whereIn('type', $documentTypes)
                ->get()
                ->keyBy('type');

            // Upload each document
            foreach ($documentMap as $fieldName => $documentType) {
                if (!$request->hasFile($fieldName)) {
                    continue;
                }

                $file = $request->file($fieldName);
                $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('driver-documents/' . $driver->id, $fileName, 'public');

                // Delete old file if exists
                $existingDoc = $existingDocs->get($documentType);
                if ($existingDoc && $existingDoc->file_path) {
                    Storage::disk('public')->delete($existingDoc->file_path);
                }

                // Create or update document record
                $document = DriverDocument::updateOrCreate(
                    [
                        'driver_id' => $driver->id,
                        'type' => $documentType,
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

                $uploadedDocuments[] = [
                    'type' => $documentType,
                    'id' => $document->uuid,
                    'file_url' => $document->file_url,
                    'original_name' => $document->original_name,
                ];
            }

            // Check if vehicle information was provided
            if ($request->has('brand_id') && $request->has('model_id') && $request->has('licence_plate_number')) {
                // Prepare vehicle data for async job
                $vehicleData = [
                    'brand_id' => $request->brand_id,
                    'model_id' => $request->model_id,
                    'category_id' => $request->category_id ?? $driver->vehicle_category_id,
                    'licence_plate_number' => $request->licence_plate_number,
                ];

                // Add optional fields if provided
                if ($request->filled('licence_expire_date')) {
                    $vehicleData['licence_expire_date'] = $request->licence_expire_date;
                }
                if ($request->filled('ownership')) {
                    $vehicleData['ownership'] = $request->ownership;
                }
                if ($request->filled('fuel_type')) {
                    $vehicleData['fuel_type'] = $request->fuel_type;
                }
                if ($request->filled('vin_number')) {
                    $vehicleData['vin_number'] = $request->vin_number;
                }
                if ($request->filled('transmission')) {
                    $vehicleData['transmission'] = $request->transmission;
                }
                if ($request->filled('parcel_weight_capacity')) {
                    $vehicleData['parcel_weight_capacity'] = $request->parcel_weight_capacity;
                }
                if ($request->filled('year_id')) {
                    $vehicleData['year_id'] = $request->year_id;
                }

                // Dispatch async job to create/update vehicle (non-blocking)
                CreateDriverVehicleJob::dispatch($driver->id, $vehicleData);

                $vehicleCreated = true;

                Log::info('Vehicle creation job dispatched', [
                    'driver_id' => $driver->id,
                    'vehicle_data' => $vehicleData,
                ]);
            }

            // Check if all required documents are uploaded
            $requiredDocs = DriverDocument::getRequiredDocuments($driver->selected_vehicle_type);
            $uploadedDocTypes = DriverDocument::where('driver_id', $driver->id)
                ->pluck('type')
                ->toArray();

            // Handle license documents specially - accept either license_front/back OR driving_license_front/back
            $missingDocs = array_diff($requiredDocs, $uploadedDocTypes);

            // If driving_license is missing, check if old license_front/back was uploaded instead
            if (isset($missingDocs['driving_license_front']) || isset($missingDocs['driving_license_back'])) {
                if (DriverDocument::hasLicenseDocuments($driver->id)) {
                    unset($missingDocs['driving_license_front']);
                    unset($missingDocs['driving_license_back']);
                }
            }

            if (empty($missingDocs)) {
                // All documents uploaded - move to KYC verification
                $driver->documents_completed_at = now();
                $driver->onboarding_step = self::STEP_KYC_VERIFICATION;
                $driver->is_active = false; // Ensure driver is not active until approved
                $driver->save();

                DB::commit();

                // Customize message
                $message = 'All documents uploaded successfully.';
                if ($vehicleCreated) {
                    $message .= ' Vehicle information is being processed.';
                }
                $message .= ' Please proceed to KYC verification.';

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

            $message = 'Documents uploaded successfully';
            if ($vehicleCreated) {
                $message .= ' and vehicle information is being processed';
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
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

            Log::error('Multi-document upload with vehicle failed', [
                'error' => $e->getMessage(),
                'driver_id' => $driver->id ?? null,
                'documents' => array_keys($documentMap),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upload documents: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get basic driver profile
     */
    protected function getDriverProfile(User $driver): array
    {
        $profile = [
            'id' => $driver->uuid ?? $driver->id,
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'phone' => $driver->phone,
            'email' => $driver->email,
            'profile_image' => $driver->profile_image ? asset('storage/' . $driver->profile_image) : null,
            'has_vehicle' => $driver->vehicle_selected_at || $driver->website_selected_at || $driver->vehicle()->exists(),
            'is_approved' => $driver->is_active,
            'ref_code' => $driver->ref_code,
            'referral_count' => $driver->referral_count ?? 0,
        ];

        // Add referrer information if driver was referred
        if ($driver->referred_by) {
            $referrer = User::find($driver->referred_by);
            if ($referrer) {
                $profile['referred_by'] = [
                    'id' => $referrer->id,
                    'name' => trim($referrer->first_name . ' ' . $referrer->last_name),
                    'ref_code' => $referrer->ref_code,
                ];
            }
        }

        return $profile;
    }
}
