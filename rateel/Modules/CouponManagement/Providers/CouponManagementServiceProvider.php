<?php

namespace Modules\CouponManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CouponManagement\Service\CouponService;
use Modules\CouponManagement\Service\FcmService;

class CouponManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'CouponManagement';
    protected string $moduleNameLower = 'couponmanagement';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Register services as singletons
        $this->app->singleton(CouponService::class, function ($app) {
            return new CouponService();
        });

        $this->app->singleton(FcmService::class, function ($app) {
            return new FcmService();
        });
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    public function provides(): array
    {
        return [
            CouponService::class,
            FcmService::class,
        ];
    }
}
