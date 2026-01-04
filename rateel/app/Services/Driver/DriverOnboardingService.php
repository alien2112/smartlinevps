<?php

namespace App\Services\Driver;

use App\Enums\DriverOnboardingState;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAddress;
use App\Services\BeOnOtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\QueryException;
use Carbon\Carbon;

/**
 * Driver Onboarding Service
 *
 * Manages the complete driver onboarding lifecycle including:
 * - Session management
 * - OTP generation and verification
 * - State machine transitions
 * - Document handling
 * - Profile management
 */
class DriverOnboardingService
{
    protected OnboardingRateLimiter $rateLimiter;
    protected BeOnOtpService $beonOtpService;

    public function __construct(OnboardingRateLimiter $rateLimiter, BeOnOtpService $beonOtpService)
    {
        $this->rateLimiter = $rateLimiter;
        $this->beonOtpService = $beonOtpService;
    }

    // ============================================
    // Session Management
    // ============================================

    /**
     * Create a new onboarding session and send OTP
     *
     * @return array{success: bool, data?: array, error?: array}
     */
    public function startOnboarding(string $phone, string $ip, ?string $deviceId = null): array
    {
        // Check rate limits
        $rateCheck = $this->rateLimiter->checkOtpSend($phone, $ip, $deviceId);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'RATE_LIMITED',
                    'message' => $this->getRateLimitMessage($rateCheck['reason'] ?? 'unknown'),
                    'retry_after' => $rateCheck['retry_after'] ?? null,
                    'retry_after_at' => $rateCheck['retry_after_at'] ?? null,
                    'locked_until' => $rateCheck['locked_until'] ?? null,
                ],
            ];
        }

        // Normalize phone
        $normalizedPhone = $this->normalizePhone($phone);
        $phoneHash = $this->hashPhone($normalizedPhone);

        return DB::transaction(function () use ($normalizedPhone, $phoneHash, $ip, $deviceId) {
            // Check for existing pending session
            $existingSession = DB::table('driver_onboarding_sessions')
                ->where('phone_hash', $phoneHash)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->first();

            // If existing session, check resend cooldown
            if ($existingSession) {
                $cooldownCheck = $this->rateLimiter->checkResendCooldown($existingSession->onboarding_id);
                if (!$cooldownCheck['allowed']) {
                    return [
                        'success' => false,
                        'error' => [
                            'code' => 'RESEND_COOLDOWN',
                            'message' => translate('Please wait before requesting a new code'),
                            'retry_after' => $cooldownCheck['retry_after'],
                            'retry_after_at' => $cooldownCheck['retry_after_at'],
                            'onboarding_id' => $existingSession->onboarding_id,
                        ],
                    ];
                }
            }

            // Check if user already exists with this phone
            $existingUser = User::where('phone', $normalizedPhone)
                ->where('user_type', 'driver')
                ->first();

            // Generate OTP
            $otp = $this->generateOtp();
            $otpHash = Hash::make($otp);
            $otpTtl = config('driver_onboarding.otp.ttl_minutes', 5);
            $sessionTtl = config('driver_onboarding.session.ttl_hours', 24);

            if ($existingSession) {
                // Update existing session
                $resendCount = $existingSession->resend_count + 1;
                $maxResends = config('driver_onboarding.otp.max_resends_per_session', 3);

                if ($resendCount > $maxResends) {
                    return [
                        'success' => false,
                        'error' => [
                            'code' => 'MAX_RESENDS',
                            'message' => translate('Maximum resend attempts reached. Please start over.'),
                        ],
                    ];
                }

                DB::table('driver_onboarding_sessions')
                    ->where('id', $existingSession->id)
                    ->update([
                        'otp_hash' => $otpHash,
                        'otp_expires_at' => now()->addMinutes($otpTtl),
                        'otp_attempts' => 0,
                        'resend_count' => $resendCount,
                        'last_resend_at' => now(),
                        'updated_at' => now(),
                    ]);

                $onboardingId = $existingSession->onboarding_id;
                $resendsRemaining = $maxResends - $resendCount;
            } else {
                // Create new session
                $onboardingId = 'onb_' . Str::random(16);

                DB::table('driver_onboarding_sessions')->insert([
                    'id' => Str::uuid(),
                    'onboarding_id' => $onboardingId,
                    'phone' => $normalizedPhone,
                    'phone_hash' => $phoneHash,
                    'driver_id' => $existingUser?->id,
                    'device_id' => $deviceId,
                    'ip_address' => $ip,
                    'otp_hash' => $otpHash,
                    'otp_expires_at' => now()->addMinutes($otpTtl),
                    'otp_attempts' => 0,
                    'resend_count' => 0,
                    'status' => 'pending',
                    'expires_at' => now()->addHours($sessionTtl),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $resendsRemaining = config('driver_onboarding.otp.max_resends_per_session', 3);
            }

            // Record rate limit
            $this->rateLimiter->recordOtpSend($normalizedPhone, $ip, $deviceId);
            $this->rateLimiter->setResendCooldown($onboardingId);

            // Send OTP via SMS
            $this->sendOtpSms($normalizedPhone, $otp);

            Log::info('Driver onboarding OTP sent', [
                'onboarding_id' => $onboardingId,
                'phone_masked' => $this->maskPhone($normalizedPhone),
                'is_returning' => (bool) $existingUser,
            ]);

            return [
                'success' => true,
                'data' => [
                    'onboarding_id' => $onboardingId,
                    'phone_masked' => $this->maskPhone($normalizedPhone),
                    'otp_expires_at' => now()->addMinutes($otpTtl)->toIso8601String(),
                    'otp_length' => config('driver_onboarding.otp.length', 6),
                    'resend_available_at' => now()->addSeconds(config('driver_onboarding.otp.resend_cooldown_seconds', 60))->toIso8601String(),
                    'resends_remaining' => $resendsRemaining,
                    'next_step' => 'verify_otp',
                    'onboarding_state' => DriverOnboardingState::OTP_PENDING->value,
                    'state_version' => 1,
                ],
            ];
        });
    }

    /**
     * Verify OTP and issue onboarding token
     *
     * @return array{success: bool, data?: array, error?: array}
     */
    public function verifyOtp(string $onboardingId, string $otp, ?string $deviceId = null): array
    {
        // Check verification rate limit
        $rateCheck = $this->rateLimiter->checkOtpVerify($onboardingId);
        if (!$rateCheck['allowed']) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'VERIFY_LOCKED',
                    'message' => translate('Too many failed attempts. Please request a new code.'),
                    'must_resend' => true,
                    'retry_after' => $rateCheck['retry_after'],
                ],
            ];
        }

        try {
            return DB::transaction(function () use ($onboardingId, $otp, $deviceId, $rateCheck) {
                // Get session
                $session = DB::table('driver_onboarding_sessions')
                    ->where('onboarding_id', $onboardingId)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$session) {
                    return [
                        'success' => false,
                        'error' => [
                            'code' => 'SESSION_NOT_FOUND',
                            'message' => translate('Session not found or expired. Please start over.'),
                        ],
                    ];
                }

            // Check session expiry
            if (Carbon::parse($session->expires_at)->isPast()) {
                DB::table('driver_onboarding_sessions')
                    ->where('id', $session->id)
                    ->update(['status' => 'expired']);

                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_EXPIRED',
                        'message' => translate('Session expired. Please start over.'),
                    ],
                ];
            }

            // Check OTP expiry
            if (Carbon::parse($session->otp_expires_at)->isPast()) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'OTP_EXPIRED',
                        'message' => translate('Code expired. Please request a new one.'),
                        'can_resend' => true,
                    ],
                ];
            }

            // Verify OTP (OTP still uses bcrypt for security, only password uses SHA256)
            if (!Hash::check($otp, $session->otp_hash)) {
                $this->rateLimiter->recordOtpVerifyAttempt($onboardingId, false);

                $attemptsRemaining = $rateCheck['attempts_remaining'] ?? 0;

                return [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_OTP',
                        'message' => translate('Invalid code. Please try again.'),
                        'attempts_remaining' => $attemptsRemaining,
                    ],
                ];
            }

            // OTP verified successfully
            $this->rateLimiter->recordOtpVerifyAttempt($onboardingId, true);

            // Get or create driver user
            $driver = $this->getOrCreateDriver($session->phone, $deviceId);
            $isReturning = $driver->wasRecentlyCreated === false;

            // Update session
            DB::table('driver_onboarding_sessions')
                ->where('id', $session->id)
                ->update([
                    'driver_id' => $driver->id,
                    'device_id' => $deviceId,
                    'status' => 'verified',
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

            // Update driver state
            $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? 'otp_pending');
            $newState = DriverOnboardingState::OTP_VERIFIED;

            // Only transition if not already past this state
            if ($currentState === DriverOnboardingState::OTP_PENDING) {
                $driver->update([
                    'onboarding_state' => $newState->value,
                    'onboarding_state_version' => $driver->onboarding_state_version + 1,
                    'is_phone_verified' => true,
                    'phone_verified_at' => now(),
                    'otp_verified_at' => now(),
                ]);
                $currentState = $newState;
            }

            // Generate onboarding token
            $token = $driver->createToken('driver-onboarding', ['onboarding'])->accessToken;

            // Prepare response based on driver's current state
            $responseData = [
                'token' => $token,
                'token_type' => 'Bearer',
                'token_expires_at' => now()->addHours(config('driver_onboarding.session.token_ttl_hours', 48))->toIso8601String(),
                'token_scope' => 'onboarding',
                'driver_id' => $driver->id,
                'next_step' => $currentState->nextStep(),
                'onboarding_state' => $currentState->value,
                'state_version' => $driver->onboarding_state_version,
                'is_returning' => $isReturning,
            ];

            // Add profile data for returning drivers
            if ($isReturning && $driver->first_name) {
                $responseData['profile'] = [
                    'first_name' => $driver->first_name,
                    'phone_masked' => $this->maskPhone($driver->phone),
                ];
            }

            // Add missing documents if relevant
            if (in_array($currentState->value, ['vehicle_selected', 'documents_pending'])) {
                $responseData['missing_documents'] = $this->getMissingDocuments($driver->id);
            }

            Log::info('Driver OTP verified', [
                'driver_id' => $driver->id,
                'onboarding_state' => $currentState->value,
                'is_returning' => $isReturning,
            ]);

                return [
                    'success' => true,
                    'data' => $responseData,
                ];
            });
        } catch (QueryException $e) {
            // Handle database errors
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();

            Log::error('Database error during OTP verification', [
                'onboarding_id' => $onboardingId,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'sql_state' => $e->errorInfo[0] ?? null,
            ]);

            // Handle specific database errors
            if (str_contains($errorMessage, 'foreign key constraint') || $errorCode === '23000') {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_INVALID',
                        'message' => translate('Session is invalid. Please start over.'),
                    ],
                ];
            }

            if (str_contains($errorMessage, 'Duplicate entry')) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'DUPLICATE_ENTRY',
                        'message' => translate('This phone number is already registered. Please login instead.'),
                    ],
                ];
            }

            // Generic database error
            return [
                'success' => false,
                'error' => [
                    'code' => 'DATABASE_ERROR',
                    'message' => translate('An error occurred. Please try again later.'),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Unexpected error during OTP verification', [
                'onboarding_id' => $onboardingId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => [
                    'code' => 'UNEXPECTED_ERROR',
                    'message' => translate('An unexpected error occurred. Please try again.'),
                ],
            ];
        }
    }

    /**
     * Resend OTP for an existing session
     */
    public function resendOtp(string $onboardingId, string $ip, ?string $deviceId = null): array
    {
        $session = DB::table('driver_onboarding_sessions')
            ->where('onboarding_id', $onboardingId)
            ->where('status', 'pending')
            ->first();

        if (!$session) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'SESSION_NOT_FOUND',
                    'message' => translate('Session not found. Please start over.'),
                ],
            ];
        }

        // Use startOnboarding which handles resend logic
        return $this->startOnboarding($session->phone, $ip, $deviceId);
    }

    // ============================================
    // State Machine Operations
    // ============================================

    /**
     * Get driver's current onboarding status
     */
    public function getStatus(\Modules\UserManagement\Entities\User $driver): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state ?? 'otp_pending');

        $status = [
            'driver_id' => $driver->id,
            'phone_masked' => $this->maskPhone($driver->phone),
            'next_step' => $currentState->nextStep(),
            'onboarding_state' => $currentState->value,
            'state_version' => $driver->onboarding_state_version ?? 1,
            'progress_percentage' => $currentState->progressPercentage(),
            'is_approved' => $driver->is_approved ?? false,
            'created_at' => $driver->created_at?->toIso8601String(),
        ];

        // Add profile if exists
        if ($driver->first_name) {
            $status['profile'] = [
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'email' => $driver->email,
                'national_id_masked' => $driver->identification_number
                    ? '***********' . substr($driver->identification_number, -4)
                    : null,
            ];
        }

        // Add vehicle if selected
        $vehicle = $driver->primaryVehicle;
        if ($vehicle) {
            $status['vehicle'] = [
                'id' => $vehicle->id,
                'type' => $vehicle->category?->name,
                'category_id' => $vehicle->category_id,
                'brand' => $vehicle->brand?->name,
                'model' => $vehicle->model?->name,
                'licence_plate' => $vehicle->licence_plate_number,
            ];
        }

        // Add documents status
        $status['documents'] = $this->getDocumentsStatus($driver->id);

        // Add rejection reasons if rejected
        if ($currentState === DriverOnboardingState::REJECTED) {
            $status['rejection_reasons'] = $this->getRejectionReasons($driver->id);
        }

        return $status;
    }

    /**
     * Transition to a new state with validation
     */
    public function transitionState(\Modules\UserManagement\Entities\User $driver, DriverOnboardingState $newState): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        if (!$currentState->canTransitionTo($newState)) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE_TRANSITION',
                    'message' => translate('Please complete the previous step first'),
                    'current_state' => $currentState->value,
                    'expected_state' => $newState->value,
                    'next_step' => $currentState->nextStep(),
                ],
            ];
        }

        $driver->update([
            'onboarding_state' => $newState->value,
            'onboarding_state_version' => $driver->onboarding_state_version + 1,
        ]);

        return [
            'success' => true,
            'data' => [
                'next_step' => $newState->nextStep(),
                'onboarding_state' => $newState->value,
                'state_version' => $driver->onboarding_state_version,
            ],
        ];
    }

    /**
     * Validate current state matches expected state
     */
    public function validateState(\Modules\UserManagement\Entities\User $driver, DriverOnboardingState $expectedState): ?array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        if ($currentState !== $expectedState) {
            return [
                'code' => 'INVALID_STATE_TRANSITION',
                'message' => translate('Please complete the previous step first'),
                'current_state' => $currentState->value,
                'expected_state' => $expectedState->value,
                'next_step' => $currentState->nextStep(),
            ];
        }

        return null;
    }

    // ============================================
    // Profile & Vehicle Operations
    // ============================================

    /**
     * Set driver password
     */
    public function setPassword(\Modules\UserManagement\Entities\User $driver, string $password): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        // Validate state
        if ($currentState !== DriverOnboardingState::OTP_VERIFIED) {
            return [
                'success' => false,
                'error' => $this->buildStateError($currentState, DriverOnboardingState::OTP_VERIFIED),
            ];
        }

        $driver->update([
            'password' => Hash::make($password),
            'password_set_at' => now(),
            'onboarding_state' => DriverOnboardingState::PASSWORD_SET->value,
            'onboarding_state_version' => $driver->onboarding_state_version + 1,
        ]);

        return [
            'success' => true,
            'data' => [
                'next_step' => DriverOnboardingState::PASSWORD_SET->nextStep(),
                'onboarding_state' => DriverOnboardingState::PASSWORD_SET->value,
                'state_version' => $driver->onboarding_state_version,
            ],
        ];
    }

    /**
     * Submit driver profile
     */
    public function submitProfile(\Modules\UserManagement\Entities\User $driver, array $data): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        // Validate state
        if ($currentState !== DriverOnboardingState::PASSWORD_SET) {
            return [
                'success' => false,
                'error' => $this->buildStateError($currentState, DriverOnboardingState::PASSWORD_SET),
            ];
        }

        return DB::transaction(function () use ($driver, $data) {
            $driver->update([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'identification_number' => $data['national_id'],
                'identification_type' => 'national_id',
                'email' => $data['email'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'first_name_ar' => $data['first_name_ar'] ?? null,
                'last_name_ar' => $data['last_name_ar'] ?? null,
                'government' => $data['government'] ?? null,
                'is_profile_complete' => true,
                'register_completed_at' => now(),
                'onboarding_state' => DriverOnboardingState::PROFILE_COMPLETE->value,
                'onboarding_state_version' => $driver->onboarding_state_version + 1,
            ]);

            // Save address if provided
            if (!empty($data['address']) || !empty($data['street']) || !empty($data['city'])) {
                UserAddress::updateOrCreate(
                    [
                        'user_id' => $driver->id,
                        'address_label' => $data['address_label'] ?? 'Home',
                    ],
                    [
                        'user_id' => $driver->id,
                        'address' => $data['address'] ?? null,
                        'street' => $data['street'] ?? null,
                        'house' => $data['house'] ?? null,
                        'city' => $data['city'] ?? null,
                        'zip_code' => $data['zip_code'] ?? null,
                        'country' => $data['country'] ?? null,
                        'zone_id' => $data['zone_id'] ?? null,
                        'latitude' => $data['latitude'] ?? null,
                        'longitude' => $data['longitude'] ?? null,
                        'address_label' => $data['address_label'] ?? 'Home',
                    ]
                );
            }

            return [
                'success' => true,
                'data' => [
                    'next_step' => DriverOnboardingState::PROFILE_COMPLETE->nextStep(),
                    'onboarding_state' => DriverOnboardingState::PROFILE_COMPLETE->value,
                    'state_version' => $driver->onboarding_state_version,
                ],
            ];
        });
    }

    /**
     * Select vehicle type and details
     */
    public function selectVehicle(\Modules\UserManagement\Entities\User $driver, array $data): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        // Validate state
        if ($currentState !== DriverOnboardingState::PROFILE_COMPLETE) {
            return [
                'success' => false,
                'error' => $this->buildStateError($currentState, DriverOnboardingState::PROFILE_COMPLETE),
            ];
        }

        return DB::transaction(function () use ($driver, $data) {
            // Deactivate any existing primary vehicles
            DB::table('vehicles')
                ->where('driver_id', $driver->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            // Create new vehicle (brand/model collected in documents phase)
            $vehicleId = Str::uuid();
            $vehicleData = [
                'id' => $vehicleId,
                'driver_id' => $driver->id,
                'category_id' => $data['vehicle_category_id'],
                'is_primary' => true,
                'is_active' => false, // Not active until approved
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Add optional fields only if provided
            if (!empty($data['brand_id'])) {
                $vehicleData['brand_id'] = $data['brand_id'];
            }
            if (!empty($data['model_id'])) {
                $vehicleData['model_id'] = $data['model_id'];
            }
            if (!empty($data['year_id'])) {
                $vehicleData['year_id'] = $data['year_id'];
            }
            if (!empty($data['licence_plate'])) {
                $vehicleData['licence_plate_number'] = $data['licence_plate'];
            }

            DB::table('vehicles')->insert($vehicleData);

            $driver->update([
                'selected_vehicle_type' => $data['vehicle_category_id'],
                'vehicle_selected_at' => now(),
                'onboarding_state' => DriverOnboardingState::VEHICLE_SELECTED->value,
                'onboarding_state_version' => $driver->onboarding_state_version + 1,
            ]);

            // Get required documents
            $requiredDocs = $this->getRequiredDocuments();

            return [
                'success' => true,
                'data' => [
                    'vehicle_id' => $vehicleId,
                    'next_step' => DriverOnboardingState::VEHICLE_SELECTED->nextStep(),
                    'onboarding_state' => DriverOnboardingState::VEHICLE_SELECTED->value,
                    'state_version' => $driver->onboarding_state_version,
                    'required_documents' => $requiredDocs,
                    'missing_documents' => array_keys(array_filter($requiredDocs, fn($doc) => $doc['required'])),
                ],
            ];
        });
    }

    // ============================================
    // Document Operations
    // ============================================

    /**
     * Upload a document
     */
    public function uploadDocument(\Modules\UserManagement\Entities\User $driver, string $type, UploadedFile $file): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        // Allow upload in vehicle_selected or documents_pending states
        if (!in_array($currentState, [DriverOnboardingState::VEHICLE_SELECTED, DriverOnboardingState::DOCUMENTS_PENDING])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_STATE',
                    'message' => translate('Please complete vehicle selection first'),
                    'current_state' => $currentState->value,
                    'next_step' => $currentState->nextStep(),
                ],
            ];
        }

        // Validate document type
        $docConfig = config("driver_onboarding.documents.required.{$type}");
        if (!$docConfig) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_DOCUMENT_TYPE',
                    'message' => translate('Invalid document type'),
                    'allowed_types' => array_keys(config('driver_onboarding.documents.required')),
                ],
            ];
        }

        // Validate file
        $maxSize = $docConfig['max_size_mb'] * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'FILE_TOO_LARGE',
                    'message' => translate('File size exceeds maximum allowed'),
                    'max_size_mb' => $docConfig['max_size_mb'],
                    'provided_size_mb' => round($file->getSize() / 1024 / 1024, 2),
                ],
            ];
        }

        if (!in_array($file->getMimeType(), $docConfig['allowed_mimes'])) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_FILE_TYPE',
                    'message' => translate('Invalid file type'),
                    'allowed_mimes' => $docConfig['allowed_mimes'],
                    'provided_mime' => $file->getMimeType(),
                ],
            ];
        }

        return DB::transaction(function () use ($driver, $type, $file, $docConfig) {
            // Check upload limit
            $existingCount = DB::table('driver_documents')
                ->where('driver_id', $driver->id)
                ->where('type', $type)
                ->count();

            $maxUploads = config('driver_onboarding.documents.max_uploads_per_type', 5);
            if ($existingCount >= $maxUploads) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'MAX_UPLOADS_REACHED',
                        'message' => translate('Maximum upload attempts reached for this document type'),
                    ],
                ];
            }

            // Deactivate previous uploads of same type
            DB::table('driver_documents')
                ->where('driver_id', $driver->id)
                ->where('type', $type)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Store file
            $storagePath = config('driver_onboarding.documents.storage_path', 'driver-documents');
            $filePath = $file->store("{$storagePath}/{$driver->id}", config('driver_onboarding.documents.storage_disk', 'public'));
            $fileHash = hash_file('sha256', $file->getRealPath());

            // Create document record
            $docId = Str::uuid();
            DB::table('driver_documents')->insert([
                'id' => $docId,
                'uuid' => $docId,
                'driver_id' => $driver->id,
                'type' => $type,
                'file_path' => $filePath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'verified' => false,
                'verification_status' => 'pending',
                'version' => $existingCount + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Check if all required documents are uploaded
            $missingDocs = $this->getMissingDocuments($driver->id);
            $allUploaded = empty($missingDocs);

            // Update state if all documents uploaded
            if ($allUploaded && $driver->onboarding_state === DriverOnboardingState::VEHICLE_SELECTED->value) {
                $driver->update([
                    'documents_completed_at' => now(),
                    'onboarding_state' => DriverOnboardingState::DOCUMENTS_PENDING->value,
                    'onboarding_state_version' => $driver->onboarding_state_version + 1,
                ]);
            }

            $currentState = DriverOnboardingState::fromString($driver->fresh()->onboarding_state);

            return [
                'success' => true,
                'data' => [
                    'document' => [
                        'id' => $docId,
                        'type' => $type,
                        'label' => $docConfig['label'],
                        'status' => 'pending',
                        'uploaded_at' => now()->toIso8601String(),
                    ],
                    'next_step' => $currentState->nextStep(),
                    'onboarding_state' => $currentState->value,
                    'state_version' => $driver->onboarding_state_version,
                    'missing_documents' => $missingDocs,
                    'all_documents_uploaded' => $allUploaded,
                ],
            ];
        });
    }

    /**
     * Submit application for review
     */
    public function submitForReview(\Modules\UserManagement\Entities\User $driver, bool $termsAccepted, bool $privacyAccepted): array
    {
        $currentState = DriverOnboardingState::fromString($driver->onboarding_state);

        if ($currentState !== DriverOnboardingState::DOCUMENTS_PENDING) {
            return [
                'success' => false,
                'error' => $this->buildStateError($currentState, DriverOnboardingState::DOCUMENTS_PENDING),
            ];
        }

        // Verify all required documents are uploaded
        $missingDocs = $this->getMissingDocuments($driver->id);
        if (!empty($missingDocs)) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'DOCUMENTS_INCOMPLETE',
                    'message' => translate('Please upload all required documents'),
                    'missing_documents' => $missingDocs,
                ],
            ];
        }

        $driver->update([
            'terms_accepted_at' => $termsAccepted ? now() : null,
            'privacy_accepted_at' => $privacyAccepted ? now() : null,
            'onboarding_state' => DriverOnboardingState::PENDING_APPROVAL->value,
            'onboarding_state_version' => $driver->onboarding_state_version + 1,
        ]);

        Log::info('Driver submitted for approval', [
            'driver_id' => $driver->id,
            'phone' => $this->maskPhone($driver->phone),
        ]);

        return [
            'success' => true,
            'data' => [
                'next_step' => DriverOnboardingState::PENDING_APPROVAL->nextStep(),
                'onboarding_state' => DriverOnboardingState::PENDING_APPROVAL->value,
                'state_version' => $driver->onboarding_state_version,
                'estimated_review_time' => '24-48 hours',
                'message' => translate('Your application has been submitted for review'),
            ],
        ];
    }

    // ============================================
    // Helper Methods
    // ============================================

    private function getOrCreateDriver(string $phone, ?string $deviceId): User
    {
        try {
            $driver = User::where('phone', $phone)
                ->where('user_type', 'driver')
                ->first();

            if ($driver) {
                $driver->wasRecentlyCreated = false;
                return $driver;
            }

            // Check if user exists with different user_type
            $existingUser = User::where('phone', $phone)->first();
            if ($existingUser) {
                Log::warning('Phone number exists with different user type', [
                    'phone' => $this->maskPhone($phone),
                    'existing_user_type' => $existingUser->user_type,
                ]);
                throw new \Exception('Phone number already registered as ' . $existingUser->user_type);
            }

            $driver = User::create([
                'id' => Str::uuid(),
                'phone' => $phone,
                'user_type' => 'driver',
                'is_active' => false,
                'is_approved' => false,
                'is_phone_verified' => true,
                'onboarding_state' => DriverOnboardingState::OTP_PENDING->value,
                'onboarding_state_version' => 1,
                'device_fingerprint' => $deviceId,
                'ref_code' => strtoupper(Str::random(8)),
            ]);

            $driver->wasRecentlyCreated = true;
            return $driver;
        } catch (QueryException $e) {
            Log::error('Database error creating driver', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            // Re-throw to be handled by caller
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating driver', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function generateOtp(): string
    {
        $length = config('driver_onboarding.otp.length', 6);
        return str_pad((string) random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function sendOtpSms(string $phone, string $otp): void
    {
        try {
            // Check if BeOn OTP is enabled
            if (!config('services.beon_otp.enabled', true)) {
                Log::warning('BeOn OTP is disabled, OTP not sent', [
                    'phone' => $this->maskPhone($phone),
                ]);
                return;
            }

            // Send OTP via BeOn OTP Service
            $result = $this->beonOtpService->sendOtp($phone, $otp, 'SmartLine');

            if ($result['success']) {
                Log::info('Driver onboarding OTP sent via BeOn', [
                    'phone' => $this->maskPhone($phone),
                    'response' => $result['data'] ?? null,
                ]);
            } else {
                Log::error('Failed to send OTP via BeOn', [
                    'phone' => $this->maskPhone($phone),
                    'error' => $result['message'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending OTP via BeOn', [
                'phone' => $this->maskPhone($phone),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters except leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Ensure + prefix for international format
        if (!str_starts_with($phone, '+')) {
            // Assume Egyptian number if starts with 0
            if (str_starts_with($phone, '0')) {
                $phone = '+20' . substr($phone, 1);
            } elseif (str_starts_with($phone, '20')) {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    private function hashPhone(string $phone): string
    {
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        return hash('sha256', $normalized . config('app.key'));
    }

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

    private function getRequiredDocuments(): array
    {
        $docs = config('driver_onboarding.documents.required', []);
        $result = [];

        foreach ($docs as $type => $config) {
            $result[$type] = [
                'type' => $type,
                'label' => $config['label'],
                'max_size_mb' => $config['max_size_mb'],
                'allowed_mimes' => $config['allowed_mimes'],
                'required' => $config['required'] ?? true,
            ];
        }

        return $result;
    }

    private function getMissingDocuments(string $driverId): array
    {
        $required = config('driver_onboarding.documents.required', []);
        $uploaded = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->where('is_active', true)
            ->pluck('type')
            ->toArray();

        $missing = [];
        foreach ($required as $type => $config) {
            if (($config['required'] ?? true) && !in_array($type, $uploaded)) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    private function getDocumentsStatus(string $driverId): array
    {
        $required = array_keys(config('driver_onboarding.documents.required', []));

        $uploaded = DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->where('is_active', true)
            ->get()
            ->map(fn($doc) => [
                'type' => $doc->type,
                'status' => $doc->verification_status ?? ($doc->verified ? 'approved' : 'pending'),
                'uploaded_at' => $doc->created_at,
                'rejection_reason' => $doc->rejection_reason,
            ]);

        $uploadedTypes = $uploaded->pluck('type')->toArray();
        $missing = array_diff($required, $uploadedTypes);
        $rejected = $uploaded->where('status', 'rejected')->values();

        return [
            'required' => $required,
            'uploaded' => $uploaded->toArray(),
            'missing' => array_values($missing),
            'rejected' => $rejected->toArray(),
        ];
    }

    private function getRejectionReasons(string $driverId): array
    {
        return DB::table('driver_documents')
            ->where('driver_id', $driverId)
            ->where('verification_status', 'rejected')
            ->whereNotNull('rejection_reason')
            ->select('type', 'rejection_reason', 'reviewed_at')
            ->get()
            ->toArray();
    }

    private function buildStateError(DriverOnboardingState $current, DriverOnboardingState $expected): array
    {
        return [
            'code' => 'INVALID_STATE_TRANSITION',
            'message' => translate('Please complete the previous step first'),
            'current_state' => $current->value,
            'expected_state' => $expected->value,
            'next_step' => $current->nextStep(),
        ];
    }

    private function getRateLimitMessage(string $reason): string
    {
        return match ($reason) {
            'phone_locked' => translate('This phone number is temporarily locked. Please try again later.'),
            'phone_hourly_limit' => translate('Too many attempts. Please try again in an hour.'),
            'phone_daily_limit' => translate('Daily limit reached. Please try again tomorrow.'),
            'ip_hourly_limit', 'ip_daily_limit' => translate('Too many requests from your network. Please try again later.'),
            'device_limit' => translate('Too many attempts from this device. Please try again later.'),
            'server_busy' => translate('Server is busy. Please try again in a moment.'),
            default => translate('Too many attempts. Please try again later.'),
        };
    }
}
