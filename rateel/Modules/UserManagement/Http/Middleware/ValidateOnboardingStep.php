<?php

namespace Modules\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ValidateOnboardingStep Middleware
 * 
 * Validates that the driver is at the correct onboarding step for the current endpoint.
 * Prevents drivers from skipping steps in the onboarding flow.
 */
class ValidateOnboardingStep
{
    /**
     * Steps in order - driver must complete each step before proceeding
     */
    protected array $stepsOrder = [
        'phone',
        'otp',
        'password',
        'register_info',
        'vehicle_type',
        'documents',
        'pending_approval',
        'approved',
    ];

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $requiredStep The step required for this endpoint
     */
    public function handle(Request $request, Closure $next, string $requiredStep): Response
    {
        // Get phone from request
        $phone = $request->input('phone');
        
        if (!$phone) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phone number is required',
            ], 422);
        }

        // Normalize phone
        $phone = $this->normalizePhone($phone);

        // Find driver
        $driver = \Modules\UserManagement\Entities\User::where('phone', $phone)
            ->where('user_type', 'driver')
            ->first();

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver not found',
            ], 404);
        }

        // Get current step index
        $currentStepIndex = array_search($driver->onboarding_step, $this->stepsOrder);
        $requiredStepIndex = array_search($requiredStep, $this->stepsOrder);

        // Driver must be at least at the previous step to access this endpoint
        // (e.g., to set password, driver must have completed OTP verification)
        $minRequiredIndex = max(0, $requiredStepIndex - 1);

        if ($currentStepIndex < $minRequiredIndex) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please complete previous steps first',
                'data' => [
                    'current_step' => $driver->onboarding_step,
                    'next_step' => $driver->onboarding_step,
                ],
            ], 400);
        }

        // Attach driver to request for use in controller
        $request->merge(['_driver' => $driver]);

        return $next($request);
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
}
