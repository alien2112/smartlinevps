<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Database-backed idempotency middleware
 * Prevents duplicate requests from being processed twice
 * More reliable than cache-based solutions
 */
class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to POST/PUT/PATCH methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $next($request);
        }

        // Check for idempotency key in header
        $idempotencyKey = $request->header('Idempotency-Key');

        if (!$idempotencyKey) {
            // If no key provided, generate warning but allow request
            // For critical endpoints, you can make this required
            Log::debug('No idempotency key provided', [
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);
            return $next($request);
        }

        $userId = auth()->id() ?? $request->ip(); // Use IP if not authenticated
        $endpoint = $request->path();

        // Check if this exact request was already processed
        $existing = DB::table('idempotency_keys')
            ->where('idempotency_key', $idempotencyKey)
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            // Request already processed, return cached response
            Log::info('Idempotent request detected, returning cached response', [
                'idempotency_key' => $idempotencyKey,
                'user_id' => $userId,
                'endpoint' => $endpoint,
            ]);

            return response()->json(
                json_decode($existing->response_payload, true),
                $existing->status_code
            )->header('X-Idempotent-Replay', 'true');
        }

        // Process the request
        $response = $next($request);

        // Store the response for future idempotent requests
        // Only store successful responses (2xx)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            try {
                DB::table('idempotency_keys')->insert([
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $userId,
                    'endpoint' => $endpoint,
                    'request_payload' => json_encode($request->except(['password', 'token'])),
                    'response_payload' => $response->getContent(),
                    'status_code' => $response->getStatusCode(),
                    'expires_at' => now()->addHours(24), // Keep for 24 hours
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Duplicate key (concurrent request) - ignore
                Log::warning('Failed to store idempotency key (likely duplicate)', [
                    'idempotency_key' => $idempotencyKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}
