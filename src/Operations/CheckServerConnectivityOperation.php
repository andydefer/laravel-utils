<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Services\SshService;

final class CheckServerConnectivityOperation
{
    public static function handle(SshService $sshService, string $sshKey, string $remotePath, bool $dryRun = false, ?Console $console = null): bool
    {
        if ($dryRun) {
            return true;
        }

        if ($console) {
            $console->info('🔍 Checking server connectivity...');
        }

        if (! $sshService->isReachable()) {
            if ($console) {
                $console->error('❌ Cannot reach SSH server: '.$sshKey);
            }

            return false;
        }

        if ($console) {
            $console->success('✅ Server reachable');
            $console->line();
            $console->info('🔍 Checking remote path...');
        }

        if (! $sshService->remotePathExists()) {
            if ($console) {
                $console->error('❌ Remote path not found: '.$remotePath);
            }

            return false;
        }

        if ($console) {
            $console->success('✅ Remote path exists');
            $console->line();
        }

        // ✅ Vérifier et copier .env.production local vers le serveur si différent
        $localEnvProduction = getcwd().'/.env.production';
        $remoteEnvProduction = "{$remotePath}/.env.production";

        if (file_exists($localEnvProduction)) {
            if ($console) {
                $console->info('📄 Checking .env.production...');
            }

            // Calculer le hash du fichier local
            $localHash = md5_file($localEnvProduction);

            // Récupérer le hash du fichier distant
            $remoteHashResult = $sshService->execute(
                "md5sum {$remoteEnvProduction} 2>/dev/null | cut -d' ' -f1 || echo ''",
                false
            );
            $remoteHash = trim($remoteHashResult->output);

            // Vérifier si le fichier distant existe et comparer les hashs
            $fileExistsResult = $sshService->execute(
                "test -f {$remoteEnvProduction} && echo 'EXISTS' || echo 'NOT_FOUND'",
                false
            );
            $fileExists = trim($fileExistsResult->output) === 'EXISTS';

            // Si le fichier n'existe pas OU les hashs sont différents
            if (! $fileExists || $localHash !== $remoteHash) {
                if ($console) {
                    if (! $fileExists) {
                        $console->info('📄 .env.production not found on server, syncing...');
                    } else {
                        $console->info('📄 .env.production has changed, syncing...');
                    }
                }

                $scpResult = $sshService->execute(
                    "scp {$localEnvProduction} {$sshKey}:{$remoteEnvProduction}",
                    false
                );

                if ($scpResult->success) {
                    if ($console) {
                        $console->success('✅ .env.production synced to server');
                        $console->line();
                    }
                } else {
                    if ($console) {
                        $console->alertWarning('Failed to sync .env.production to server, will use .env.example');
                        $console->line();
                    }
                }
            } else {
                if ($console) {
                    $console->success('✅ .env.production is up to date');
                    $console->line();
                }
            }
        } else {
            if ($console) {
                $console->info('📄 .env.production not found locally, will use .env.example');
                $console->line();
            }
        }

        return true;
    }
}
