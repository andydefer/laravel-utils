<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Providers;

use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Illuminate\Support\ServiceProvider;

final class UtilsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/utils.php' => config_path('utils.php'),
        ], 'utils-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/utils.php',
            'utils'
        );

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });
    }
}
