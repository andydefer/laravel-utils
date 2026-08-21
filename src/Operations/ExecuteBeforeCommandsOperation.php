<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class ExecuteBeforeCommandsOperation
{
    /**
     * Execute custom commands BEFORE deployment on the remote server.
     */
    public static function handle(
        SshService $sshService,
        string $remotePath,
        UtilsConfigInterface $config,
        bool $dryRun = false,
        ?Console $console = null
    ): DeploymentResultRecord {
        $commands = $config->getBeforeCommands();
        $commandsExecuted = [];

        if (empty($commands)) {
            if ($console) {
                $console->logInfo('📋 No before-commands configured to execute');
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'No before-commands to execute',
                'commands_executed' => [],
            ]);
        }

        if ($console) {
            $console->logInfo('🔧 Executing '.count($commands).' before-command(s) on remote server...');
            $console->line();
        }

        $globalSuccess = true;

        foreach ($commands as $index => $command) {
            $commandNumber = $index + 1;

            if ($console) {
                $console->logInfo("📦 Before-command {$commandNumber}/".count($commands));
                $console->logInfo("   📝 Executing: {$command}");
            }

            if ($dryRun) {
                if ($console) {
                    $console->logInfo("   🔍 DRY RUN: Would execute: {$command}");
                }
                $commandsExecuted[] = "before: {$command} (dry-run)";

                if ($console) {
                    $console->line();
                }

                continue;
            }

            $remoteCommand = "cd {$remotePath} && {$command}";
            $commandsExecuted[] = "before: {$command}";

            if ($console) {
                $console->logInfo("   📤 Command: {$remoteCommand}");
            }

            $result = $sshService->execute($remoteCommand, false);

            if ($console && ! empty($result->output)) {
                $console->line('   📤 Output:');
                $console->line($result->output);
            }

            if (! $result->success) {
                $globalSuccess = false;
                if ($console) {
                    $console->logError("   ❌ Before-command failed: {$command}");
                    $console->logError('   ❌ Error: '.($result->error ?? 'Unknown error'));
                }
                break;
            }

            if ($console) {
                $console->logSuccess('   ✅ Before-command completed successfully');
                $console->line();
            }
        }

        if ($console) {
            if ($globalSuccess) {
                $console->logSuccess('✅ All before-commands executed successfully');
            } else {
                $console->logError('❌ Some before-commands failed');
            }
            $console->line();
        }

        return DeploymentResultRecord::from([
            'success' => $globalSuccess,
            'message' => $globalSuccess ? 'All before-commands executed successfully' : 'Some before-commands failed',
            'commands_executed' => StringTypedCollection::from($commandsExecuted),
        ]);
    }
}
