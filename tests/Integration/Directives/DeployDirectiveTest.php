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

        // Arrange: Configuration de base
        $this->originalDeploymentConfig = config('utils.deployment', []);

        Config::set('utils.deployment', [
            'ssh_key' => 'test-server',
            'remote_path' => '~/sites/test-app.com',
            'git_branch' => 'main',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $kernel = $this->service->getKernel();
        $kernel->addDirective(DeployDirective::class);
    }

    protected function tearDown(): void
    {
        // Arrange: Nettoyage
        Config::set('utils.deployment', $this->originalDeploymentConfig);

        $this->service->destroy();
        parent::tearDown();
    }

    public function test_deploy_alias_works(): void
    {
        // Arrange
        $command = 'deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $response->output);
    }

    public function test_o2d_alias_works(): void
    {
        // Arrange
        $command = 'o2d --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $response->output);
    }

    public function test_deploy_displays_configuration(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Deployment Configuration:', $response->output);
        $this->assertStringContainsString('test-server', $response->output);
        $this->assertStringContainsString('~/sites/test-app.com', $response->output);
        $this->assertStringContainsString('main', $response->output);
    }

    public function test_deploy_dry_run_mode(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('git fetch origin main', $response->output);
        $this->assertStringContainsString('git reset --hard origin/main', $response->output);
        $this->assertStringContainsString('composer install --dry-run', $response->output);
        $this->assertStringContainsString('composer install', $response->output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $response->output);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
        $this->assertStringContainsString('php artisan cache:clear', $response->output);
        $this->assertStringContainsString('php artisan config:clear', $response->output);
        $this->assertStringContainsString('php artisan route:clear', $response->output);
        $this->assertStringContainsString('php artisan view:clear', $response->output);
        $this->assertStringContainsString('php artisan config:cache', $response->output);
        $this->assertStringContainsString('php artisan route:cache', $response->output);
        $this->assertStringContainsString('php artisan view:cache', $response->output);
        $this->assertStringContainsString('composer dump-autoload', $response->output);
        $this->assertStringContainsString('php artisan migrate --force', $response->output);
        $this->assertStringContainsString('test -f', $response->output);
        $this->assertStringContainsString('cp', $response->output);
        $this->assertStringContainsString('.env.example', $response->output);
        $this->assertStringContainsString('php artisan key:generate', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_force_flag_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --force --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_verbose_flag_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --verbose --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_all_flags_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --force --verbose --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_displays_summary_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('Summary:', $output);
        $this->assertMatchesRegularExpression('/Success\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    public function test_deploy_success_message_after_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎉 Deployment completed successfully!', $response->output);
    }

    public function test_deploy_uses_configured_settings(): void
    {
        // Arrange
        Config::set('utils.deployment', [
            'ssh_key' => 'production-server',
            'remote_path' => '~/sites/prod-app.com',
            'git_branch' => 'master',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

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
        $this->assertStringContainsString('composer install --dry-run', $response->output);
        $this->assertStringContainsString('composer install', $response->output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $response->output);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
        $this->assertStringContainsString('php artisan cache:clear', $response->output);
        $this->assertStringContainsString('php artisan config:clear', $response->output);
        $this->assertStringContainsString('php artisan route:clear', $response->output);
        $this->assertStringContainsString('php artisan view:clear', $response->output);
        $this->assertStringContainsString('php artisan config:cache', $response->output);
        $this->assertStringContainsString('php artisan route:cache', $response->output);
        $this->assertStringContainsString('php artisan view:cache', $response->output);
        $this->assertStringContainsString('composer dump-autoload', $response->output);
        $this->assertStringContainsString('php artisan migrate --force', $response->output);
        $this->assertStringContainsString('test -f', $response->output);
        $this->assertStringContainsString('.env.example', $response->output);
        $this->assertStringContainsString('php artisan key:generate', $response->output);
    }

    public function test_deploy_skips_connectivity_checks_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringNotContainsString('🔍 Checking server connectivity...', $response->output);
        $this->assertStringNotContainsString('🔍 Checking remote path...', $response->output);
    }

    public function test_deploy_full_dry_run_flow(): void
    {
        // Arrange
        $command = 'o2switch:deploy --force --verbose --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $output);
        $this->assertStringContainsString('📋 Deployment Configuration:', $output);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $output);
        $this->assertStringContainsString('git fetch origin main', $output);
        $this->assertStringContainsString('git reset --hard origin/main', $output);
        $this->assertStringContainsString('composer install --dry-run', $output);
        $this->assertStringContainsString('composer install', $output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $output);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $output);
        $this->assertStringContainsString('php artisan storage:link', $output);
        $this->assertStringContainsString('php artisan cache:clear', $output);
        $this->assertStringContainsString('php artisan config:clear', $output);
        $this->assertStringContainsString('php artisan route:clear', $output);
        $this->assertStringContainsString('php artisan view:clear', $output);
        $this->assertStringContainsString('php artisan config:cache', $output);
        $this->assertStringContainsString('php artisan route:cache', $output);
        $this->assertStringContainsString('php artisan view:cache', $output);
        $this->assertStringContainsString('composer dump-autoload', $output);
        $this->assertStringContainsString('php artisan migrate --force', $output);
        $this->assertStringContainsString('test -f', $output);
        $this->assertStringContainsString('.env.example', $output);
        $this->assertStringContainsString('php artisan key:generate', $output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $output);
        $this->assertStringContainsString('📊 Summary:', $output);

        $this->assertMatchesRegularExpression('/Duration\s*:\s*[\d.]+\s*s/', $output);
        $this->assertMatchesRegularExpression('/Success\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);

        $this->assertStringContainsString('🎉 Deployment completed successfully!', $output);
    }

    public function test_deploy_without_flags_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    // ============================================================
    // TESTS POUR SetupEnvironmentOperation
    // ============================================================

    public function test_deploy_includes_environment_setup_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('test -f', $response->output);
        $this->assertStringContainsString('.env.example', $response->output);
        $this->assertStringContainsString('php artisan key:generate', $response->output);
    }

    public function test_deploy_shows_environment_setup_messages(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('test -f', $response->output);
        $this->assertStringContainsString('.env.example', $response->output);
        $this->assertStringContainsString('php artisan key:generate', $response->output);
    }

    // ============================================================
    // TESTS POUR SetupDependenciesOperation
    // ============================================================

    public function test_deploy_includes_dependencies_setup_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('composer install --dry-run', $response->output);
        $this->assertStringContainsString('composer install', $response->output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $response->output);
    }

    public function test_deploy_shows_dependencies_setup_messages(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('composer install --dry-run', $response->output);
        $this->assertStringContainsString('rm -rf vendor composer.lock (if needed)', $response->output);
        $this->assertStringContainsString('composer install', $response->output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $response->output);
    }

    // ============================================================
    // TESTS POUR SetupFrontendAssetsOperation
    // ============================================================

    public function test_deploy_includes_frontend_assets_setup_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $response->output);
    }

    public function test_deploy_shows_frontend_assets_setup_messages(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('test -f public/build/manifest.json', $response->output);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $response->output);
    }

    public function test_deploy_dry_run_shows_frontend_check_message(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('test -f public/build/manifest.json', $response->output);
    }

    // ============================================================
    // TESTS POUR SetupStorageOperation
    // ============================================================

    public function test_deploy_includes_storage_setup_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
        $this->assertStringContainsString('(Check and create storage symbolic links)', $response->output);
    }

    public function test_deploy_shows_storage_setup_messages(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
    }

    public function test_deploy_dry_run_shows_storage_check_message(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
        $this->assertStringContainsString('(Check and create storage symbolic links)', $response->output);
    }

    // ============================================================
    // TESTS POUR SetupLaravelOptimizationOperation
    // ============================================================

    public function test_deploy_includes_laravel_optimization_in_dry_run(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('php artisan cache:clear', $response->output);
        $this->assertStringContainsString('php artisan config:clear', $response->output);
        $this->assertStringContainsString('php artisan route:clear', $response->output);
        $this->assertStringContainsString('php artisan view:clear', $response->output);
        $this->assertStringContainsString('php artisan config:cache', $response->output);
        $this->assertStringContainsString('php artisan route:cache', $response->output);
        $this->assertStringContainsString('php artisan view:cache', $response->output);
        $this->assertStringContainsString('composer dump-autoload', $response->output);
        $this->assertStringContainsString('php artisan migrate --force', $response->output);
    }

    public function test_deploy_shows_laravel_optimization_messages(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('php artisan cache:clear', $response->output);
        $this->assertStringContainsString('php artisan config:cache', $response->output);
        $this->assertStringContainsString('composer dump-autoload', $response->output);
        $this->assertStringContainsString('php artisan migrate --force', $response->output);
    }

    public function test_deploy_summary_shows_correct_commands_count_with_all_operations_including_optimization(): void
    {
        // Arrange
        $command = 'o2switch:deploy --dry-run';

        // Act
        $response = $this->service->run($command);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        // Vérifier que le nombre de commandes inclut toutes les opérations
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }
}
