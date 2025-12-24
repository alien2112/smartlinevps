<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Middleware to add trace ID to all requests for end-to-end debugging
 * The trace ID is passed through to logs, responses, and Redis events
 */
class TraceIdMiddleware
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
        // Get trace ID from header or generate new one
        $traceId = $request->header('X-Trace-Id') 
            ?? $request->header('x-trace-id')
            ?? 'trace-' . now()->timestamp . '-' . Str::random(8);
        
        // Store in request for use throughout the application
        $request->attributes->set('trace_id', $traceId);
        
        // Add to Log context so all logs include trace_id
        Log::withContext(['trace_id' => $traceId]);
        
        // Log request start
        $startTime = microtime(true);
        
        Log::info('Request started', [
            'trace_id' => $traceId,
            'method' => $request->method(),
            'path' => $request->path(),
            'user_id' => auth('api')->id() ?? 'guest',
            'ip' => $request->ip(),
        ]);
        
        // Process request
        $response = $next($request);
        
        // Calculate duration
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log request end
        Log::info('Request completed', [
            'trace_id' => $traceId,
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
        ]);
        
        // Add trace ID to response header
        $response->headers->set('X-Trace-Id', $traceId);
        
        return $response;
    }
}
