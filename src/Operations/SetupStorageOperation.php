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
                $console->alertWarning('⚠️  storage directory missing, creating...');
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

        // Étape 2: Vérifier et créer les liens symboliques
        if ($console) {
            $console->info('🔗 Checking storage symbolic links...');
        }

        // Récupérer la configuration des liens depuis le fichier config/filesystems.php
        $configContent = $sshService->execute("cat {$remotePath}/config/filesystems.php 2>/dev/null || echo ''", false);
        $commandsExecuted[] = "cat {$remotePath}/config/filesystems.php";

        $linksMissing = false;

        // Vérifier le lien storage public
        $publicLinkExists = $sshService->execute("test -L {$remotePath}/public/storage && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -L {$remotePath}/public/storage";

        if (! str_contains($publicLinkExists->output, 'EXISTS')) {
            if ($console) {
                $console->alertWarning('⚠️  public/storage symbolic link is missing');
            }
            $linksMissing = true;
        } else {
            if ($console) {
                $console->success('✅ public/storage symbolic link exists');
            }
        }

        // Vérifier les autres liens configurés (extraction depuis le fichier config)
        if (str_contains($configContent->output, "'links' => [")) {
            // Extraire les liens de la config
            preg_match_all("/'([^']+)'\s*=>\s*'([^']+)'/", $configContent->output, $matches);

            for ($i = 0; $i < count($matches[0]); $i++) {
                $link = $matches[1][$i];
                $target = $matches[2][$i];

                // Ne pas vérifier le lien public/storage car il a déjà été vérifié
                if ($link === 'public/storage') {
                    continue;
                }

                $linkExists = $sshService->execute("test -L {$remotePath}/{$link} && echo 'EXISTS'", false);
                $commandsExecuted[] = "test -L {$remotePath}/{$link}";

                if (! str_contains($linkExists->output, 'EXISTS')) {
                    if ($console) {
                        $console->alertWarning("⚠️  {$link} symbolic link is missing");
                    }
                    $linksMissing = true;
                } else {
                    if ($console) {
                        $console->success("✅ {$link} symbolic link exists");
                    }
                }
            }
        }

        // Étape 3: Exécuter storage:link si des liens manquent
        if ($linksMissing) {
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
                $console->success('✅ All storage symbolic links are present');
            }
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Storage setup completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
