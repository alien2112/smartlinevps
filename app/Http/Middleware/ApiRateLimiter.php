<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprehensive API rate limiting middleware
 * Protects against abuse with different limits per endpoint type
 * 
 * All rate limits are configurable via config/rate_limits.php
 */
class ApiRateLimiter
{
    /**
     * Rate limit configurations loaded from config file
     */
    protected array $limits;

    public function __construct()
    {
        // Load rate limits from config file (allows env var customization)
        $this->limits = config('rate_limits.limits', [
            'general' => ['max' => 60, 'decay' => 60],
        ]);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $limitType
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $limitType = 'general'): Response
    {
        // Determine the limit configuration
        $config = $this->limits[$limitType] ?? $this->limits['general'];

        // Create a unique key per user/IP and endpoint type
        $key = $this->resolveRequestSignature($request, $limitType);

        // Check rate limit
        $maxAttempts = $config['max'];
        $decaySeconds = $config['decay'];

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);

            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'limit_type' => $limitType,
                'user_id' => auth()->id(),
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'retry_after' => $retryAfter,
            ]);

            return response()->json([
                'response_code' => 'rate_limit_exceeded',
                'message' => "Too many requests. Please try again in {$retryAfter} seconds.",
                'content' => null,
                'errors' => [],
                'retry_after' => $retryAfter,
            ], 429)
                ->header('Retry-After', $retryAfter)
                ->header('X-RateLimit-Limit', $maxAttempts)
                ->header('X-RateLimit-Remaining', 0)
                ->header('X-RateLimit-Reset', now()->addSeconds($retryAfter)->timestamp);
        }

        // Increment the hit count
        RateLimiter::hit($key, $decaySeconds);

        // Calculate remaining attempts
        $remaining = max(0, $maxAttempts - RateLimiter::attempts($key));

        // Process the request
        $response = $next($request);

        // Add rate limit headers to response
        return $response
            ->header('X-RateLimit-Limit', $maxAttempts)
            ->header('X-RateLimit-Remaining', $remaining)
            ->header('X-RateLimit-Reset', now()->addSeconds($decaySeconds)->timestamp);
    }

    /**
     * Resolve request signature for rate limiting
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $limitType
     * @return string
     */
    protected function resolveRequestSignature(Request $request, string $limitType): string
    {
        // Use authenticated user ID if available, otherwise IP address
        $identifier = auth()->id() ?? $request->ip();

        return sprintf(
            'rate_limit:%s:%s:%s',
            $limitType,
            $identifier,
            $request->method()
        );
    }
}
