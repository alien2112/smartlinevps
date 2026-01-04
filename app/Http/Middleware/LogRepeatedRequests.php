<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class LogRepeatedRequests
{
    /**
     * Handle an incoming request and log repeated calls
     */
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        
        // Only track specific endpoints
        $trackedEndpoints = [
            'api/customer/configuration',
            'api/customer/config/get-zone-id',
            'api/version'
        ];
        
        $isTracked = false;
        foreach ($trackedEndpoints as $endpoint) {
            if (str_starts_with($path, $endpoint)) {
                $isTracked = true;
                break;
            }
        }
        
        if ($isTracked) {
            $userId = $request->header('Authorization') ? substr(md5($request->header('Authorization')), 0, 8) : 'anonymous';
            $cacheKey = "request_log_{$userId}_{$path}";
            
            // Get request history from cache (last 60 seconds)
            $history = Cache::get($cacheKey, []);
            $now = now();
            
            // Clean old entries
            $history = array_filter($history, function($timestamp) use ($now) {
                return $now->diffInSeconds($timestamp) < 60;
            });
            
            // Add current request
            $history[] = $now;
            
            // Count requests in last minute
            $count = count($history);
            
            // Log if more than 5 requests in a minute
            if ($count > 5) {
                Log::warning('Repeated API requests detected', [
                    'endpoint' => $path,
                    'user_hash' => $userId,
                    'count_last_minute' => $count,
                    'query' => $request->query(),
                    'timestamp' => $now->toDateTimeString()
                ]);
            }
            
            // Store updated history
            Cache::put($cacheKey, $history, 60);
        }
        
        return $next($request);
    }
}
