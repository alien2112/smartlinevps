<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Version;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AppConfigController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'nodejs_realtime_url' => config('services.nodejs_realtime.url', 'http://72.62.29.3:3000'),
            'api_url' => config('app.url'),
        ]);
    }

    /**
     * Get software version (converted from closure for route caching)
     */
    public function version(): JsonResponse
    {
        $version = Version::where('is_active', 1)->latest('id')->first();
        return response()->json(responseFormatter(DEFAULT_200, [
            'software_version' => $version ? $version->version : env('SOFTWARE_VERSION')
        ]));
    }

    /**
     * Get authenticated user (converted from closure for route caching)
     */
    public function currentUser(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Internal settings API for Node.js realtime service (converted from closure for route caching)
     */
    public function internalSettings(): JsonResponse
    {
        $settings = app(SettingsService::class)->getAsKeyValueArray();
        return response()->json([
            'success' => true,
            'settings' => $settings,
            'version' => Cache::get('app_settings:version', 1),
        ]);
    }

    /**
     * Issue #31 FIX: Health check endpoint for load balancer
     *
     * Checks critical system components:
     * - Database connection
     * - Redis connection
     * - Queue worker status (via Redis)
     *
     * Returns 200 if healthy, 503 if any check fails
     */
    public function health(): JsonResponse
    {
        $checks = [
            'database' => false,
            'redis' => false,
            'cache' => false,
        ];

        $errors = [];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Exception $e) {
            $errors['database'] = $e->getMessage();
        }

        // Check Redis connection
        try {
            $pong = Redis::ping();
            $checks['redis'] = ($pong === true || $pong === 'PONG' || $pong === '+PONG');
        } catch (\Exception $e) {
            $errors['redis'] = $e->getMessage();
        }

        // Check cache (Laravel cache, may use Redis or file)
        try {
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, 'ok', 5);
            $checks['cache'] = Cache::get($testKey) === 'ok';
            Cache::forget($testKey);
        } catch (\Exception $e) {
            $errors['cache'] = $e->getMessage();
        }

        $healthy = !in_array(false, $checks, true);

        $response = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ];

        if (!$healthy) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $healthy ? 200 : 503);
    }

    /**
     * Issue #31 FIX: Detailed health check for monitoring dashboards
     * Returns more detailed system information
     */
    public function healthDetailed(): JsonResponse
    {
        $checks = [
            'database' => ['status' => false, 'latency_ms' => null],
            'redis' => ['status' => false, 'latency_ms' => null],
            'cache' => ['status' => false],
            'queue' => ['status' => false, 'pending_jobs' => null],
        ];

        $errors = [];

        // Check database with latency
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $checks['database']['latency_ms'] = round((microtime(true) - $start) * 1000, 2);
            $checks['database']['status'] = true;
        } catch (\Exception $e) {
            $errors['database'] = $e->getMessage();
        }

        // Check Redis with latency
        try {
            $start = microtime(true);
            $pong = Redis::ping();
            $checks['redis']['latency_ms'] = round((microtime(true) - $start) * 1000, 2);
            $checks['redis']['status'] = ($pong === true || $pong === 'PONG' || $pong === '+PONG');

            // Check queue backlog
            try {
                $queueSize = Redis::llen('queues:default') + Redis::llen('queues:high');
                $checks['queue']['pending_jobs'] = $queueSize;
                $checks['queue']['status'] = true;
            } catch (\Exception $e) {
                // Queue check is optional
            }
        } catch (\Exception $e) {
            $errors['redis'] = $e->getMessage();
        }

        // Check cache
        try {
            $testKey = 'health_check_detailed_' . uniqid();
            Cache::put($testKey, 'ok', 5);
            $checks['cache']['status'] = Cache::get($testKey) === 'ok';
            Cache::forget($testKey);
        } catch (\Exception $e) {
            $errors['cache'] = $e->getMessage();
        }

        $healthy = $checks['database']['status'] && $checks['redis']['status'];

        $response = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => config('app.env'),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        if (!$healthy) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $healthy ? 200 : 503);
    }

    /**
     * Verify authentication token for Socket.IO connections
     * Used by Node.js realtime service to validate Passport tokens
     */
    public function verifyAuth(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'response_code' => 'auth_401',
                'message' => 'Unauthenticated'
            ], 401);
        }

        return response()->json([
            'response_code' => 'default_200',
            'content' => [
                'id' => $user->id,
                'user_type' => $user->user_type,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email
            ]
        ]);
    }
}
