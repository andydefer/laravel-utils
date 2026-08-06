<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class SetupStorageOperation
{
    public static function handle(SshService $sshService, string $remotePath, bool $dryRun = false, ?Console $console = null): DeploymentResultRecord
    {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line('   php artisan storage:link');
                $console->line('   (Check and create storage symbolic links)');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Storage setup dry run completed',
                'commands_executed' => ['php artisan storage:link'],
            ]);
        }

        // Étape 1: Vérifier si le dossier storage existe
        if ($console) {
            $console->info('📁 Checking storage directory...');
        }

        $storageExists = $sshService->execute("test -d {$remotePath}/storage && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -d {$remotePath}/storage";

        if (! str_contains($storageExists->output, 'EXISTS')) {
            if ($console) {
                $console->alertWarning('storage directory missing, creating...');
            }

            $createStorageResult = $sshService->execute("mkdir -p {$remotePath}/storage", false);
            $commandsExecuted[] = "mkdir -p {$remotePath}/storage";

            if (! $createStorageResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'Failed to create storage directory',
                    'error' => $createStorageResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ storage directory created');
            }
        } else {
            if ($console) {
                $console->success('✅ storage directory exists');
            }
        }

        // Étape 2: Vérifier le fichier config/filesystems.php local et distant
        if ($console) {
            $console->info('📄 Checking config/filesystems.php...');
        }

        $localConfigPath = getcwd().'/config/filesystems.php';
        $remoteConfigPath = "{$remotePath}/config/filesystems.php";

        $needsStorageLink = false;

        // Vérifier si le fichier config existe localement
        if (file_exists($localConfigPath)) {
            // Récupérer la date de modification locale
            $localMtime = filemtime($localConfigPath);

            // Récupérer la date de modification distante
            $remoteMtimeResult = $sshService->execute("stat -c %Y {$remoteConfigPath} 2>/dev/null || echo '0'", false);
            $commandsExecuted[] = "stat -c %Y {$remoteConfigPath}";
            $remoteMtime = (int) trim($remoteMtimeResult->output);

            // Récupérer le hash local
            $localHash = md5_file($localConfigPath);

            // Récupérer le hash distant
            $remoteHashResult = $sshService->execute("md5sum {$remoteConfigPath} 2>/dev/null | cut -d' ' -f1 || echo ''", false);
            $commandsExecuted[] = "md5sum {$remoteConfigPath}";
            $remoteHash = trim($remoteHashResult->output);

            // Comparer les dates et les hashs
            if ($localMtime > $remoteMtime || $localHash !== $remoteHash) {
                if ($console) {
                    if ($remoteMtime === 0) {
                        $console->alertWarning('config/filesystems.php not found on server, will sync...');
                    } elseif ($localMtime > $remoteMtime) {
                        $console->alertWarning('config/filesystems.php is newer locally, syncing...');
                    } else {
                        $console->alertWarning('config/filesystems.php has changed, syncing...');
                    }
                }

                // Copier le fichier config vers le serveur
                $copyConfigResult = $sshService->execute(
                    "scp {$localConfigPath} {$sshService->getSshKey()}:{$remoteConfigPath}",
                    false
                );
                $commandsExecuted[] = "scp config/filesystems.php {$remoteConfigPath}";

                if ($copyConfigResult->success) {
                    if ($console) {
                        $console->success('✅ config/filesystems.php synced to server');
                    }
                    $needsStorageLink = true;
                } else {
                    if ($console) {
                        $console->alertWarning('Failed to sync config/filesystems.php, continuing...');
                    }
                }
            } else {
                if ($console) {
                    $console->success('✅ config/filesystems.php is up to date');
                }
            }
        } else {
            if ($console) {
                $console->alertWarning('config/filesystems.php not found locally, skipping...');
            }
        }

        // Étape 3: Exécuter storage:link si nécessaire
        if ($needsStorageLink) {
            if ($console) {
                $console->info('🔗 Creating storage symbolic links...');
            }

            $storageLinkResult = $sshService->execute("cd {$remotePath} && php artisan storage:link --force", false);
            $commandsExecuted[] = 'php artisan storage:link --force';

            if (! $storageLinkResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'Failed to create storage symbolic links',
                    'error' => $storageLinkResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ Storage symbolic links created');
            }
        } else {
            if ($console) {
                $console->success('✅ No storage link update needed');
            }
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Storage setup completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
