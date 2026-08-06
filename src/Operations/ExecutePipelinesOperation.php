<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class ExecutePipelinesOperation
{
    public static function handle(
        SshService $sshService,
        string $remotePath,
        DirectiveKernel $kernel,
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
            $console->logInfo('🔧 Executing '.count($pipelines).' pipeline(s)...');
            $console->line();
        }

        $globalSuccess = true;

        foreach ($pipelines as $index => $pipeline) {
            $pipelineNumber = $index + 1;

            if ($console) {
                $console->logInfo("📦 Pipeline {$pipelineNumber}/".count($pipelines));
            }

            // Si c'est une string, on exécute via runSignature
            if (is_string($pipeline)) {
                if ($console) {
                    $console->logInfo("   📝 Executing: {$pipeline}");
                }

                if ($dryRun) {
                    if ($console) {
                        $console->logInfo("   🔍 DRY RUN: Would execute: {$pipeline}");
                    }
                    $commandsExecuted[] = "pipeline: {$pipeline} (dry-run)";

                    continue;
                }

                $result = $kernel->runSignature($pipeline);
                $commandsExecuted[] = "pipeline: {$pipeline}";

                if ($result !== ExitCode::SUCCESS) {
                    $globalSuccess = false;
                    if ($console) {
                        $console->logError("   ❌ Pipeline failed: {$pipeline}");
                    }
                    break;
                }

                if ($console) {
                    $console->logSuccess("   ✅ Pipeline completed: {$pipeline}");
                }
            }

            // Si c'est un tableau [fqcn, argv]
            if (is_array($pipeline) && count($pipeline) >= 1) {
                $fqcn = $pipeline[0];
                $argv = $pipeline[1] ?? [];

                if ($console) {
                    $console->logInfo("   📝 Executing: {$fqcn} with args: ".json_encode($argv));
                }

                if ($dryRun) {
                    if ($console) {
                        $console->logInfo("   🔍 DRY RUN: Would execute: {$fqcn}");
                    }
                    $commandsExecuted[] = "pipeline: {$fqcn} (dry-run)";

                    continue;
                }

                $result = $kernel->runDirective($fqcn, $argv);
                $commandsExecuted[] = "pipeline: {$fqcn}";

                if ($result !== ExitCode::SUCCESS) {
                    $globalSuccess = false;
                    if ($console) {
                        $console->logError("   ❌ Pipeline failed: {$fqcn}");
                    }
                    break;
                }

                if ($console) {
                    $console->logSuccess("   ✅ Pipeline completed: {$fqcn}");
                }
            }

            if ($console) {
                $console->line();
            }
        }

        if ($console) {
            if ($globalSuccess) {
                $console->logSuccess('✅ All pipelines executed successfully');
            } else {
                $console->logError('❌ Some pipelines failed');
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
