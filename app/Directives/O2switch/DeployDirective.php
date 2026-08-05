<?php

declare(strict_types=1);

namespace App\Directives\O2switch;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

/**
 * CLI directive for deploying to O2Switch server.
 *
 * This directive handles the deployment pipeline for O2Switch,
 * including git fetch, reset, and code synchronization.
 *
 * @example
 * // Deploy with default configuration
 * ./bin/afya o2switch:deploy
 *
 * // Deploy with dry run
 * ./bin/afya o2switch:deploy --dry-run
 *
 * // Deploy with force (skip confirmation)
 * ./bin/afya o2switch:deploy --force
 *
 * // Deploy with verbose output
 * ./bin/afya o2switch:deploy --verbose
 */
final class DeployDirective extends AbstractDirective
{
    private Console $console;

    private UtilsConfigInterface $config;

    private array $deploymentConfig;

    private SshService $sshService;

    public function getSignature(): string
    {
        return 'o2switch:deploy 
                {--force}#"Skip confirmation and force deployment"
                {--verbose}#"Show detailed output"
                {--dry-run}#"Simulate the operation without actually executing"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['o2d', 'deploy']);
    }

    public static function getName(): string
    {
        return 'Hello';
    }

    public function getDescription(): string
    {
        return 'Deploy the application to O2Switch server';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->loadConfiguration();
        $this->initializeServices();

        $this->console->title('🚀 O2SWITCH DEPLOYMENT');
        $this->console->separatorDouble();
        $this->console->line();
    }

    private function loadConfiguration(): void
    {
        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);
        $this->deploymentConfig = $this->config->getDeploymentConfig();
    }

    private function initializeServices(): void
    {
        $verbose = $this->getFlag('verbose');

        $this->sshService = $this->getApplication()->make(SshService::class);
        $this->sshService
            ->sshKey($this->deploymentConfig['ssh_key'])
            ->remotePath($this->deploymentConfig['remote_path'])
            ->timeout(300)
            ->verbose($verbose);
    }

    protected function execute(): ExitCode
    {

        $dryRun = $this->getFlag('dry-run');
        $force = $this->getFlag('force');

        $this->displayConfiguration();

        if (! $dryRun) {
            $reachable = $this->checkServerConnectivity();
            if (! $reachable) {
                return ExitCode::FAILURE;
            }
        }

        if (! $force && ! $dryRun) {
            $confirmed = $this->askConfirmation();
            if (! $confirmed) {
                return ExitCode::FAILURE;
            }
        }

        $this->contextSet('start_time', microtime(true));

        $result = $this->executeDeployment($dryRun);
        $this->displayResult($result);

        return $result->success ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function displayConfiguration(): void
    {
        $this->console->info('📋 Deployment Configuration:');

        $config = MapCollection::from([
            'SSH Key' => $this->deploymentConfig['ssh_key'],
            'Remote Path' => $this->deploymentConfig['remote_path'],
            'Git Branch' => $this->deploymentConfig['git_branch'],
        ]);

        $this->console->raw(KeyValue::renderWithValueColor($config, 'cyan'));
        $this->console->line();
    }

    private function checkServerConnectivity(): bool
    {
        $this->console->info('🔍 Checking server connectivity...');

        if (! $this->sshService->isReachable()) {
            $this->console->error('❌ Cannot reach SSH server: '.$this->deploymentConfig['ssh_key']);

            return false;
        }

        $this->console->success('✅ Server reachable');
        $this->console->line();

        $this->console->info('🔍 Checking remote path...');
        if (! $this->sshService->remotePathExists()) {
            $this->console->error('❌ Remote path not found: '.$this->deploymentConfig['remote_path']);

            return false;
        }
        $this->console->success('✅ Remote path exists');
        $this->console->line();

        return true;
    }

    private function askConfirmation(): bool
    {
        $this->console->alertWarning('⚠️  This will deploy the code to O2Switch');
        $this->console->line('   Use --force to skip confirmation');
        $this->console->line();

        return $this->console->confirm('🔄 Continue with deployment?', false);
    }

    private function executeDeployment(bool $dryRun): DeploymentResultRecord
    {
        $gitBranch = $this->deploymentConfig['git_branch'];
        $commandsExecuted = [];

        if ($dryRun) {
            $this->console->info('🔍 DRY RUN - Would execute:');
            $this->console->line("   git fetch origin {$gitBranch}");
            $this->console->line("   git reset --hard origin/{$gitBranch}");
            $this->console->line();
            $this->console->success('✅ Dry run completed successfully!');

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dry run completed',
                'commands_executed' => ['git fetch', 'git reset'],
            ]);
        }

        // Fetch
        $this->console->line('📦 Fetching latest code from repository...');
        $fetchResult = $this->sshService->gitFetch('origin', $gitBranch);
        $commandsExecuted[] = "git fetch origin {$gitBranch}";

        if (! $fetchResult->success) {
            $this->console->error('❌ Git fetch failed');
            $this->console->line();
            $this->console->error('Error: '.$fetchResult->error);

            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Git fetch failed',
                'error' => $fetchResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        if ($this->getFlag('verbose')) {
            $this->console->line('Output: '.$fetchResult->output);
        }

        $this->console->success('✅ Code fetched successfully');
        $this->console->line();

        // Reset
        $this->console->line('🔄 Resetting to origin/'.$gitBranch.'...');
        $resetTarget = "origin/{$gitBranch}";
        $resetResult = $this->sshService->gitReset($resetTarget, true);
        $commandsExecuted[] = "git reset --hard {$resetTarget}";

        if (! $resetResult->success) {
            $this->console->error('❌ Git reset failed');
            $this->console->line();
            $this->console->error('Error: '.$resetResult->error);

            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Git reset failed',
                'error' => $resetResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        if ($this->getFlag('verbose')) {
            $this->console->line('Output: '.$resetResult->output);
        }

        $this->console->success('✅ Reset completed successfully');

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Deployment completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }

    private function displayResult(DeploymentResultRecord $result): void
    {
        $duration = microtime(true) - $this->contextGet('start_time');

        $this->console->line();
        $this->console->info('📊 Summary:');

        $summary = MapCollection::from([
            'Duration' => number_format($duration, 2).'s',
            'Success' => $result->success ? '✅ Yes' : '❌ No',
            'Commands' => $result->commands_executed ? count($result->commands_executed) : 0,
        ]);

        $this->console->raw(KeyValue::renderWithValueColor($summary, 'yellow'));
        $this->console->line();

        if ($result->success) {
            $this->console->success('✨ '.$result->message);
        } else {
            $this->console->error('❌ '.$result->message);
            if ($result->error) {
                $this->console->line();
                $this->console->error('Error: '.$result->error);
            }
        }

        $this->console->render();
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->console->line();

        if ($exitCode->isSuccess()) {
            $this->console->success('🎉 Deployment completed successfully!');
        } else {
            $this->console->error('❌ Deployment failed (code: '.$exitCode->value.')');
        }

        $this->console->render();
    }
}
