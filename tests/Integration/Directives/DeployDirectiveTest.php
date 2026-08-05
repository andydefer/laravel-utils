<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use App\Directives\O2switch\DeployDirective;
use Illuminate\Support\Facades\Config;

/**
 * Integration tests for the DeployDirective.
 *
 * @group integration
 * @group directives
 * @group deploy
 */
final class DeployDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private array $originalDeploymentConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Save original configuration
        $this->originalDeploymentConfig = config('utils.deployment', []);

        // Configure test deployment (fake values)
        Config::set('utils.deployment', [
            'ssh_key' => 'test-server',
            'remote_path' => '~/sites/test-app.com',
            'git_branch' => 'main',
        ]);

        // Rebind UtilsConfig with new config
        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        // Initialize test service
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $kernel = $this->service->getKernel();
        $kernel->addDirective(DeployDirective::class);
    }

    protected function tearDown(): void
    {
        // Restore original configuration
        Config::set('utils.deployment', $this->originalDeploymentConfig);

        $this->service->destroy();
        parent::tearDown();
    }

    /**
     * Tests that the alias 'deploy' works correctly.
     */
    public function test_deploy_alias_works(): void
    {
        // Act
        $response = $this->service->run('deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $response->output);
    }

    /**
     * Tests that the alias 'o2d' works correctly.
     */
    public function test_o2d_alias_works(): void
    {
        // Act
        $response = $this->service->run('o2d --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $response->output);
    }

    /**
     * Tests that the directive displays the deployment configuration.
     */
    public function test_deploy_displays_configuration(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Deployment Configuration:', $response->output);
        $this->assertStringContainsString('test-server', $response->output);
        $this->assertStringContainsString('~/sites/test-app.com', $response->output);
        $this->assertStringContainsString('main', $response->output);
    }

    /**
     * Tests that the directive handles --dry-run mode correctly.
     */
    public function test_deploy_dry_run_mode(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('git fetch origin main', $response->output);
        $this->assertStringContainsString('git reset --hard origin/main', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    /**
     * Tests that the directive handles --force flag (but in dry-run mode, it's just displayed).
     */
    public function test_deploy_force_flag_in_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    /**
     * Tests that the directive handles --verbose flag in dry-run mode.
     */
    public function test_deploy_verbose_flag_in_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --verbose --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    /**
     * Tests that the directive handles all flags together in dry-run mode.
     */
    public function test_deploy_all_flags_in_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --force --verbose --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    /**
     * Tests that the directive displays a summary after dry-run.
     */
    public function test_deploy_displays_summary_in_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('Summary:', $output);
        $this->assertMatchesRegularExpression('/Success\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*2/', $output);
    }

    /**
     * Tests that the directive displays success message after dry-run.
     */
    public function test_deploy_success_message_after_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎉 Deployment completed successfully!', $response->output);
    }

    /**
     * Tests that the directive uses the configured deployment settings.
     */
    public function test_deploy_uses_configured_settings(): void
    {
        // Arrange: Change configuration
        Config::set('utils.deployment', [
            'ssh_key' => 'production-server',
            'remote_path' => '~/sites/prod-app.com',
            'git_branch' => 'master',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        // Re-create service with new config
        $this->service->destroy();
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );
        $this->service->getKernel()->addDirective(DeployDirective::class);

        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('production-server', $response->output);
        $this->assertStringContainsString('~/sites/prod-app.com', $response->output);
        $this->assertStringContainsString('master', $response->output);
        $this->assertStringContainsString('git fetch origin master', $response->output);
        $this->assertStringContainsString('git reset --hard origin/master', $response->output);
    }

    /**
     * Tests that the directive skips server connectivity checks in dry-run mode.
     */
    public function test_deploy_skips_connectivity_checks_in_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Should NOT show connectivity checks in dry-run
        $this->assertStringNotContainsString('🔍 Checking server connectivity...', $response->output);
        $this->assertStringNotContainsString('🔍 Checking remote path...', $response->output);
    }

    /**
     * Tests that the directive handles the full dry-run flow without errors.
     */
    public function test_deploy_full_dry_run_flow(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --force --verbose --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Check the flow
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $output);
        $this->assertStringContainsString('📋 Deployment Configuration:', $output);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $output);
        $this->assertStringContainsString('📊 Summary:', $output);

        // Assertions plus souples avec regex
        $this->assertMatchesRegularExpression('/Duration\s*:\s*[\d.]+\s*s/', $output);
        $this->assertMatchesRegularExpression('/Success\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);

        $this->assertStringContainsString('🎉 Deployment completed successfully!', $output);
    }

    /**
     * Tests that the directive works without any flags (default dry-run for testing).
     */
    public function test_deploy_without_flags_in_dry_run(): void
    {
        // Act
        $response = $this->service->run('o2switch:deploy --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }
}
