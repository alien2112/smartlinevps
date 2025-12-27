<?php

namespace Modules\UserManagement\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Factory;
use Modules\UserManagement\Entities\Role;
use Modules\UserManagement\Observers\RoleObserver;

class UserManagementServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'UserManagement';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'usermanagement';

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
        Role::observe(RoleObserver::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // Register Verification repositories
        $this->app->bind(
            \Modules\UserManagement\Repository\VerificationSessionRepositoryInterface::class,
            \Modules\UserManagement\Repository\Eloquent\VerificationSessionRepository::class
        );
        $this->app->bind(
            \Modules\UserManagement\Repository\VerificationMediaRepositoryInterface::class,
            \Modules\UserManagement\Repository\Eloquent\VerificationMediaRepository::class
        );

        // Register Verification services
        $this->app->bind(
            \Modules\UserManagement\Service\Interface\VerificationSessionServiceInterface::class,
            \Modules\UserManagement\Service\VerificationSessionService::class
        );
        $this->app->bind(
            \Modules\UserManagement\Service\Interface\FastApiClientServiceInterface::class,
            \Modules\UserManagement\Service\FastApiClientService::class
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

        // Register verification config
        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/verification.php'), 'verification'
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
