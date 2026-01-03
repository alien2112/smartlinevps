<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware to add deprecation warnings to v1 API endpoints
 * 
 * Adds standard deprecation headers and optionally logs usage.
 */
class DeprecationWarning
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $newEndpoint  The new v2 endpoint path
     * @param  string  $sunsetDate  ISO 8601 date when v1 will be removed
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $newEndpoint, string $sunsetDate = '2026-04-01')
    {
        $response = $next($request);

        // Add deprecation headers (RFC 8594)
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Link', "<{$newEndpoint}>; rel=\"successor-version\"");
        $response->headers->set('Sunset', $sunsetDate);
        $response->headers->set('X-API-Deprecated', 'true');
        $response->headers->set('X-API-New-Endpoint', $newEndpoint);

        // Log deprecated endpoint usage if enabled
        if (config('driver_onboarding.deprecation.log_deprecated_usage', true)) {
            Log::warning('Deprecated v1 endpoint accessed', [
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'new_endpoint' => $newEndpoint,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Add warning to response body for JSON responses
        if ($response->headers->get('Content-Type') === 'application/json' || 
            str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            
            $content = json_decode($response->getContent(), true);
            if (is_array($content)) {
                $content['_deprecation'] = [
                    'warning' => translate('This endpoint is deprecated and will be removed on :date', ['date' => $sunsetDate]),
                    'new_endpoint' => $newEndpoint,
                    'migration_guide' => url('/docs/api-migration-v2'),
                ];
                $response->setContent(json_encode($content));
            }
        }

        return $response;
    }
}
