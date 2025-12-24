<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    public function handle(Request $request, Closure $next, int $ttl = 60)
    {
        if (!$this->shouldCache($request)) {
            return $next($request);
        }

        $cacheKey = $this->buildCacheKey($request);

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $response = $next($request);

        if ($this->isCacheableResponse($response)) {
            Cache::put($cacheKey, $response, (int) $ttl);
        }

        return $response;
    }

    private function shouldCache(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        $noCacheHeaders = [
            strtolower($request->headers->get('cache-control', '')),
            strtolower($request->headers->get('pragma', '')),
        ];

        return !in_array('no-cache', $noCacheHeaders, true) && !in_array('no-store', $noCacheHeaders, true);
    }

    private function buildCacheKey(Request $request): string
    {
        $userKey = $request->user()?->getAuthIdentifier() ?? 'guest';

        $varyHeaders = [
            'zone' => $request->header('zoneId') ?? $request->header('zoneid') ?? 'none',
            'accept_language' => $request->header('Accept-Language') ?? app()->getLocale(),
        ];

        $fingerprint = $request->fullUrl() . '|' . $userKey . '|' . json_encode($varyHeaders);

        return 'response_cache:' . sha1($fingerprint);
    }

    private function isCacheableResponse(Response $response): bool
    {
        if (method_exists($response, 'isSuccessful')) {
            return $response->isSuccessful();
        }

        $status = $response->getStatusCode();

        return $status >= 200 && $status < 300;
    }
}
