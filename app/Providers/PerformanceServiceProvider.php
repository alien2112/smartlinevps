<?php

namespace App\Providers;

use App\Services\CachedRouteService;
use App\Services\PerformanceCache;
use App\Services\SpatialQueryService;
use Illuminate\Support\ServiceProvider;

/**
 * Performance Optimization Service Provider
 * 
 * Registers high-performance services for:
 * - Spatial queries (driver location, zone lookup)
 * - Route caching
 * - Configuration caching
 */
class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register as singletons for better performance
        $this->app->singleton(SpatialQueryService::class, function ($app) {
            return new SpatialQueryService();
        });

        $this->app->singleton(CachedRouteService::class, function ($app) {
            return new CachedRouteService();
        });

        // Bind interface aliases for dependency injection
        $this->app->alias(SpatialQueryService::class, 'spatial');
        $this->app->alias(CachedRouteService::class, 'routes');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Cache warming is now handled via artisan command: php artisan cache:warmup
        // This avoids issues during config:cache and early boot scenarios
    }

    /**
     * Pre-warm frequently accessed caches
     */
    private function prewarmCaches(): void
    {
        try {
            // Pre-load common configuration values
            $commonConfigs = [
                'search_radius',
                'vat_percent',
                'trip_commission',
                'bid_on_fare',
                'idle_fee',
                'delay_fee',
                'trip_request_active_time',
            ];

            PerformanceCache::getConfigs($commonConfigs);
        } catch (\Exception $e) {
            // Silently fail - cache warming is optional
        }
    }
}
