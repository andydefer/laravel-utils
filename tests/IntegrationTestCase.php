<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests;

use AndyDefer\Directive\DirectiveServiceProvider;
use AndyDefer\LaravelCluster\Providers\ClusterServiceProvider;
use AndyDefer\LaravelUtils\Providers\UtilsServiceProvider;
use AndyDefer\Repository\RepositoryServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class IntegrationTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ClusterServiceProvider::class,
            RepositoryServiceProvider::class,
            DirectiveServiceProvider::class,
            UtilsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }

    protected function runMigrations(): void
    {
        $migrationPath = __DIR__.'/Fixtures/migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
    }
}
