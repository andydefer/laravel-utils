<?php

declare(strict_types=1);

namespace App\Directives\O2switch;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Operations\CheckServerConnectivityOperation;
use AndyDefer\LaravelUtils\Operations\DeployCodeOperation;
use AndyDefer\LaravelUtils\Operations\SetupEnvironmentOperation;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;
use AndyDefer\LaravelUtils\UI\DeploymentUI;

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

        DeploymentUI::displayConfiguration($this->console, $this->deploymentConfig);

        $reachable = CheckServerConnectivityOperation::handle(
            $this->sshService,
            $this->deploymentConfig['ssh_key'],
            $this->deploymentConfig['remote_path'],
            $dryRun
        );

        if (! $reachable) {
            return ExitCode::FAILURE;
        }

        if (! $force && ! $dryRun) {
            $confirmed = DeploymentUI::displayConfirmation($this->console);
            if (! $confirmed) {
                return ExitCode::FAILURE;
            }
        }

        $this->contextSet('start_time', microtime(true));

        $deployResult = DeployCodeOperation::handle(
            $this->sshService,
            $this->deploymentConfig['git_branch'],
            $dryRun,
            $this->console
        );

        if (! $deployResult->success) {
            $duration = microtime(true) - $this->contextGet('start_time');
            DeploymentUI::displayResult($this->console, $deployResult, $duration);

            return ExitCode::FAILURE;
        }

        $envResult = SetupEnvironmentOperation::handle(
            $this->sshService,
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        $duration = microtime(true) - $this->contextGet('start_time');

        if (! $envResult->success) {
            DeploymentUI::displayResult($this->console, $envResult, $duration);

            return ExitCode::FAILURE;
        }

        // ✅ Correction : fusionner les collections StringTypedCollection
        $mergedCommands = $deployResult->commands_executed->merge($envResult->commands_executed);

        $finalResult = DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Deployment completed successfully',
            'commands_executed' => $mergedCommands,
        ]);

        DeploymentUI::displayResult($this->console, $finalResult, $duration);

        return $finalResult->success ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        DeploymentUI::displayAfterExecute($this->console, $exitCode);
    }
}
