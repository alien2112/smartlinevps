<?php

namespace Modules\DispatchManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class DispatchManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'DispatchManagement';
    protected string $moduleNameLower = 'dispatchmanagement';

    public function boot(): void
    {
        $this->registerRoutes();
        $this->loadViewsFrom(module_path($this->moduleName, 'Resources/views'), $this->moduleNameLower);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerRoutes(): void
    {
        // Register API routes
        Route::middleware('api')
            ->prefix('api')
            ->group(module_path($this->moduleName, '/Routes/honeycomb.php'));

        // Register Web routes
        Route::middleware('web')
            ->group(module_path($this->moduleName, '/Routes/web.php'));
    }

    public function provides(): array
    {
        return [];
    }
}
