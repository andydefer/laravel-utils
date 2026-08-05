<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Providers;

use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Services\GitCommandExecutor;
use AndyDefer\LaravelUtils\Services\GitService;
use AndyDefer\LaravelUtils\Services\ShellCommandExecutor;
use AndyDefer\LaravelUtils\Services\SshService;
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

        $this->app->singleton(ShellCommandExecutor::class, function () {
            return new ShellCommandExecutor;
        });

        $this->app->singleton(GitCommandExecutor::class, function () {
            return new GitCommandExecutor;
        });

        $this->app->singleton(SshService::class, function ($app) {
            return new SshService(
                $app->make(ShellCommandExecutor::class)
            );
        });

        $this->app->singleton(GitService::class, function ($app) {
            return new GitService(
                $app->make(GitCommandExecutor::class)
            );
        });
    }
}
