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

    private array $originalBeforeCommands;

    private array $originalAfterCommands;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDeploymentConfig = config('utils.deployment', []);
        $this->originalExportAssets = config('utils.export_assets', []);
        $this->originalPipelines = config('utils.pipelines', []);
        $this->originalBeforeCommands = config('utils.before_commands', []);
        $this->originalAfterCommands = config('utils.after_commands', []);

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
        Config::set('utils.before_commands', []);
        Config::set('utils.after_commands', []);

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
        Config::set('utils.before_commands', $this->originalBeforeCommands);
        Config::set('utils.after_commands', $this->originalAfterCommands);

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

    // ============================================================
    // TESTS POUR BEFORE COMMANDS
    // ============================================================

    public function test_deploy_executes_before_commands_from_config(): void
    {
        Config::set('utils.before_commands', [
            'echo "Starting deployment..."',
            'mkdir -p storage/backups',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: echo "Starting deployment..."', $response->output);
        $this->assertStringContainsString('Would execute: mkdir -p storage/backups', $response->output);
        $this->assertStringContainsString('Executing 2 before-command(s) on remote server', $response->output);
        $this->assertStringContainsString('Before-command 1/2', $response->output);
        $this->assertStringContainsString('Before-command 2/2', $response->output);
        $this->assertStringContainsString('All before-commands executed successfully', $response->output);
    }

    public function test_deploy_executes_before_commands_before_code_deployment(): void
    {
        Config::set('utils.before_commands', [
            'echo "Before deployment"',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = $response->output;
        $beforePosition = strpos($output, 'Would execute: echo "Before deployment"');
        $deployPosition = strpos($output, 'git fetch origin main');

        $this->assertNotFalse($beforePosition);
        $this->assertNotFalse($deployPosition);
        $this->assertLessThan($deployPosition, $beforePosition, 'Before commands should execute before code deployment');
    }

    public function test_deploy_skips_before_commands_when_not_configured(): void
    {
        Config::set('utils.before_commands', []);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertStringContainsString('No before-commands configured to execute', $output);
    }

    public function test_deploy_skips_before_commands_with_skip_before_flag(): void
    {
        Config::set('utils.before_commands', [
            'echo "Starting deployment..."',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run --skip-before');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⏭️  Skipping before-commands (--skip-before enabled)', $response->output);
        $this->assertStringNotContainsString('Would execute: echo "Starting deployment..."', $response->output);
    }

    public function test_deploy_before_commands_with_complex_commands(): void
    {
        Config::set('utils.before_commands', [
            'cp .env .env.backup',
            'echo "Backup created at $(date)"',
            './pre-deploy-check.sh --force',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: cp .env .env.backup', $response->output);
        $this->assertStringContainsString('Would execute: echo "Backup created at $(date)"', $response->output);
        $this->assertStringContainsString('Would execute: ./pre-deploy-check.sh --force', $response->output);
        $this->assertStringContainsString('All before-commands executed successfully', $response->output);
    }

    public function test_deploy_summary_shows_before_commands_count(): void
    {
        Config::set('utils.before_commands', [
            'echo "Before command 1"',
            'echo "Before command 2"',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    // ============================================================
    // TESTS POUR AFTER COMMANDS
    // ============================================================

    public function test_deploy_executes_after_commands_from_config(): void
    {
        Config::set('utils.after_commands', [
            'npm run build',
            'php artisan storage:link',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: npm run build', $response->output);
        $this->assertStringContainsString('Would execute: php artisan storage:link', $response->output);
        $this->assertStringContainsString('Executing 2 after-command(s) on remote server', $response->output);
        $this->assertStringContainsString('After-command 1/2', $response->output);
        $this->assertStringContainsString('After-command 2/2', $response->output);
        $this->assertStringContainsString('All after-commands executed successfully', $response->output);
    }

    public function test_deploy_executes_after_commands_after_pipelines(): void
    {
        Config::set('utils.after_commands', [
            'echo "After deployment"',
        ]);

        Config::set('utils.pipelines', [
            'ping',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = $response->output;
        $pipelinesPosition = strpos($output, 'Would execute: ping');
        $afterPosition = strpos($output, 'Would execute: echo "After deployment"');

        $this->assertNotFalse($pipelinesPosition);
        $this->assertNotFalse($afterPosition);
        $this->assertLessThan($afterPosition, $pipelinesPosition, 'After commands should execute after pipelines');
    }

    public function test_deploy_skips_after_commands_when_not_configured(): void
    {
        Config::set('utils.after_commands', []);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertStringContainsString('No after-commands configured to execute', $output);
    }

    public function test_deploy_skips_after_commands_with_skip_after_flag(): void
    {
        Config::set('utils.after_commands', [
            'npm run build',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run --skip-after');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⏭️  Skipping after-commands (--skip-after enabled)', $response->output);
        $this->assertStringNotContainsString('Would execute: npm run build', $response->output);
    }

    public function test_deploy_after_commands_with_complex_commands(): void
    {
        Config::set('utils.after_commands', [
            'npm run build && php artisan storage:link',
            'chmod -R 775 storage bootstrap/cache',
            './post-deploy.sh --force',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: npm run build && php artisan storage:link', $response->output);
        $this->assertStringContainsString('Would execute: chmod -R 775 storage bootstrap/cache', $response->output);
        $this->assertStringContainsString('Would execute: ./post-deploy.sh --force', $response->output);
        $this->assertStringContainsString('All after-commands executed successfully', $response->output);
    }

    public function test_deploy_summary_shows_after_commands_count(): void
    {
        Config::set('utils.after_commands', [
            'echo "After command 1"',
            'echo "After command 2"',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    // ============================================================
    // TESTS POUR BEFORE + AFTER COMMANDS COMBINÉS
    // ============================================================

    public function test_deploy_executes_both_before_and_after_commands(): void
    {
        Config::set('utils.before_commands', [
            'echo "Before"',
        ]);

        Config::set('utils.after_commands', [
            'echo "After"',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Would execute: echo "Before"', $response->output);
        $this->assertStringContainsString('Would execute: echo "After"', $response->output);

        $output = $response->output;
        $beforePosition = strpos($output, 'echo "Before"');
        $afterPosition = strpos($output, 'echo "After"');

        $this->assertLessThan($afterPosition, $beforePosition, 'Before commands should execute before after commands');
    }

    public function test_deploy_skips_both_before_and_after_with_flags(): void
    {
        Config::set('utils.before_commands', [
            'echo "Before"',
        ]);

        Config::set('utils.after_commands', [
            'echo "After"',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run --skip-before --skip-after');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⏭️  Skipping before-commands (--skip-before enabled)', $response->output);
        $this->assertStringContainsString('⏭️  Skipping after-commands (--skip-after enabled)', $response->output);
        $this->assertStringNotContainsString('Would execute: echo "Before"', $response->output);
        $this->assertStringNotContainsString('Would execute: echo "After"', $response->output);
    }

    public function test_deploy_summary_shows_both_before_and_after_commands_count(): void
    {
        Config::set('utils.before_commands', [
            'echo "Before"',
        ]);

        Config::set('utils.after_commands', [
            'echo "After 1"',
            'echo "After 2"',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertMatchesRegularExpression('/Commands\s*:\s*\d+/', $output);
    }

    // ============================================================
    // TESTS POUR LES PIPELINES (UNIQUEMENT STRINGS)
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

    public function test_deploy_skips_pipelines_when_not_configured(): void
    {
        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);
        $this->assertStringContainsString('No pipelines configured to execute', $output);
    }

    public function test_deploy_ignores_pipeline_with_fqcn_array_format(): void
    {
        Config::set('utils.pipelines', [
            [PingDirective::class, ['1']],
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $response = $this->service->run('o2switch:deploy --force --dry-run');

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⚠️ Ignored pipeline (array format not supported)', $response->output);
        $this->assertStringContainsString('Only string signatures are supported', $response->output);
        $this->assertStringNotContainsString('Would execute: AndyDefer\LaravelUtils\Tests\Fixtures\Directives\PingDirective', $response->output);
    }

    public function test_deploy_ignores_mixed_pipelines_and_executes_strings(): void
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
        $this->assertStringContainsString('Ignored pipeline (array format not supported)', $response->output);
        $this->assertStringContainsString('1 pipeline(s) were ignored because they use array format', $response->output);
        $this->assertStringNotContainsString('Would execute: AndyDefer\LaravelUtils\Tests\Fixtures\Directives\PingDirective', $response->output);
    }

    // ============================================================
    // TESTS POUR ASSETS EXPORT
    // ============================================================

    public function test_deploy_includes_assets_export_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('rsync -avz assets to', $response->output);
        $this->assertStringContainsString('📝 Will skip existing files (tracker enabled)', $response->output);
    }

    public function test_deploy_assets_with_force_export_flag_in_dry_run(): void
    {
        $command = 'o2switch:deploy --dry-run --force-export';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🧹 Force export: will overwrite existing files', $response->output);
    }

    public function test_deploy_skips_assets_export_with_skip_export_flag(): void
    {
        $command = 'o2switch:deploy --dry-run --skip-export';

        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⏭️  Skipping assets export (--skip-export enabled)', $response->output);
        $this->assertStringNotContainsString('rsync -avz assets to', $response->output);
    }

    // ============================================================
    // TESTS DE LA COMMANDE COMPLÈTE
    // ============================================================

    public function test_deploy_full_dry_run_flow(): void
    {
        Config::set('utils.before_commands', [
            'echo "Before"',
        ]);

        Config::set('utils.after_commands', [
            'echo "After"',
        ]);

        Config::set('utils.pipelines', [
            'ping',
        ]);

        $this->app->singleton(UtilsConfigInterface::class, function ($app) {
            return new UtilsConfig($app['config']);
        });

        $command = 'o2switch:deploy --force --verbose --dry-run';
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);

        $output = strip_ansi($response->output);

        $this->assertStringContainsString('🚀 O2SWITCH DEPLOYMENT', $output);
        $this->assertStringContainsString('📋 Deployment Configuration:', $output);
        $this->assertStringContainsString('Would execute: echo "Before"', $output);
        $this->assertStringContainsString('git fetch origin main', $output);
        $this->assertStringContainsString('composer install --dry-run', $output);
        $this->assertStringContainsString('npm install (if manifest missing or outdated)', $output);
        $this->assertStringContainsString('php artisan storage:link', $output);
        $this->assertStringContainsString('php artisan config:cache', $output);
        $this->assertStringContainsString('Would execute: ping', $output);
        $this->assertStringContainsString('Would execute: echo "After"', $output);
        $this->assertStringContainsString('✅ Dry run completed successfully!', $output);
        $this->assertStringContainsString('📊 Summary:', $output);
        $this->assertStringContainsString('🎉 Deployment completed successfully!', $output);
    }
}
