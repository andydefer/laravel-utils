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
                $console->line("   test -f .env.production (local) && rsync -av .env.production {$remotePath}/.env");
                $console->line("   test -f {$remotePath}/.env.production (remote) && rsync -av {$remotePath}/.env.production {$remotePath}/.env");
                $console->line("   test -f {$remotePath}/.env || cp {$remotePath}/.env.example {$remotePath}/.env");
                $console->line('   php artisan key:generate');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Environment setup dry run completed',
                'commands_executed' => ['rsync .env.production', 'copy .env.example', 'key:generate'],
            ]);
        }

        // Étape 1: Vérifier si .env.production existe LOCALEMENT (sur le PC)
        if ($console) {
            $console->info('📄 Checking local .env.production...');
        }

        $localEnvProductionExists = file_exists(getcwd().'/.env.production');

        // Si .env.production existe localement, le rsync vers le serveur
        if ($localEnvProductionExists) {
            if ($console) {
                $console->info('📄 .env.production found locally, syncing to server...');
            }

            // Rsync depuis local vers distant
            $localPath = getcwd().'/.env.production';
            $remoteEnvPath = "{$remotePath}/.env";

            // Utiliser rsync avec SSH
            $rsyncResult = $sshService->execute(
                "rsync -av -e ssh {$localPath} {$sshService->getSshKey()}:{$remoteEnvPath}",
                false
            );
            $commandsExecuted[] = "rsync -av .env.production {$remotePath}/.env";

            if (! $rsyncResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'Failed to rsync .env.production to server',
                    'error' => $rsyncResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ .env.production synced to server');
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

        // Étape 2: Si pas de .env.production local, vérifier sur le serveur
        if ($console) {
            $console->info('📄 .env.production not found locally, checking remote...');
        }

        $checkRemoteProduction = $sshService->execute("test -f {$remotePath}/.env.production && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -f {$remotePath}/.env.production";

        if (str_contains($checkRemoteProduction->output, 'EXISTS')) {
            if ($console) {
                $console->info('📄 .env.production found on server, copying to .env...');
            }

            $copyResult = $sshService->execute("cp {$remotePath}/.env.production {$remotePath}/.env", false);
            $commandsExecuted[] = "cp {$remotePath}/.env.production {$remotePath}/.env";

            if (! $copyResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'Failed to copy .env.production to .env',
                    'error' => $copyResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ .env created from .env.production');
            }
        } else {
            // Étape 3: Si rien n'existe, utiliser .env.example
            if ($console) {
                $console->info('📄 No .env.production found, using .env.example...');
            }

            // Vérifier si .env existe déjà
            $checkEnvResult = $sshService->execute("test -f {$remotePath}/.env && echo 'EXISTS'", false);
            $commandsExecuted[] = "test -f {$remotePath}/.env";

            if (! str_contains($checkEnvResult->output, 'EXISTS')) {
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
