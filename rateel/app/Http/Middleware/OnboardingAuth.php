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

        // Check token scope (Passport tokens have scopes)
        // For Passport, we check if the token has the onboarding scope
        // If scopes aren't configured, we'll allow any authenticated driver token
        $token = $user->token();
        if ($token) {
            $scopes = $token->scopes ?? [];
            // If Passport scopes are configured and token doesn't have onboarding scope
            if (!empty($scopes) && !in_array('onboarding', $scopes)) {
                // Token exists but doesn't have onboarding scope
                // This might be a full driver token - check if approved
                if ($user->is_approved) {
                    return response()->json([
                        'success' => false,
                        'message' => translate('Please use the driver login endpoint'),
                        'error' => ['code' => 'USE_DRIVER_LOGIN'],
                    ], 403);
                }
            }
            // If scopes are empty (not configured), allow any authenticated driver
        }

        return $next($request);
    }
}
