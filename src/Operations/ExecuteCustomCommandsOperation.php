<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class ExecuteCustomCommandsOperation
{
    /**
     * Execute custom commands on the remote server.
     *
     * @param  SshService  $sshService  The SSH service instance
     * @param  string  $remotePath  The remote path where commands will be executed
     * @param  UtilsConfigInterface  $config  The configuration instance
     * @param  bool  $dryRun  Whether to simulate the operation
     * @param  Console|null  $console  The console instance for output
     * @return DeploymentResultRecord The result of the operation
     */
    public static function handle(
        SshService $sshService,
        string $remotePath,
        UtilsConfigInterface $config,
        bool $dryRun = false,
        ?Console $console = null
    ): DeploymentResultRecord {
        $commands = $config->getCustomCommands();
        $commandsExecuted = [];

        if (empty($commands)) {
            if ($console) {
                $console->logInfo('📋 No custom commands configured to execute');
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'No custom commands to execute',
                'commands_executed' => [],
            ]);
        }

        if ($console) {
            $console->logInfo('🔧 Executing '.count($commands).' custom command(s) on remote server...');
            $console->line();
        }

        $globalSuccess = true;

        foreach ($commands as $index => $command) {
            $commandNumber = $index + 1;

            if ($console) {
                $console->logInfo("📦 Command {$commandNumber}/".count($commands));
                $console->logInfo("   📝 Executing: {$command}");
            }

            if ($dryRun) {
                if ($console) {
                    $console->logInfo("   🔍 DRY RUN: Would execute: {$command}");
                }
                $commandsExecuted[] = "custom: {$command} (dry-run)";

                if ($console) {
                    $console->line();
                }

                continue;
            }

            // Build the full command with cd to remote path
            $remoteCommand = "cd {$remotePath} && {$command}";
            $commandsExecuted[] = "custom: {$command}";

            if ($console) {
                $console->logInfo("   📤 Command: {$remoteCommand}");
            }

            // Execute the command
            $result = $sshService->execute($remoteCommand, false);

            if ($console && ! empty($result->output)) {
                $console->line('   📤 Output:');
                $console->line($result->output);
            }

            if (! $result->success) {
                $globalSuccess = false;
                if ($console) {
                    $console->logError("   ❌ Command failed: {$command}");
                    $console->logError('   ❌ Error: '.($result->error ?? 'Unknown error'));
                }
                break;
            }

            if ($console) {
                $console->logSuccess('   ✅ Command completed successfully');
                $console->line();
            }
        }

        if ($console) {
            if ($globalSuccess) {
                $console->logSuccess('✅ All custom commands executed successfully on remote server');
            } else {
                $console->logError('❌ Some custom commands failed on remote server');
            }
            $console->line();
        }

        return DeploymentResultRecord::from([
            'success' => $globalSuccess,
            'message' => $globalSuccess ? 'All custom commands executed successfully' : 'Some custom commands failed',
            'commands_executed' => StringTypedCollection::from($commandsExecuted),
        ]);
    }
}
