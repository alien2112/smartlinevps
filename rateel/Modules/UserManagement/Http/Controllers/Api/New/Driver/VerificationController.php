<?php

namespace Modules\UserManagement\Http\Controllers\Api\New\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\VerificationMedia;
use Modules\UserManagement\Entities\VerificationSession;
use Modules\UserManagement\Service\Interface\VerificationSessionServiceInterface;

class VerificationController extends Controller
{
    protected VerificationSessionServiceInterface $verificationService;

    public function __construct(VerificationSessionServiceInterface $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Create a new verification session or get existing one.
     *
     * POST /api/driver/verification/session
     * Request: { "phone": "01234567890" }
     */
    public function createSession(Request $request): JsonResponse
    {
        // Check if KYC verification feature is enabled
        if (!config('verification.enabled', true)) {
            return response()->json(
                responseFormatter(DEFAULT_200, ['message' => 'KYC verification is currently disabled']),
                200
            );
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(
                responseFormatter(DEFAULT_400, errors: errorProcessor($validator)),
                400
            );
        }

        try {
            // Normalize phone number
            $phone = $this->normalizePhone($request->phone);

            // Find driver by phone
            $user = \Modules\UserManagement\Entities\User::where('phone', $phone)
                ->where('user_type', 'driver')
                ->first();

            if (!$user) {
                return response()->json(
                    responseFormatter(DEFAULT_404, errors: ['message' => 'Driver not found']),
                    404
                );
            }

            $session = $this->verificationService->getOrCreateSession($user->id, 'driver_kyc');

            return response()->json(responseFormatter(DEFAULT_200, [
                'session_id' => $session->id,
                'status' => $session->status,
                'created_at' => $session->created_at->toIso8601String(),
                'existing_media' => $session->media->pluck('kind')->toArray(),
            ]), 200);

        } catch (\Exception $e) {
            return response()->json(
                responseFormatter(DEFAULT_500, errors: ['message' => $e->getMessage()]),
                500
            );
        }
    }

    /**
     * Upload media for a verification session.
     *
     * POST /api/driver/verification/session/{id}/upload
     * Request: { "phone": "01234567890", "kind": "selfie", "file": <file> }
     */
    public function uploadMedia(Request $request, string $id): JsonResponse
    {
        // Check if KYC verification feature is enabled
        if (!config('verification.enabled', true)) {
            return response()->json(
                responseFormatter(DEFAULT_200, ['message' => 'KYC verification is currently disabled']),
                200
            );
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
            'kind' => 'required|in:selfie,liveness_video,id_front,id_back',
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json(
                responseFormatter(DEFAULT_400, errors: errorProcessor($validator)),
                400
            );
        }

        try {
            // Normalize phone number
            $phone = $this->normalizePhone($request->phone);

            // Find driver by phone
            $user = \Modules\UserManagement\Entities\User::where('phone', $phone)
                ->where('user_type', 'driver')
                ->first();

            if (!$user) {
                return response()->json(
                    responseFormatter(DEFAULT_404, errors: ['message' => 'Driver not found']),
                    404
                );
            }

            // Verify session belongs to user
            $session = $this->verificationService->getStatusForUser($user->id, 'driver_kyc');

            if (!$session || $session->id !== $id) {
                return response()->json(
                    responseFormatter(DEFAULT_404, errors: ['message' => 'Session not found']),
                    404
                );
            }

            if (!$session->canBeSubmitted()) {
                return response()->json(
                    responseFormatter(DEFAULT_400, errors: ['message' => 'Session is already submitted or processed']),
                    400
                );
            }

            $media = $this->verificationService->storeMedia(
                $id,
                $request->input('kind'),
                $request->file('file')
            );

            return response()->json(responseFormatter(DEFAULT_200, [
                'media_id' => $media->id,
                'kind' => $media->kind,
                'size' => $media->size,
                'uploaded_at' => $media->created_at->toIso8601String(),
            ]), 200);

        } catch (\InvalidArgumentException $e) {
            return response()->json(
                responseFormatter(DEFAULT_400, errors: ['message' => $e->getMessage()]),
                400
            );
        } catch (\Exception $e) {
            return response()->json(
                responseFormatter(DEFAULT_500, errors: ['message' => $e->getMessage()]),
                500
            );
        }
    }

    /**
     * Submit session for verification processing.
     *
     * POST /api/driver/verification/session/{id}/submit
     * Request: { "phone": "01234567890" }
     */
    public function submitSession(Request $request, string $id): JsonResponse
    {
        // Check if KYC verification feature is enabled
        if (!config('verification.enabled', true)) {
            return response()->json(
                responseFormatter(DEFAULT_200, ['message' => 'KYC verification is currently disabled']),
                200
            );
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(
                responseFormatter(DEFAULT_400, errors: errorProcessor($validator)),
                400
            );
        }

        try {
            // Normalize phone number
            $phone = $this->normalizePhone($request->phone);

            // Find driver by phone
            $user = \Modules\UserManagement\Entities\User::where('phone', $phone)
                ->where('user_type', 'driver')
                ->first();

            if (!$user) {
                return response()->json(
                    responseFormatter(DEFAULT_404, errors: ['message' => 'Driver not found']),
                    404
                );
            }

            // Verify session belongs to user
            $session = $this->verificationService->getStatusForUser($user->id, 'driver_kyc');

            if (!$session || $session->id !== $id) {
                return response()->json(
                    responseFormatter(DEFAULT_404, errors: ['message' => 'Session not found']),
                    404
                );
            }

            $this->verificationService->submitSession($id);

            // Auto-complete KYC verification (always pass)
            $user->update([
                'kyc_verified_at' => now(),
                'onboarding_step' => 'pending_approval',
                'onboarding_state' => 'pending_approval',
            ]);

            return response()->json(responseFormatter(DEFAULT_200, [
                'message' => 'KYC verification completed successfully. Driver moved to pending approval.',
                'session_id' => $id,
                'status' => VerificationSession::STATUS_PENDING,
                'next_step' => 'pending_approval',
                'kyc_verified_at' => now()->toIso8601String(),
            ]), 200);

        } catch (\RuntimeException $e) {
            return response()->json(
                responseFormatter(DEFAULT_400, errors: ['message' => $e->getMessage()]),
                400
            );
        } catch (\Exception $e) {
            return response()->json(
                responseFormatter(DEFAULT_500, errors: ['message' => $e->getMessage()]),
                500
            );
        }
    }

    /**
     * Get current verification status for the driver.
     *
     * GET /api/driver/verification/status?phone=01234567890
     */
    public function getStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|min:10|max:15',
        ]);

        if ($validator->fails()) {
            return response()->json(
                responseFormatter(DEFAULT_400, errors: errorProcessor($validator)),
                400
            );
        }

        try {
            // Normalize phone number
            $phone = $this->normalizePhone($request->phone);

            // Find driver by phone
            $user = \Modules\UserManagement\Entities\User::where('phone', $phone)
                ->where('user_type', 'driver')
                ->first();

            if (!$user) {
                return response()->json(
                    responseFormatter(DEFAULT_404, errors: ['message' => 'Driver not found']),
                    404
                );
            }

            $session = $this->verificationService->getStatusForUser($user->id, 'driver_kyc');

            if (!$session) {
                return response()->json(responseFormatter(DEFAULT_200, [
                    'has_session' => false,
                    'kyc_status' => $user->kyc_status ?? 'not_required',
                ]), 200);
            }

            $data = [
                'has_session' => true,
                'session_id' => $session->id,
                'status' => $session->status,
                'decision' => $session->decision,
                'kyc_status' => $user->kyc_status ?? 'not_required',
                'created_at' => $session->created_at->toIso8601String(),
                'submitted_at' => $session->submitted_at?->toIso8601String(),
                'processed_at' => $session->processed_at?->toIso8601String(),
                'existing_media' => $session->media->pluck('kind')->toArray(),
            ];

            // Include scores if processed
            if ($session->processed_at) {
                $data['scores'] = [
                    'liveness' => $session->liveness_score,
                    'face_match' => $session->face_match_score,
                    'doc_auth' => $session->doc_auth_score,
                ];
            }

            // Include extracted fields if available
            if ($session->extracted_fields) {
                $data['extracted_fields'] = $session->extracted_fields;
            }

            // Include rejection reasons if rejected
            if ($session->decision === VerificationSession::DECISION_REJECTED && $session->decision_reason_codes) {
                $data['reason_codes'] = $session->decision_reason_codes;
            }

            return response()->json(responseFormatter(DEFAULT_200, $data), 200);

        } catch (\Exception $e) {
            return response()->json(
                responseFormatter(DEFAULT_500, errors: ['message' => $e->getMessage()]),
                500
            );
        }
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
}
