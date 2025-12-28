<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $type
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $type)
    {
        if (!auth()->check()) {
            return $this->unauthorized($request);
        }

        $user = auth()->user();
        $userType = $user->user_type;

        // Handle admin type (includes admin-employee and super-admin)
        if ($type === 'admin' && in_array($userType, ['admin-employee', 'super-admin'])) {
            return $next($request);
        }

        // Handle driver type
        if ($type === 'driver' && $userType === 'driver') {
            return $next($request);
        }

        // Handle customer type
        if ($type === 'customer' && $userType === 'customer') {
            return $next($request);
        }

        // Handle exact match for any other types
        if ($userType === $type) {
            return $next($request);
        }

        return $this->forbidden($request);
    }

    /**
     * Handle unauthorized response
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function unauthorized(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest(route('admin.auth.login'));
    }

    /**
     * Handle forbidden response
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function forbidden(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Unauthorized. Insufficient permissions.'
            ], 403);
        }

        abort(403, 'Unauthorized action.');
    }
}
