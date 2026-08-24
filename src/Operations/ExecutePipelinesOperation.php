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
        $remoteCommands = [];
        $ignoredPipelines = [];
        $binaryPath = $config->getBinaryPath();

        foreach ($pipelines as $index => $pipeline) {
            $pipelineNumber = $index + 1;

            if ($console) {
                $console->logInfo("📦 Pipeline {$pipelineNumber}/".count($pipelines));
            }

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

                $remoteCommand = "cd {$remotePath} && {$binaryPath} {$pipeline}";
                $remoteCommands[] = $remoteCommand;

                if ($console) {
                    $console->logInfo("   📤 Command: {$remoteCommand}");
                }
            }

            if (is_array($pipeline)) {
                $ignoredPipelines[] = $pipeline;
                if ($console) {
                    $fqcn = $pipeline[0] ?? 'unknown';
                    $console->logWarning("   ⚠️ Ignored pipeline (array format not supported): {$fqcn}");
                    $console->logWarning('   💡 Please use signature format instead, e.g.: "afya:seed --force"');
                }
            }

            if ($console) {
                $console->line();
            }
        }

        if (! empty($ignoredPipelines) && $console) {
            $console->logWarning('⚠️  '.count($ignoredPipelines).' pipeline(s) were ignored because they use array format');
            $console->logWarning('   💡 Only string signatures are supported');
            $console->line();
        }

        if ($dryRun) {
            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dry-run completed',
                'commands_executed' => StringTypedCollection::from($commandsExecuted),
            ]);
        }

        if (! empty($remoteCommands)) {
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
