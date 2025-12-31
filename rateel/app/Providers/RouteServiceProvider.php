<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';


    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });

    }

    /**
     * Configure the rate limiters for the application.
     *
     * Issue #18 FIX: Add rate limiting to critical endpoints to prevent abuse
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // Default API rate limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        // Issue #18 FIX: Trip creation - prevent spamming ride requests
        RateLimiter::for('trip-creation', function (Request $request) {
            return [
                Limit::perMinute(10)->by($request->user()?->id ?: $request->ip())
                    ->response(function () {
                        return response()->json([
                            'message' => 'Too many trip requests. Please wait before creating another.',
                            'response_code' => 'TOO_MANY_REQUESTS_429'
                        ], 429);
                    }),
                Limit::perMinute(100)->by($request->ip()),
            ];
        });

        // Issue #18 FIX: Bidding system - prevent bid spamming
        RateLimiter::for('bidding', function (Request $request) {
            return [
                Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()),
                Limit::perHour(500)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        // Issue #18 FIX: Location updates - prevent excessive updates
        RateLimiter::for('location-update', function (Request $request) {
            return Limit::perSecond(2)->by($request->user()?->id ?: $request->ip());
        });

        // Issue #18 FIX: Trip acceptance - prevent race condition abuse
        RateLimiter::for('trip-acceptance', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Issue #18 FIX: Fare estimation - prevent route API abuse
        RateLimiter::for('fare-estimate', function (Request $request) {
            return [
                Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()),
                Limit::perHour(200)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        // Issue #18 FIX: OTP requests - prevent SMS abuse
        RateLimiter::for('otp-request', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->user()?->id ?: $request->ip()),
                Limit::perHour(10)->by($request->user()?->id ?: $request->ip()),
            ];
        });

        // Issue #18 FIX: Authentication - prevent brute force
        RateLimiter::for('auth', function (Request $request) {
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perHour(30)->by($request->ip()),
            ];
        });
    }
}
