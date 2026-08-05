<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class SetupEnvironmentOperation
{
    public static function handle(SshService $sshService, string $remotePath, bool $dryRun = false, ?Console $console = null): DeploymentResultRecord
    {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line("   test -f {$remotePath}/.env || cp {$remotePath}/.env.example {$remotePath}/.env");
                $console->line('   php artisan key:generate');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Environment setup dry run completed',
                'commands_executed' => ['check .env', 'copy .env.example', 'key:generate'],
            ]);
        }

        // Vérifier si .env existe
        $checkEnvResult = $sshService->execute("test -f {$remotePath}/.env && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -f {$remotePath}/.env";

        // Si .env n'existe pas, copier .env.example
        if (! str_contains($checkEnvResult->output, 'EXISTS')) {
            if ($console) {
                $console->info('📄 .env not found, copying from .env.example...');
            }

            $copyResult = $sshService->execute("cp {$remotePath}/.env.example {$remotePath}/.env", false);
            $commandsExecuted[] = "cp {$remotePath}/.env.example {$remotePath}/.env";

            if (! $copyResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'Failed to copy .env.example',
                    'error' => $copyResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ .env created from .env.example');
            }
        } else {
            if ($console) {
                $console->info('✅ .env already exists');
            }
        }

        // Générer la clé Artisan
        if ($console) {
            $console->info('🔑 Generating application key...');
        }

        $keyResult = $sshService->execute("cd {$remotePath} && php artisan key:generate", false);
        $commandsExecuted[] = 'php artisan key:generate';

        if (! $keyResult->success) {
            return DeploymentResultRecord::from([
                'success' => false,
                'message' => 'Failed to generate application key',
                'error' => $keyResult->error,
                'commands_executed' => $commandsExecuted,
            ]);
        }

        if ($console) {
            $console->success('✅ Application key generated');
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Environment setup completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
