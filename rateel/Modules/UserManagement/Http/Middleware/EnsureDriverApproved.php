<?php

namespace Modules\UserManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureDriverApproved Middleware
 * 
 * Ensures that only fully approved drivers can access protected routes.
 * Use this middleware on any route that requires a fully onboarded driver.
 */
class EnsureDriverApproved
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Only apply to drivers
        if ($user->user_type !== 'driver') {
            return $next($request);
        }

        // Check if driver is fully approved
        if ($user->onboarding_step !== 'approved') {
            return response()->json([
                'status' => 'error',
                'message' => 'Driver onboarding not complete',
                'data' => [
                    'next_step' => $user->onboarding_step,
                    'is_approved' => false,
                ],
            ], 403);
        }

        // Check if driver is active
        if (!$user->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Account is deactivated. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
