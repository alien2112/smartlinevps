<?php

namespace App\Providers;

use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Register SettingsService as a singleton
        $this->app->singleton(SettingsService::class, function ($app) {
            return new SettingsService();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Issue #32 FIX: Validate critical environment variables on boot
        $this->validateEnvironmentVariables();

        if($this->app->environment('live')) {
            URL::forceScheme('https');
        }
        Paginator::useBootstrap();

        // Performance profiling: Log SQL queries when enabled
        // Enable in .env with: PERF_LOG_SQL=true
        // Logs all queries with timing, highlights slow queries (>50ms)
        if (env('PERF_LOG_SQL', false)) {
            $requestId = uniqid('req_');

            DB::listen(function ($query) use ($requestId) {
                $slowThreshold = 50; // milliseconds
                $isSlow = $query->time > $slowThreshold;

                $logData = [
                    'request_id' => $requestId,
                    'sql' => $query->sql,
                    'bindings_count' => count($query->bindings),
                    'time_ms' => round($query->time, 2),
                    'route' => request()->route()?->getName() ?? request()->path(),
                    'method' => request()->method(),
                    'slow' => $isSlow,
                ];

                if ($isSlow) {
                    Log::warning('[SLOW QUERY]', $logData);
                } else {
                    Log::debug('[SQL]', $logData);
                }
            });
        }

        // Register model observers for cache invalidation
        \Modules\TripManagement\Entities\TripRequest::observe(\App\Observers\TripRequestObserver::class);
        \Modules\TransactionManagement\Entities\Transaction::observe(\App\Observers\TransactionObserver::class);
        \Modules\ZoneManagement\Entities\Zone::observe(\App\Observers\ZoneObserver::class);

        // Register observer for rating achievements
        \Modules\ReviewModule\Entities\Review::observe(\App\Observers\ReviewObserver::class);

        // Register observer for negative balance limit monitoring
        \Modules\UserManagement\Entities\UserAccount::observe(\App\Observers\UserAccountObserver::class);

        // Note: EloquentSpatial::useDefaultSrid(4326) was removed because
        // this method is not available in the installed version of eloquent-spatial.
        // SRID 4326 is typically the default for geographic coordinates anyway.
    }

    /**
     * Issue #32 FIX: Validate critical environment variables
     *
     * This prevents runtime failures due to missing configuration.
     * Only runs in non-testing environments to avoid breaking tests.
     */
    private function validateEnvironmentVariables(): void
    {
        // Skip validation during testing or console commands like migrate/seed
        if ($this->app->runningInConsole() && !$this->app->runningUnitTests()) {
            // Only validate for web requests, not artisan commands
            return;
        }

        // Skip during unit tests
        if ($this->app->runningUnitTests()) {
            return;
        }

        $required = [
            'APP_KEY' => 'Application encryption key is not set',
            'DB_DATABASE' => 'Database name is not configured',
            'DB_HOST' => 'Database host is not configured',
        ];

        $warnings = [
            'REDIS_HOST' => 'Redis host not set - queue and cache may not work properly',
            'QUEUE_CONNECTION' => 'Queue connection not set - defaulting to sync (not recommended for production)',
        ];

        $missing = [];

        foreach ($required as $var => $message) {
            if (empty(env($var))) {
                $missing[] = "{$var}: {$message}";
            }
        }

        if (!empty($missing)) {
            Log::critical('Missing required environment variables', [
                'missing' => $missing,
            ]);

            // In production, don't throw - just log
            if (!$this->app->environment('live', 'production')) {
                throw new \RuntimeException(
                    "Missing required environment variables:\n" . implode("\n", $missing)
                );
            }
        }

        // Log warnings for non-critical missing vars
        foreach ($warnings as $var => $message) {
            if (empty(env($var))) {
                Log::warning("Environment warning: {$message}", ['var' => $var]);
            }
        }

        // Issue #1 FIX: Warn if queue is still sync in production
        if (in_array($this->app->environment(), ['live', 'production'])) {
            $queueDriver = env('QUEUE_CONNECTION', env('QUEUE_DRIVER', 'sync'));
            if ($queueDriver === 'sync') {
                Log::warning('CRITICAL: Queue driver is set to sync in production. This will block API responses!', [
                    'recommendation' => 'Set QUEUE_CONNECTION=redis in .env'
                ]);
            }
        }
    }
}
