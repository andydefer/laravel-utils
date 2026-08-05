<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class SetupDependenciesOperation
{
    public static function handle(SshService $sshService, string $remotePath, bool $dryRun = false, ?Console $console = null): DeploymentResultRecord
    {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line('   composer install --dry-run');
                $console->line('   rm -rf vendor composer.lock (if needed)');
                $console->line('   composer install');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dependencies setup dry run completed',
                'commands_executed' => ['composer install --dry-run', 'composer install'],
            ]);
        }

        // Étape 1: Vérifier si vendor et composer.lock existent
        if ($console) {
            $console->info('📦 Checking existing dependencies...');
        }

        $vendorExists = $sshService->execute("test -d {$remotePath}/vendor && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -d {$remotePath}/vendor";

        $lockExists = $sshService->execute("test -f {$remotePath}/composer.lock && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -f {$remotePath}/composer.lock";

        $needsCleanup = false;

        // Si vendor ou composer.lock manque, il faut nettoyer et réinstaller
        if (! str_contains($vendorExists->output, 'EXISTS') || ! str_contains($lockExists->output, 'EXISTS')) {
            if ($console) {
                $console->alertWarning('⚠️  vendor or composer.lock missing, will reinstall dependencies');
            }
            $needsCleanup = true;
        } else {
            // Étape 2: Vérifier si composer install --dry-run passe
            if ($console) {
                $console->info('📦 Checking composer dependencies...');
            }

            $dryRunResult = $sshService->execute("cd {$remotePath} && composer install --dry-run", false);
            $commandsExecuted[] = 'composer install --dry-run';

            // Si le dry-run échoue, on nettoie
            if (! $dryRunResult->success || str_contains($dryRunResult->error, 'not present in the lock file')) {
                if ($console) {
                    $console->alertWarning('⚠️  Composer dry-run failed, cleaning vendor and lock file...');
                }
                $needsCleanup = true;
            } else {
                if ($console) {
                    $console->success('✅ Composer dry-run passed');
                }
            }
        }

        // Étape 3: Nettoyer si nécessaire
        if ($needsCleanup) {
            if ($console) {
                $console->info('🧹 Cleaning vendor and composer.lock...');
            }

            $cleanResult = $sshService->execute("cd {$remotePath} && rm -rf vendor composer.lock", false);
            $commandsExecuted[] = 'rm -rf vendor composer.lock';

            if (! $cleanResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'Failed to clean vendor and composer.lock',
                    'error' => $cleanResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ Cleaned vendor and composer.lock');
            }
        }

        // Étape 4: Installer les dépendances
        if ($console) {
            $console->info('📦 Installing composer dependencies...');
        }

        $installResult = $sshService->execute("cd {$remotePath} && composer install --no-interaction --prefer-dist --optimize-autoloader", false);
        $commandsExecuted[] = 'composer install --no-interaction --prefer-dist --optimize-autoloader';

        if (! $installResult->success) {
            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Composer install failed',
                'error' => $installResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        if ($console) {
            $console->success('✅ Composer dependencies installed');
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Dependencies setup completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
