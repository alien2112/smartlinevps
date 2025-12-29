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

        // Note: EloquentSpatial::useDefaultSrid(4326) was removed because
        // this method is not available in the installed version of eloquent-spatial.
        // SRID 4326 is typically the default for geographic coordinates anyway.
    }
}
