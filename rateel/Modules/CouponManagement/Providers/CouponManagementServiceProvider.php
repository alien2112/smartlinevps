<?php

namespace Modules\CouponManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CouponManagement\Console\DeactivateExpiredOffersCommand;
use Modules\CouponManagement\Console\ExpireStaleReservationsCommand;
use Modules\CouponManagement\Service\CouponService;
use Modules\CouponManagement\Service\FcmService;
use Modules\CouponManagement\Service\OfferService;

class CouponManagementServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'CouponManagement';
    protected string $moduleNameLower = 'couponmanagement';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
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

        $this->app->singleton(OfferService::class, function ($app) {
            return new OfferService();
        });

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                ExpireStaleReservationsCommand::class,
                DeactivateExpiredOffersCommand::class,
            ]);
        }
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

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }

    public function provides(): array
    {
        return [
            CouponService::class,
            FcmService::class,
            OfferService::class,
        ];
    }
}
