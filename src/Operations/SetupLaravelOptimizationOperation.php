<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class SetupLaravelOptimizationOperation
{
    public static function handle(SshService $sshService, string $remotePath, bool $dryRun = false, ?Console $console = null): DeploymentResultRecord
    {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line('   php artisan cache:clear');
                $console->line('   php artisan config:clear');
                $console->line('   php artisan route:clear');
                $console->line('   php artisan view:clear');
                $console->line('   php artisan config:cache');
                $console->line('   php artisan route:cache');
                $console->line('   php artisan view:cache');
                $console->line('   rm -rf storage/framework/cache/*');
                $console->line('   rm -rf storage/framework/views/*');
                $console->line('   rm -rf bootstrap/cache/*.php');
                $console->line('   composer dump-autoload');
                $console->line('   php artisan migrate --force');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Laravel optimization dry run completed',
                'commands_executed' => [
                    'php artisan cache:clear',
                    'php artisan config:clear',
                    'php artisan route:clear',
                    'php artisan view:clear',
                    'php artisan config:cache',
                    'php artisan route:cache',
                    'php artisan view:cache',
                    'rm -rf storage/framework/cache/*',
                    'rm -rf storage/framework/views/*',
                    'rm -rf bootstrap/cache/*.php',
                    'composer dump-autoload',
                    'php artisan migrate --force',
                ],
            ]);
        }

        // Étape 1: Vider les caches
        if ($console) {
            $console->info('🧹 Clearing Laravel caches...');
        }

        $clearCommands = [
            'php artisan cache:clear',
            'php artisan config:clear',
            'php artisan route:clear',
            'php artisan view:clear',
        ];

        foreach ($clearCommands as $command) {
            if ($console) {
                $console->line("   Running: {$command}");
            }

            $result = $sshService->execute("cd {$remotePath} && {$command}", false);
            $commandsExecuted[] = $command;

            if (! $result->success) {
                if ($console) {
                    $console->alertWarning("⚠️  {$command} failed, continuing...");
                }
            }
        }

        if ($console) {
            $console->success('✅ Caches cleared');
        }

        // Étape 2: Recacher les caches
        if ($console) {
            $console->info('📦 Rebuilding Laravel caches...');
        }

        $cacheCommands = [
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
        ];

        foreach ($cacheCommands as $command) {
            if ($console) {
                $console->line("   Running: {$command}");
            }

            $result = $sshService->execute("cd {$remotePath} && {$command}", false);
            $commandsExecuted[] = $command;

            if (! $result->success) {
                if ($console) {
                    $console->alertWarning("⚠️  {$command} failed, continuing...");
                }
            }
        }

        if ($console) {
            $console->success('✅ Caches rebuilt');
        }

        // Étape 3: Nettoyer les fichiers temporaires
        if ($console) {
            $console->info('🧹 Cleaning temporary files...');
        }

        $cleanCommands = [
            'rm -rf storage/framework/cache/*',
            'rm -rf storage/framework/views/*',
            'rm -rf bootstrap/cache/*.php',
        ];

        foreach ($cleanCommands as $command) {
            if ($console) {
                $console->line("   Running: {$command}");
            }

            $result = $sshService->execute("cd {$remotePath} && {$command}", false);
            $commandsExecuted[] = $command;

            if (! $result->success) {
                if ($console) {
                    $console->alertWarning("⚠️  {$command} failed, continuing...");
                }
            }
        }

        if ($console) {
            $console->success('✅ Temporary files cleaned');
        }

        // Étape 4: composer dump-autoload
        if ($console) {
            $console->info('📦 Dumping autoload...');
        }

        $dumpResult = $sshService->execute("cd {$remotePath} && composer dump-autoload", false);
        $commandsExecuted[] = 'composer dump-autoload';

        if (! $dumpResult->success) {
            if ($console) {
                $console->alertWarning(' composer dump-autoload failed, continuing...');
            }
        } else {
            if ($console) {
                $console->success('✅ Autoload dumped');
            }
        }

        // Étape 5: php artisan migrate --force
        if ($console) {
            $console->info('🗄️ Running migrations...');
        }

        $migrateResult = $sshService->execute("cd {$remotePath} && php artisan migrate --force", false);
        $commandsExecuted[] = 'php artisan migrate --force';

        if (! $migrateResult->success) {
            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Migration failed',
                'error' => $migrateResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        if ($console) {
            $console->success('✅ Migrations completed');
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Laravel optimization completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
