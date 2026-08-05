<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class DeployCodeOperation
{
    public static function handle(SshService $sshService, string $gitBranch, bool $dryRun = false, ?Console $console = null): DeploymentResultRecord
    {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line("   git fetch origin {$gitBranch}");
                $console->line("   git reset --hard origin/{$gitBranch}");
                $console->line();
                $console->success('✅ Dry run completed successfully!');
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dry run completed',
                'commands_executed' => ['git fetch', 'git reset'],
            ]);
        }

        $fetchResult = $sshService->gitFetch('origin', $gitBranch);
        $commandsExecuted[] = "git fetch origin {$gitBranch}";

        if (! $fetchResult->success) {
            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Git fetch failed',
                'error' => $fetchResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        $resetTarget = "origin/{$gitBranch}";
        $resetResult = $sshService->gitReset($resetTarget, true);
        $commandsExecuted[] = "git reset --hard {$resetTarget}";

        if (! $resetResult->success) {
            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Git reset failed',
                'error' => $resetResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Deployment completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
