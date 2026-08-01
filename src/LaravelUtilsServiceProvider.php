<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils;

use Illuminate\Support\ServiceProvider;

final class LaravelUtilsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Nothing to boot for now
    }

    public function register(): void
    {
        // No bindings needed for static proxies
    }
}
