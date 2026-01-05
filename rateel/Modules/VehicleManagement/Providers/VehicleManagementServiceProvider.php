<?php

namespace Modules\VehicleManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;

class VehicleManagementServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'VehicleManagement';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'vehiclemanagement';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // Register repositories
        $this->app->bind(
            \Modules\VehicleManagement\Repository\VehicleRepositoryInterface::class,
            \Modules\VehicleManagement\Repository\Eloquent\VehicleRepository::class
        );
        $this->app->bind(
            \Modules\VehicleManagement\Repository\VehicleModelRepositoryInterface::class,
            \Modules\VehicleManagement\Repository\Eloquent\VehicleModelRepository::class
        );
        $this->app->bind(
            \Modules\VehicleManagement\Repository\VehicleBrandRepositoryInterface::class,
            \Modules\VehicleManagement\Repository\Eloquent\VehicleBrandRepository::class
        );
        $this->app->bind(
            \Modules\VehicleManagement\Repository\VehicleCategoryRepositoryInterface::class,
            \Modules\VehicleManagement\Repository\Eloquent\VehicleCategoryRepository::class
        );

        // Register services
        $this->app->bind(
            \Modules\VehicleManagement\Service\Interface\VehicleServiceInterface::class,
            \Modules\VehicleManagement\Service\VehicleService::class
        );
        $this->app->bind(
            \Modules\VehicleManagement\Service\Interface\VehicleModelServiceInterface::class,
            \Modules\VehicleManagement\Service\VehicleModelService::class
        );
        $this->app->bind(
            \Modules\VehicleManagement\Service\Interface\VehicleBrandServiceInterface::class,
            \Modules\VehicleManagement\Service\VehicleBrandService::class
        );
        $this->app->bind(
            \Modules\VehicleManagement\Service\Interface\VehicleCategoryServiceInterface::class,
            \Modules\VehicleManagement\Service\VehicleCategoryService::class
        );
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
        } else {
            $this->loadTranslationsFrom(module_path($this->moduleName, 'Resources/lang'), $this->moduleNameLower);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
