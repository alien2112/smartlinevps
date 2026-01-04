<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware to authenticate onboarding tokens
 * 
 * Validates that the user has a valid token with 'onboarding' scope.
 * This allows access to onboarding endpoints even if the driver is not fully approved.
 */
class OnboardingAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if user is authenticated
        if (!Auth::guard('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => translate('Unauthorized'),
                'error' => ['code' => 'UNAUTHORIZED'],
            ], 401);
        }

        $user = Auth::guard('api')->user();

        // Verify user is a driver
        if ($user->user_type !== 'driver') {
            return response()->json([
                'success' => false,
                'message' => translate('Access denied'),
                'error' => ['code' => 'INVALID_USER_TYPE'],
            ], 403);
        }

        // Check token scope - strictly enforce onboarding scope
        $token = $user->token();
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => translate('Invalid token'),
                'error' => ['code' => 'INVALID_TOKEN'],
            ], 401);
        }

        $scopes = $token->scopes ?? [];

        // Strictly require onboarding scope for onboarding endpoints
        if (!in_array('onboarding', $scopes)) {
            // Check if this is a driver token (approved driver trying to access onboarding)
            if (in_array('driver', $scopes) || $user->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => translate('Please use the driver app endpoints'),
                    'error' => ['code' => 'USE_DRIVER_ENDPOINTS'],
                ], 403);
            }

            // Token without proper scope - reject
            return response()->json([
                'success' => false,
                'message' => translate('Invalid token scope'),
                'error' => ['code' => 'INVALID_SCOPE'],
            ], 403);
        }

        return $next($request);
    }
}
