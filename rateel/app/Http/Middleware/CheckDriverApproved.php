<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\DriverOnboardingState;

class CheckDriverApproved
{
    /**
     * Handle an incoming request.
     * Ensures only approved drivers can access driver app endpoints.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Check if user exists and is a driver
        if (!$user || $user->user_type !== 'driver') {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Not a driver account',
                'error_code' => 'INVALID_USER_TYPE',
            ], 403);
        }

        // Check if driver is approved
        if (!$user->is_approved) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account has not been approved yet. Please wait for admin approval.',
                'error_code' => 'DRIVER_NOT_APPROVED',
                'onboarding_state' => $user->onboarding_state,
            ], 403);
        }

        // Check if onboarding state is APPROVED
        try {
            $currentState = DriverOnboardingState::fromString($user->onboarding_state ?? 'otp_pending');
            if ($currentState !== DriverOnboardingState::APPROVED) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your onboarding is not complete. Current status: ' . $currentState->value,
                    'error_code' => 'ONBOARDING_INCOMPLETE',
                    'current_step' => $currentState->nextStep(),
                    'onboarding_state' => $currentState->value,
                ], 403);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid onboarding state',
                'error_code' => 'INVALID_STATE',
            ], 403);
        }

        return $next($request);
    }
}
