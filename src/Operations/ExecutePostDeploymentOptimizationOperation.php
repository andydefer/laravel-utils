<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

/**
 * Operation to execute post-deployment optimization commands one by one.
 */
final class ExecutePostDeploymentOptimizationOperation
{
    private const COMMANDS = [
        'php artisan storage:link',
        'npm run build',
        'php artisan cache:clear',
        'php artisan config:clear',
        'php artisan route:clear',
        'php artisan view:clear',
        'php artisan config:cache',
        'php artisan route:cache',
        'php artisan view:cache',
    ];

    public static function handle(
        SshService $sshService,
        string $remotePath,
        bool $dryRun = false,
        ?Console $console = null
    ): DeploymentResultRecord {
        $commandsExecuted = [];
        $globalSuccess = true;

        if ($console) {
            $console->logInfo('⚡ Executing post-deployment optimization...');
            $console->line();
        }

        if ($dryRun) {
            if ($console) {
                $console->logInfo('🔍 DRY RUN - Would execute optimization commands:');
                foreach (self::COMMANDS as $index => $command) {
                    $console->logInfo('   📦 '.($index + 1).". {$command}");
                }
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dry run: Post-deployment optimization commands would be executed',
                'commands_executed' => StringTypedCollection::from(self::COMMANDS),
            ]);
        }

        foreach (self::COMMANDS as $index => $command) {
            $commandNumber = $index + 1;
            $fullCommand = "cd {$remotePath} && {$command}";
            $commandsExecuted[] = $command;

            if ($console) {
                $console->logInfo("   📦 Command {$commandNumber}/".count(self::COMMANDS));
                $console->logInfo("   📝 Executing: {$command}");
            }

            $result = $sshService->execute($fullCommand, false);

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
                $console->logSuccess('✅ All post-deployment optimization commands completed successfully');
            } else {
                $console->logError('❌ Post-deployment optimization failed');
            }
            $console->line();
        }

        return DeploymentResultRecord::from([
            'success' => $globalSuccess,
            'message' => $globalSuccess
                ? 'Post-deployment optimization completed successfully'
                : 'Post-deployment optimization failed',
            'commands_executed' => StringTypedCollection::from($commandsExecuted),
        ]);
    }
}
