<?php

namespace Kce\Kcejenga;

use Illuminate\Support\ServiceProvider;
use Kce\Kcejenga\Services\JengaPaymentService;
use Kce\Kcejenga\Services\JengaSettingsService;

class KcejengaServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register payment service as singleton
        $this->app->singleton(JengaPaymentService::class, function ($app) {
            return new JengaPaymentService();
        });

        // Register settings service as singleton
        $this->app->singleton(JengaSettingsService::class, function ($app) {
            return new JengaSettingsService();
        });

        // Register facade accessor
        $this->app->singleton('kcejenga', function ($app) {
            return $app->make(JengaPaymentService::class);
        });

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/kcejenga.php',
            'kcejenga'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../config/kcejenga.php' => config_path('kcejenga.php'),
        ], 'kcejenga-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load routes
        $this->loadRoutes();
    }

    /**
     * Load package routes
     */
    protected function loadRoutes(): void
    {
        // Load web routes
        if (file_exists(__DIR__ . '/../routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }

        // Load API routes
        if (file_exists(__DIR__ . '/../routes/api.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }
}

