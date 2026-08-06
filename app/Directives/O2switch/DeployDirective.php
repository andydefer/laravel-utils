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
use AndyDefer\LaravelUtils\Operations\ExportAssetsOperation;
use AndyDefer\LaravelUtils\Operations\SetupDependenciesOperation;
use AndyDefer\LaravelUtils\Operations\SetupEnvironmentOperation;
use AndyDefer\LaravelUtils\Operations\SetupFrontendAssetsOperation;
use AndyDefer\LaravelUtils\Operations\SetupLaravelOptimizationOperation;
use AndyDefer\LaravelUtils\Operations\SetupStorageOperation;
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
                {--dry-run}#"Simulate the operation without actually executing"
                {--no-compress}#"Skip compression of assets before export"
                {--hls}#"Generate HLS streams for videos before export"
                {--skip-export}#"Skip assets export step"';
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
        $noCompress = $this->getFlag('no-compress');
        $hls = $this->getFlag('hls');
        $skipExport = $this->getFlag('skip-export');

        // Récupérer les assets depuis la configuration
        $assets = $this->config->getExportAssets();

        DeploymentUI::displayConfiguration($this->console, $this->deploymentConfig);

        // Operation 1 & 2: Vérification de la connectivité
        $reachable = CheckServerConnectivityOperation::handle(
            $this->sshService,
            $this->deploymentConfig['ssh_key'],
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        if (! $reachable) {
            return ExitCode::FAILURE;
        }

        // Demander confirmation
        if (! $force && ! $dryRun) {
            $confirmed = DeploymentUI::displayConfirmation($this->console);
            if (! $confirmed) {
                return ExitCode::FAILURE;
            }
        }

        $this->contextSet('start_time', microtime(true));

        // Operation 3 & 4: Déploiement du code
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

        // Operation 5: Installation des dépendances
        $dependenciesResult = SetupDependenciesOperation::handle(
            $this->sshService,
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        if (! $dependenciesResult->success) {
            $duration = microtime(true) - $this->contextGet('start_time');
            DeploymentUI::displayResult($this->console, $dependenciesResult, $duration);

            return ExitCode::FAILURE;
        }

        // Operation 6: Assets frontend
        $frontendResult = SetupFrontendAssetsOperation::handle(
            $this->sshService,
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        if (! $frontendResult->success) {
            $duration = microtime(true) - $this->contextGet('start_time');
            DeploymentUI::displayResult($this->console, $frontendResult, $duration);

            return ExitCode::FAILURE;
        }

        // Operation 7: Export des assets (skip si flag présent)
        if (! $skipExport && ! empty($assets)) {
            $exportResult = ExportAssetsOperation::handle(
                $this->sshService,
                $this->deploymentConfig['remote_path'],
                $assets,
                $force,
                $noCompress,
                $hls,
                $dryRun,
                $this->console,
                $this->getKernel(),
                $this->config
            );

            if (! $exportResult->success) {
                $duration = microtime(true) - $this->contextGet('start_time');
                DeploymentUI::displayResult($this->console, $exportResult, $duration);

                return ExitCode::FAILURE;
            }
        } elseif ($skipExport && $this->console) {
            $this->console->logInfo('⏭️  Skipping assets export (--skip-export enabled)');
        }

        // Operation 8: Configuration de l'environnement
        $envResult = SetupEnvironmentOperation::handle(
            $this->sshService,
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        if (! $envResult->success) {
            $duration = microtime(true) - $this->contextGet('start_time');
            DeploymentUI::displayResult($this->console, $envResult, $duration);

            return ExitCode::FAILURE;
        }

        // Operation 9: Configuration du storage
        $storageResult = SetupStorageOperation::handle(
            $this->sshService,
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        if (! $storageResult->success) {
            $duration = microtime(true) - $this->contextGet('start_time');
            DeploymentUI::displayResult($this->console, $storageResult, $duration);

            return ExitCode::FAILURE;
        }

        // Operation 10: Optimisation Laravel et migrations
        $optimizationResult = SetupLaravelOptimizationOperation::handle(
            $this->sshService,
            $this->deploymentConfig['remote_path'],
            $dryRun,
            $this->console
        );

        $duration = microtime(true) - $this->contextGet('start_time');

        if (! $optimizationResult->success) {
            DeploymentUI::displayResult($this->console, $optimizationResult, $duration);

            return ExitCode::FAILURE;
        }

        // Fusionner toutes les commandes exécutées
        $mergedCommands = $deployResult->commands_executed
            ->merge($dependenciesResult->commands_executed)
            ->merge($frontendResult->commands_executed)
            ->merge($envResult->commands_executed)
            ->merge($storageResult->commands_executed)
            ->merge($optimizationResult->commands_executed);

        if (! $skipExport && ! empty($assets) && isset($exportResult)) {
            $mergedCommands = $mergedCommands->merge($exportResult->commands_executed);
        }

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
