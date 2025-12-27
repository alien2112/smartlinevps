<?php

namespace App\Providers;

use App\Policies\MediaAccessPolicy;
use App\Services\Media\MediaUrlSigner;
use App\Services\Media\SecureMediaUploader;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register MediaUrlSigner as singleton
        $this->app->singleton(MediaUrlSigner::class, function ($app) {
            return new MediaUrlSigner();
        });

        // Register SecureMediaUploader as singleton
        $this->app->singleton(SecureMediaUploader::class, function ($app) {
            return new SecureMediaUploader();
        });

        // Register MediaAccessPolicy as singleton
        $this->app->singleton(MediaAccessPolicy::class, function ($app) {
            return new MediaAccessPolicy();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config if needed
        $this->publishes([
            __DIR__ . '/../../config/media.php' => config_path('media.php'),
        ], 'media-config');
    }
}
