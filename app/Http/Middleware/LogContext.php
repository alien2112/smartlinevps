<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to inject correlation ID and request context into all logs
 * This enables distributed tracing across multiple VPS servers
 */
class LogContext
{
    /**
     * Handle an incoming request
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or retrieve correlation ID
        $correlationId = $request->header('X-Correlation-ID') ?? (string) Str::uuid();
        $request->headers->set('X-Correlation-ID', $correlationId);

        // Get user info if authenticated
        $userId = null;
        $userType = null;
        if (auth()->check()) {
            $userId = auth()->id();
            $userType = auth()->user()->user_type ?? null;
        }

        // Set log context for ALL subsequent logs in this request
        Log::withContext([
            'correlation_id' => $correlationId,
            'vps_id' => gethostname(),
            'user_id' => $userId,
            'user_type' => $userType,
            'ip' => $request->ip(),
            'method' => $request->method(),
            'uri' => $this->sanitizeUri($request->getRequestUri()),
            'user_agent' => $request->userAgent(),
        ]);

        // Log incoming request
        Log::info('request_started', [
            'body_size' => $request->header('Content-Length', 0),
        ]);

        // Process request and capture timing
        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        // Add correlation ID to response headers for client-side tracing
        $response->headers->set('X-Correlation-ID', $correlationId);

        // Log request completion
        Log::info('request_completed', [
            'status' => $response->status(),
            'duration_ms' => round($duration * 1000, 2),
        ]);

        // Log slow requests (> 1 second)
        if ($duration > 1.0) {
            Log::channel('performance')->warning('slow_request', [
                'duration_ms' => round($duration * 1000, 2),
                'threshold_ms' => 1000,
            ]);
        }

        return $response;
    }

    /**
     * Sanitize URI to remove sensitive data from logs
     *
     * @param string $uri
     * @return string
     */
    private function sanitizeUri(string $uri): string
    {
        // Remove query parameters that might contain sensitive data
        $sensitiveParams = ['api_key', 'token', 'password', 'secret', 'otp'];

        foreach ($sensitiveParams as $param) {
            $uri = preg_replace('/([?&]' . $param . '=)[^&]*/', '$1***', $uri);
        }

        return $uri;
    }
}
