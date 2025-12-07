<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
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
        //
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
        
        // Note: EloquentSpatial::useDefaultSrid(4326) was removed because 
        // this method is not available in the installed version of eloquent-spatial.
        // SRID 4326 is typically the default for geographic coordinates anyway.
    }
}
