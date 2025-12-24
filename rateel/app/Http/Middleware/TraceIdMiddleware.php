<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * TraceIdMiddleware
 * 
 * Generates or propagates a unique trace ID for every request.
 * This enables end-to-end request tracing across Laravel, Node.js, and logs.
 * 
 * Usage:
 * - Client can send X-Trace-Id header to propagate existing trace
 * - Response includes X-Trace-Id header for client correlation
 * - All logs within request context include trace_id
 */
class TraceIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get trace ID from header or generate new one
        $traceId = $request->header('X-Trace-Id') ?: $this->generateTraceId();
        
        // Store in request for access throughout the application
        $request->attributes->set('trace_id', $traceId);
        
        // Add to Log context so all subsequent logs include trace_id
        Log::withContext([
            'trace_id' => $traceId,
            'request_id' => $this->generateRequestId(),
            'user_agent' => substr($request->userAgent() ?? 'unknown', 0, 100),
            'ip' => $request->ip(),
        ]);
        
        // Log request start
        $this->logRequestStart($request, $traceId);
        
        // Process request
        $startTime = microtime(true);
        $response = $next($request);
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log request completion
        $this->logRequestEnd($request, $response, $traceId, $duration);
        
        // Add trace ID to response header
        $response->headers->set('X-Trace-Id', $traceId);
        $response->headers->set('X-Request-Duration-Ms', (string) $duration);
        
        return $response;
    }
    
    /**
     * Generate a unique trace ID
     * Format: trc_<timestamp>_<random>
     */
    private function generateTraceId(): string
    {
        return 'trc_' . time() . '_' . Str::random(8);
    }
    
    /**
     * Generate a unique request ID
     */
    private function generateRequestId(): string
    {
        return 'req_' . Str::uuid()->toString();
    }
    
    /**
     * Log request start with key information
     */
    private function logRequestStart(Request $request, string $traceId): void
    {
        $context = [
            'trace_id' => $traceId,
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'route' => $request->route()?->getName() ?? $request->path(),
        ];
        
        // Add user info if authenticated
        if ($user = $request->user('api')) {
            $context['user_id'] = $user->id;
            $context['user_type'] = $user->user_type ?? 'unknown';
        }
        
        // Add ride/trip ID if present in request
        $tripId = $request->input('trip_request_id') ?? $request->route('trip_request_id');
        if ($tripId) {
            $context['trip_id'] = $tripId;
        }
        
        Log::info('API Request Started', $context);
    }
    
    /**
     * Log request completion with response info
     */
    private function logRequestEnd(Request $request, Response $response, string $traceId, float $duration): void
    {
        $context = [
            'trace_id' => $traceId,
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
        ];
        
        // Warn on slow requests
        $level = 'info';
        if ($duration > 3000) {
            $level = 'warning';
            $context['slow_request'] = true;
        }
        
        // Log errors with more detail
        if ($response->getStatusCode() >= 400) {
            $level = 'warning';
            if ($response->getStatusCode() >= 500) {
                $level = 'error';
            }
        }
        
        Log::log($level, 'API Request Completed', $context);
    }
}
