<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Configs\UtilsConfig;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Tests\Fixtures\Directives\PingDirective;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use App\Directives\O2switch\DeployDirective;
use Illuminate\Support\Facades\Config;

final class DeployDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private array $originalDeploymentConfig;

    private array $originalExportAssets;

    private array $originalPipelines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDeploymentConfig = config('utils.deployment', []);
        $this->originalExportAssets = config('utils.export_assets', []);
        $this->originalPipelines = config('utils.pipelines', []);

        Config::set('utils.deployment', [
            'ssh_key' => 'test-server',
            'remote_path' => '~/sites/test-app.com',
            'git_branch' => 'main',
        ]);

        Config::set('utils.export_assets', [
            'storage/app/public/images',
            'storage/app/public/videos',
        ]);

        Config::set('utils.pipelines', []);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: [__DIR__.'/../../Fixtures/Directives']
        );
        $kernel = $this->service->getKernel();
        $kernel->addDirective(DeployDirective::class);
        $kernel->addDirective(PingDirective::class);
    }

    protected function tearDown(): void
    {
        Config::set('utils.deployment', $this->originalDeploymentConfig);
        Config::set('utils.export_assets', $this->originalExportAssets);
        Config::set('utils.pipelines', $this->originalPipelines);

        $this->service->destroy();
        parent::tearDown();
    }

    public function test_deploy_alias_works(): void
    {
        $command = 'deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $response->output);
    }

    public function test_o2d_alias_works(): void
    {
        $command = 'o2d --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $response->output);
    }

    public function test_deploy_displays_configuration(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 Deployment Configuration:', $response->output);
        $this->assertStringContainsString('test-server', $response->output);
        $this->assertStringContainsString('~/sites/test-app.com', $response->output);
        $this->assertStringContainsString('main', $response->output);
    }

    public function test_deploy_dry_run_mode(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

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
        $command = 'o2switch:deploy --force --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_verbose_flag_in_dry_run(): void
    {
        $command = 'o2switch:deploy --verbose --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_all_flags_in_dry_run(): void
    {
        $command = 'o2switch:deploy --force --verbose --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_displays_summary_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('Summary:', $output);
        $this->assertMatchesRegularExpression('/Success\s*:\s*✅\s*Yes/', $output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    public function test_deploy_success_message_after_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎉 Deployment completed successfully!', $response->output);
    }

    public function test_deploy_uses_configured_settings(): void
    {
        Config::set('utils.deployment', [
            'ssh_key' => 'production-server',
            'remote_path' => '~/sites/prod-app.com',
            'git_branch' => 'master',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --dry-run');

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
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringNotContainsString('🔍 Checking server connectivity...', $response->output);
        $this->assertStringNotContainsString('🔍 Checking remote path...', $response->output);
    }

    public function test_deploy_full_dry_run_flow(): void
    {
        $command = 'o2switch:deploy --force --verbose --dry-run';
        $response = $this->service->run($command);

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
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $response->output);
    }

    public function test_deploy_includes_environment_setup_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('test -f', $response->output);
        $this->assertStringContainsString('.env.example', $response->output);
        $this->assertStringContainsString('php artisan key:generate', $response->output);
    }

    public function test_deploy_shows_environment_setup_messages(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('test -f', $response->output);
        $this->assertStringContainsString('.env.example', $response->output);
        $this->assertStringContainsString('php artisan key:generate', $response->output);
    }

    public function test_deploy_includes_dependencies_setup_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('composer install --dry-run', $response->output);
        $this->assertStringContainsString('composer install', $response->output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $response->output);
    }

    public function test_deploy_shows_dependencies_setup_messages(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('composer install --dry-run', $response->output);
        $this->assertStringContainsString('rm -rf vendor composer.lock (if needed)', $response->output);
        $this->assertStringContainsString('composer install', $response->output);
        $this->assertStringContainsString('touch vendor/autoload.php (if autoload outdated)', $response->output);
    }

    public function test_deploy_includes_frontend_assets_setup_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $response->output);
    }

    public function test_deploy_shows_frontend_assets_setup_messages(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('test -f public/build/manifest.json', $response->output);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $response->output);
        $this->assertStringContainsString('npm run build (if manifest missing or outdated)', $response->output);
    }

    public function test_deploy_dry_run_shows_frontend_check_message(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('test -f public/build/manifest.json', $response->output);
    }

    public function test_deploy_includes_storage_setup_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
        $this->assertStringContainsString('(Check and create storage symbolic links)', $response->output);
    }

    public function test_deploy_shows_storage_setup_messages(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
    }

    public function test_deploy_dry_run_shows_storage_check_message(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('php artisan storage:link', $response->output);
        $this->assertStringContainsString('(Check and create storage symbolic links)', $response->output);
    }

    public function test_deploy_includes_laravel_optimization_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

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
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🔍 DRY RUN - Would execute:', $response->output);
        $this->assertStringContainsString('php artisan cache:clear', $response->output);
        $this->assertStringContainsString('php artisan config:cache', $response->output);
        $this->assertStringContainsString('composer dump-autoload', $response->output);
        $this->assertStringContainsString('php artisan migrate --force', $response->output);
    }

    public function test_deploy_summary_shows_correct_commands_count_with_all_operations_including_optimization(): void
    {
        $command = 'o2switch:deploy --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    // ============================================================
    // TESTS POUR ExportAssetsOperation - MIS À JOUR
    // ============================================================

    public function test_deploy_includes_assets_export_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('rsync -avz assets to', $response->output);
        // Vérifier le nouveau message de skip
        $this->assertStringContainsString('📝 Will skip existing files', $response->output);
        // Vérifier que l'ancien message n'est plus présent
        $this->assertStringNotContainsString('images:compress (would compress images)', $response->output);
    }

    public function test_deploy_assets_with_force_export_flag_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run --force-export';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🧹 Force export: will overwrite existing files', $response->output);
        $this->assertStringNotContainsString('images:compress (would compress images)', $response->output);
        $this->assertStringNotContainsString('videos:hls (would generate HLS)', $response->output);
    }

    public function test_deploy_assets_uses_config_assets_when_configured(): void
    {
        Config::set('utils.export_assets', [
            'storage/app/public/config-assets',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('rsync -avz assets to', $response->output);
        $this->assertStringContainsString('📝 Will skip existing files', $response->output);
        $this->assertStringNotContainsString('images:compress (would compress images)', $response->output);
    }

    public function test_deploy_assets_shows_export_summary_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('rsync -avz assets to', $response->output);
        $this->assertStringContainsString('📝 Will skip existing files', $response->output);
    }

    public function test_deploy_assets_with_all_flags_in_dry_run(): void
    {
        $command = 'o2switch:deploy --force --verbose --dry-run --force-export';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('rsync -avz assets to', $response->output);
        $this->assertStringContainsString('🧹 Force export: will overwrite existing files', $response->output);
        $this->assertStringNotContainsString('images:compress (would compress images)', $response->output);
        $this->assertStringNotContainsString('videos:hls (would generate HLS)', $response->output);
    }

    // ============================================================
    // TESTS POUR ExecutePipelinesOperation
    // ============================================================

    public function test_deploy_executes_pipelines_from_config(): void
    {
        Config::set('utils.pipelines', [
            'ping',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: ping', $response->output);
    }

    public function test_deploy_executes_multiple_pipelines_from_config(): void
    {
        Config::set('utils.pipelines', [
            'ping',
            'ping --delay=1',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: ping', $response->output);
        $this->assertStringContainsString('Would execute: ping --delay=1', $response->output);
    }

    public function test_deploy_executes_pipeline_with_fqcn_and_args(): void
    {
        Config::set('utils.pipelines', [
            [PingDirective::class, ['1']],
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: AndyDefer\LaravelUtils\Tests\Fixtures\Directives\PingDirective', $response->output);
    }

    public function test_deploy_executes_pipeline_with_mixed_types(): void
    {
        Config::set('utils.pipelines', [
            'ping',
            [PingDirective::class, []],
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: ping', $response->output);
        $this->assertStringContainsString('Would execute: AndyDefer\LaravelUtils\Tests\Fixtures\Directives\PingDirective', $response->output);
    }

    public function test_deploy_skips_pipelines_when_not_configured(): void
    {
        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertStringContainsString('No pipelines configured to execute', $output);
    }

    public function test_deploy_skips_pipelines_in_dry_run(): void
    {
        Config::set('utils.pipelines', [
            'ping',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('dry-run', $response->output);
    }

    public function test_deploy_summary_shows_pipeline_commands_count(): void
    {
        Config::set('utils.pipelines', [
            'ping',
            'ping --delay=1',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    public function test_deploy_pipelines_with_skip_export_flag_does_not_affect_pipelines(): void
    {
        Config::set('utils.pipelines', [
            'ping',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run --skip-export');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: ping', $response->output);
        $this->assertStringContainsString('Skipping assets export', $response->output);
    }
}
