<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Modules\Gateways\Traits\SmsGateway;

class AccountController extends Controller
{
    use SmsGateway;

    /**
     * Get privacy settings
     * GET /api/driver/auth/account/privacy-settings
     */
    public function getPrivacySettings(): JsonResponse
    {
        $driver = auth('api')->user();

        $settings = DB::table('driver_privacy_settings')
            ->where('driver_id', $driver->id)
            ->first();

        if (!$settings) {
            // Create default settings
            $settings = DB::table('driver_privacy_settings')->insertGetId([
                'id' => \Illuminate\Support\Str::uuid(),
                'driver_id' => $driver->id,
                'show_profile_photo' => true,
                'show_phone_number' => false,
                'show_in_leaderboard' => true,
                'share_trip_data_for_improvement' => true,
                'allow_promotional_contacts' => true,
                'data_sharing_with_partners' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $settings = DB::table('driver_privacy_settings')
                ->where('driver_id', $driver->id)
                ->first();
        }

        return response()->json(responseFormatter(DEFAULT_200, $settings));
    }

    /**
     * Update privacy settings
     * PUT /api/driver/auth/account/privacy-settings
     */
    public function updatePrivacySettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'show_profile_photo' => 'sometimes|boolean',
            'show_phone_number' => 'sometimes|boolean',
            'show_in_leaderboard' => 'sometimes|boolean',
            'share_trip_data_for_improvement' => 'sometimes|boolean',
            'allow_promotional_contacts' => 'sometimes|boolean',
            'data_sharing_with_partners' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        DB::table('driver_privacy_settings')
            ->updateOrInsert(
                ['driver_id' => $driver->id],
                array_merge(
                    $request->only([
                        'show_profile_photo',
                        'show_phone_number',
                        'show_in_leaderboard',
                        'share_trip_data_for_improvement',
                        'allow_promotional_contacts',
                        'data_sharing_with_partners',
                    ]),
                    ['updated_at' => now()]
                )
            );

        return response()->json(responseFormatter([
            'response_code' => 'settings_updated_200',
            'message' => translate('Privacy settings updated successfully'),
        ]));
    }

    /**
     * Get emergency contacts
     * GET /api/driver/auth/account/emergency-contacts
     */
    public function getEmergencyContacts(): JsonResponse
    {
        $driver = auth('api')->user();

        $contacts = DB::table('emergency_contacts')
            ->where('driver_id', $driver->id)
            ->orderBy('is_primary', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(responseFormatter(DEFAULT_200, [
            'contacts' => $contacts,
            'count' => $contacts->count(),
        ]));
    }

    /**
     * Create emergency contact
     * POST /api/driver/auth/account/emergency-contacts
     */
    public function createEmergencyContact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'relationship' => 'required|in:spouse,parent,sibling,friend,other',
            'phone' => 'required|string|min:10|max:15',
            'alternate_phone' => 'sometimes|nullable|string|min:10|max:15',
            'is_primary' => 'sometimes|boolean',
            'notify_on_emergency' => 'sometimes|boolean',
            'share_live_location' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        // If setting as primary, unset other primary contacts
        if ($request->boolean('is_primary', false)) {
            DB::table('emergency_contacts')
                ->where('driver_id', $driver->id)
                ->update(['is_primary' => false]);
        }

        $contactId = DB::table('emergency_contacts')->insertGetId([
            'id' => \Illuminate\Support\Str::uuid(),
            'driver_id' => $driver->id,
            'name' => $request->name,
            'relationship' => $request->relationship,
            'phone' => $request->phone,
            'alternate_phone' => $request->input('alternate_phone'),
            'is_primary' => $request->boolean('is_primary', false),
            'notify_on_emergency' => $request->boolean('notify_on_emergency', true),
            'share_live_location' => $request->boolean('share_live_location', false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $contact = DB::table('emergency_contacts')->where('id', $contactId)->first();

        return response()->json(responseFormatter([
            'response_code' => 'contact_created_201',
            'message' => translate('Emergency contact added successfully'),
            'data' => $contact,
        ]), 201);
    }

    /**
     * Update emergency contact
     * PUT /api/driver/auth/account/emergency-contacts/{id}
     */
    public function updateEmergencyContact(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'relationship' => 'sometimes|in:spouse,parent,sibling,friend,other',
            'phone' => 'sometimes|string|min:10|max:15',
            'alternate_phone' => 'sometimes|nullable|string|min:10|max:15',
            'is_primary' => 'sometimes|boolean',
            'notify_on_emergency' => 'sometimes|boolean',
            'share_live_location' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $contact = DB::table('emergency_contacts')
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$contact) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // If setting as primary, unset other primary contacts
        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            DB::table('emergency_contacts')
                ->where('driver_id', $driver->id)
                ->where('id', '!=', $id)
                ->update(['is_primary' => false]);
        }

        DB::table('emergency_contacts')
            ->where('id', $id)
            ->update(array_merge(
                $request->only([
                    'name',
                    'relationship',
                    'phone',
                    'alternate_phone',
                    'is_primary',
                    'notify_on_emergency',
                    'share_live_location',
                ]),
                ['updated_at' => now()]
            ));

        return response()->json(responseFormatter([
            'response_code' => 'contact_updated_200',
            'message' => translate('Emergency contact updated successfully'),
        ]));
    }

    /**
     * Delete emergency contact
     * DELETE /api/driver/auth/account/emergency-contacts/{id}
     */
    public function deleteEmergencyContact(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $deleted = DB::table('emergency_contacts')
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->delete();

        if (!$deleted) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        return response()->json(responseFormatter([
            'response_code' => 'contact_deleted_200',
            'message' => translate('Emergency contact deleted successfully'),
        ]));
    }

    /**
     * Set primary emergency contact
     * POST /api/driver/auth/account/emergency-contacts/{id}/set-primary
     */
    public function setPrimaryContact(string $id): JsonResponse
    {
        $driver = auth('api')->user();

        $contact = DB::table('emergency_contacts')
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->first();

        if (!$contact) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }

        // Unset all primary contacts
        DB::table('emergency_contacts')
            ->where('driver_id', $driver->id)
            ->update(['is_primary' => false]);

        // Set this as primary
        DB::table('emergency_contacts')
            ->where('id', $id)
            ->update(['is_primary' => true, 'updated_at' => now()]);

        return response()->json(responseFormatter([
            'response_code' => 'primary_contact_set_200',
            'message' => translate('Primary contact updated successfully'),
        ]));
    }

    /**
     * Request phone number change
     * POST /api/driver/auth/account/change-phone/request
     */
    public function requestPhoneChange(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_phone' => 'required|string|min:10|max:15',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        // Verify password
        if (!Hash::check($request->password, $driver->password)) {
            return response()->json(responseFormatter([
                'response_code' => 'invalid_password_401',
                'message' => translate('Invalid password'),
            ]), 401);
        }

        // Check if new phone already exists
        $phoneExists = \Modules\UserManagement\Entities\User::where('phone', $request->new_phone)
            ->where('id', '!=', $driver->id)
            ->exists();

        if ($phoneExists) {
            return response()->json(responseFormatter([
                'response_code' => 'phone_exists_400',
                'message' => translate('This phone number is already registered'),
            ]), 400);
        }

        // Check for pending requests
        $pendingRequest = DB::table('phone_change_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($pendingRequest) {
            return response()->json(responseFormatter([
                'response_code' => 'pending_request_exists_400',
                'message' => translate('You already have a pending phone change request'),
                'data' => ['expires_at' => $pendingRequest->expires_at],
            ]), 400);
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create phone change request
        $requestId = DB::table('phone_change_requests')->insertGetId([
            'id' => \Illuminate\Support\Str::uuid(),
            'driver_id' => $driver->id,
            'old_phone' => $driver->phone,
            'new_phone' => $request->new_phone,
            'otp_code' => $otp,
            'old_phone_verified' => false,
            'new_phone_verified' => false,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send OTP to old phone for verification
        try {
            self::send($driver->phone, $otp);
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP for phone change', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(responseFormatter([
            'response_code' => 'otp_sent_200',
            'message' => translate('OTP sent to your current phone number for verification'),
            'data' => [
                'request_id' => $requestId,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]));
    }

    /**
     * Verify old phone with OTP
     * POST /api/driver/auth/account/change-phone/verify-old
     */
    public function verifyOldPhone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $changeRequest = DB::table('phone_change_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$changeRequest) {
            return response()->json(responseFormatter([
                'response_code' => 'request_not_found_404',
                'message' => translate('Phone change request not found or expired'),
            ]), 404);
        }

        if ($changeRequest->otp_code !== $request->otp) {
            return response()->json(responseFormatter([
                'response_code' => 'invalid_otp_400',
                'message' => translate('Invalid OTP'),
            ]), 400);
        }

        // Mark old phone as verified
        DB::table('phone_change_requests')
            ->where('id', $changeRequest->id)
            ->update([
                'old_phone_verified' => true,
                'old_phone_verified_at' => now(),
                'updated_at' => now(),
            ]);

        // Generate new OTP for new phone
        $newOtp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('phone_change_requests')
            ->where('id', $changeRequest->id)
            ->update([
                'otp_code' => $newOtp,
                'updated_at' => now(),
            ]);

        // Send OTP to new phone
        try {
            self::send($changeRequest->new_phone, $newOtp);
        } catch (\Exception $e) {
            \Log::error('Failed to send OTP to new phone', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(responseFormatter([
            'response_code' => 'old_phone_verified_200',
            'message' => translate('Old phone verified. OTP sent to new phone number.'),
        ]));
    }

    /**
     * Verify new phone with OTP and complete change
     * POST /api/driver/auth/account/change-phone/verify-new
     */
    public function verifyNewPhone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        $changeRequest = DB::table('phone_change_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('old_phone_verified', true)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$changeRequest) {
            return response()->json(responseFormatter([
                'response_code' => 'request_not_found_404',
                'message' => translate('Phone change request not found or old phone not verified'),
            ]), 404);
        }

        if ($changeRequest->otp_code !== $request->otp) {
            return response()->json(responseFormatter([
                'response_code' => 'invalid_otp_400',
                'message' => translate('Invalid OTP'),
            ]), 400);
        }

        // Update phone number
        DB::table('users')
            ->where('id', $driver->id)
            ->update([
                'phone' => $changeRequest->new_phone,
                'updated_at' => now(),
            ]);

        // Mark request as completed
        DB::table('phone_change_requests')
            ->where('id', $changeRequest->id)
            ->update([
                'new_phone_verified' => true,
                'new_phone_verified_at' => now(),
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        // Send notification
        DriverNotification::notify(
            $driver->id,
            'account_update',
            translate('Phone Number Changed'),
            translate('Your phone number has been successfully updated'),
            ['old_phone' => $changeRequest->old_phone, 'new_phone' => $changeRequest->new_phone],
            'high',
            'system'
        );

        return response()->json(responseFormatter([
            'response_code' => 'phone_changed_200',
            'message' => translate('Phone number changed successfully'),
            'data' => ['new_phone' => $changeRequest->new_phone],
        ]));
    }

    /**
     * Request account deletion
     * POST /api/driver/auth/account/delete-request
     */
    public function requestAccountDeletion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|in:dissatisfied,privacy_concerns,switching_service,temporary_break,other',
            'additional_comments' => 'sometimes|nullable|string|max:1000',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(
                constant: DEFAULT_400,
                errors: errorProcessor($validator)
            ), 400);
        }

        $driver = auth('api')->user();

        // Verify password
        if (!Hash::check($request->password, $driver->password)) {
            return response()->json(responseFormatter([
                'response_code' => 'invalid_password_401',
                'message' => translate('Invalid password'),
            ]), 401);
        }

        // Check for pending deletion request
        $pendingRequest = DB::table('account_deletion_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->first();

        if ($pendingRequest) {
            return response()->json(responseFormatter([
                'response_code' => 'pending_request_exists_400',
                'message' => translate('You already have a pending account deletion request'),
                'data' => [
                    'requested_at' => $pendingRequest->requested_at,
                    'scheduled_deletion_at' => $pendingRequest->scheduled_deletion_at,
                ],
            ]), 400);
        }

        // Check for active trips
        $activeTrips = DB::table('trip_requests')
            ->where('driver_id', $driver->id)
            ->whereIn('current_status', ['pending', 'accepted', 'ongoing'])
            ->count();

        if ($activeTrips > 0) {
            return response()->json(responseFormatter([
                'response_code' => 'active_trips_exist_400',
                'message' => translate('Cannot delete account with active trips. Please complete or cancel all trips first.'),
            ]), 400);
        }

        // Create deletion request (30-day grace period)
        $requestId = DB::table('account_deletion_requests')->insertGetId([
            'id' => \Illuminate\Support\Str::uuid(),
            'driver_id' => $driver->id,
            'reason' => $request->reason,
            'additional_comments' => $request->input('additional_comments'),
            'status' => 'pending',
            'requested_at' => now(),
            'scheduled_deletion_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Deactivate account immediately
        DB::table('users')
            ->where('id', $driver->id)
            ->update(['is_active' => false]);

        // Send notification
        DriverNotification::notify(
            $driver->id,
            'account_update',
            translate('Account Deletion Requested'),
            translate('Your account will be deleted in 30 days. You can cancel this request anytime.'),
            ['scheduled_deletion_at' => now()->addDays(30)->toIso8601String()],
            'urgent',
            'system'
        );

        return response()->json(responseFormatter([
            'response_code' => 'deletion_requested_200',
            'message' => translate('Account deletion requested. Your account will be deleted in 30 days. You can cancel anytime.'),
            'data' => [
                'request_id' => $requestId,
                'scheduled_deletion_at' => now()->addDays(30)->toIso8601String(),
                'grace_period_days' => 30,
            ],
        ]));
    }

    /**
     * Cancel account deletion request
     * POST /api/driver/auth/account/delete-cancel
     */
    public function cancelDeletionRequest(): JsonResponse
    {
        $driver = auth('api')->user();

        $request = DB::table('account_deletion_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->first();

        if (!$request) {
            return response()->json(responseFormatter([
                'response_code' => 'no_pending_request_404',
                'message' => translate('No pending deletion request found'),
            ]), 404);
        }

        // Cancel request
        DB::table('account_deletion_requests')
            ->where('id', $request->id)
            ->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

        // Reactivate account
        DB::table('users')
            ->where('id', $driver->id)
            ->update(['is_active' => true]);

        return response()->json(responseFormatter([
            'response_code' => 'deletion_cancelled_200',
            'message' => translate('Account deletion request cancelled. Your account is now active.'),
        ]));
    }

    /**
     * Get deletion request status
     * GET /api/driver/auth/account/delete-status
     */
    public function deletionStatus(): JsonResponse
    {
        $driver = auth('api')->user();

        $request = DB::table('account_deletion_requests')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->first();

        if (!$request) {
            return response()->json(responseFormatter(DEFAULT_200, [
                'has_pending_request' => false,
                'status' => 'active',
            ]));
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'has_pending_request' => true,
            'status' => 'pending_deletion',
            'requested_at' => $request->requested_at,
            'scheduled_deletion_at' => $request->scheduled_deletion_at,
            'days_remaining' => now()->diffInDays($request->scheduled_deletion_at),
            'reason' => $request->reason,
        ]));
    }
}
