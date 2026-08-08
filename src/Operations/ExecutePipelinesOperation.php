<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class ExecutePipelinesOperation
{
    public static function handle(
        SshService $sshService,
        string $remotePath,
        UtilsConfigInterface $config,
        bool $dryRun = false,
        ?Console $console = null
    ): DeploymentResultRecord {
        $commandsExecuted = [];

        $pipelines = $config->getPipelines();

        if (empty($pipelines)) {
            if ($console) {
                $console->logInfo('📋 No pipelines configured to execute');
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'No pipelines to execute',
                'commands_executed' => [],
            ]);
        }

        if ($console) {
            $console->logInfo('🔧 Executing '.count($pipelines).' pipeline(s) on remote server...');
            $console->line();
        }

        $globalSuccess = true;

        // Construire la commande qui exécute les pipelines sur le serveur distant
        $remoteCommands = [];

        foreach ($pipelines as $index => $pipeline) {
            $pipelineNumber = $index + 1;

            if ($console) {
                $console->logInfo("📦 Pipeline {$pipelineNumber}/".count($pipelines));
            }

            // Si c'est une string, on exécute via runSignature sur le remote
            if (is_string($pipeline)) {
                if ($console) {
                    $console->logInfo("   📝 Executing on remote: {$pipeline}");
                }

                if ($dryRun) {
                    if ($console) {
                        $console->logInfo("   🔍 DRY RUN: Would execute: {$pipeline}");
                    }
                    $commandsExecuted[] = "pipeline: {$pipeline} (dry-run)";

                    continue;
                }

                // Commande à exécuter sur le serveur distant via SSH
                $remoteCommand = "cd {$remotePath} && php bin/afya {$pipeline}";
                $remoteCommands[] = $remoteCommand;

                if ($console) {
                    $console->logInfo("   📤 Command: {$remoteCommand}");
                }
            }

            // Si c'est un tableau [fqcn, argv]
            if (is_array($pipeline) && count($pipeline) >= 1) {
                $fqcn = $pipeline[0];
                $argv = $pipeline[1] ?? [];

                if ($console) {
                    $console->logInfo("   📝 Executing on remote: {$fqcn} with args: ".json_encode($argv));
                }

                if ($dryRun) {
                    if ($console) {
                        $console->logInfo("   🔍 DRY RUN: Would execute: {$fqcn}");
                    }
                    $commandsExecuted[] = "pipeline: {$fqcn} (dry-run)";

                    continue;
                }

                // Construire la commande avec les arguments
                $argsString = '';
                if (! empty($argv)) {
                    $argsString = ' '.implode(' ', array_map('escapeshellarg', $argv));
                }

                // Commande à exécuter sur le serveur distant via SSH
                $remoteCommand = "cd {$remotePath} && php bin/afya {$fqcn}{$argsString}";
                $remoteCommands[] = $remoteCommand;

                if ($console) {
                    $console->logInfo("   📤 Command: {$remoteCommand}");
                }
            }

            if ($console) {
                $console->line();
            }
        }

        // Si on est en dry-run, on s'arrête là
        if ($dryRun) {
            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dry-run completed',
                'commands_executed' => StringTypedCollection::from($commandsExecuted),
            ]);
        }

        // Exécuter toutes les commandes en une seule session SSH
        if (! empty($remoteCommands)) {
            // Option 1: Exécuter chaque commande une par une
            foreach ($remoteCommands as $index => $command) {
                if ($console) {
                    $console->logInfo('   🚀 Executing pipeline '.($index + 1).'/'.count($remoteCommands));
                }

                $result = $sshService->execute($command, false);

                if ($console && ! empty($result->output)) {
                    $console->line('   📤 Output:');
                    $console->line($result->output);
                }

                if (! $result->success) {
                    $globalSuccess = false;
                    if ($console) {
                        $console->logError("   ❌ Pipeline failed: {$command}");
                        $console->logError('   ❌ Error: '.($result->error ?? 'Unknown error'));
                    }
                    break;
                }

                $commandsExecuted[] = "pipeline: {$command}";
                if ($console) {
                    $console->logSuccess('   ✅ Pipeline completed');
                }
            }
        }

        if ($console) {
            if ($globalSuccess) {
                $console->logSuccess('✅ All pipelines executed successfully on remote server');
            } else {
                $console->logError('❌ Some pipelines failed on remote server');
            }
            $console->line();
        }

        return DeploymentResultRecord::from([
            'success' => $globalSuccess,
            'message' => $globalSuccess ? 'All pipelines executed successfully' : 'Some pipelines failed',
            'commands_executed' => StringTypedCollection::from($commandsExecuted),
        ]);
    }
}
