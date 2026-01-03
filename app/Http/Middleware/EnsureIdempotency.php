<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Database-backed idempotency middleware for trip-action requests
 * Prevents duplicate trip accept/reject requests from being processed twice
 */
class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        // Auto-generate idempotency key for critical endpoints if not provided
        // This prevents duplicate requests from Flutter retries
        if (!$idempotencyKey) {
            $tripId = $request->input('trip_request_id') ?? $request->route('trip_request_id');
            $userId = auth()->id();
            $status = $request->input('status');
            
            if ($request->is('*/trip-action')) {
                $action = $request->input('action');
                if ($tripId && $action && $userId) {
                    $idempotencyKey = "auto:{$userId}:{$tripId}:{$action}";
                }
            } elseif ($request->is('*/match-otp')) {
                if ($tripId && $userId) {
                    $idempotencyKey = "auto:otp:{$userId}:{$tripId}";
                }
            } elseif ($request->is('*/update-status/*')) {
                if ($tripId && $status && $userId) {
                    $idempotencyKey = "auto:status:{$userId}:{$tripId}:{$status}";
                }
            }
        }

        // If still no idempotency key, proceed normally
        if (!$idempotencyKey) {
            return $next($request);
        }

        $userId = auth()->id() ?? $request->ip();
        $endpoint = $request->path();

        try {
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
        } catch (\Exception $e) {
            Log::warning('Idempotency check failed, proceeding with request', [
                'error' => $e->getMessage(),
            ]);
        }

        // Process the request WITHOUT wrapping in transaction
        // The controller handles its own transactions
        $response = $next($request);

        // Store the response for future idempotent requests (only for successful responses)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            try {
                DB::table('idempotency_keys')->insertOrIgnore([
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => $userId,
                    'endpoint' => $endpoint,
                    'request_payload' => json_encode($request->except(['password', 'token'])),
                    'response_payload' => $response->getContent(),
                    'status_code' => $response->getStatusCode(),
                    'expires_at' => now()->addHours(24),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Failed to store - not critical, log and continue
                Log::warning('Failed to store idempotency key', [
                    'idempotency_key' => $idempotencyKey,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $response;
    }
}
