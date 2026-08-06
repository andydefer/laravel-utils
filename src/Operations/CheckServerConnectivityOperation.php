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

        // ✅ Copier .env.production local vers le serveur si présent
        $localEnvProduction = getcwd().'/.env.production';
        if (file_exists($localEnvProduction)) {
            if ($console) {
                $console->info('📄 .env.production found locally, syncing to server...');
            }

            $scpResult = $sshService->execute(
                "scp {$localEnvProduction} {$sshKey}:{$remotePath}/.env.production",
                false
            );

            if ($scpResult->success) {
                if ($console) {
                    $console->success('✅ .env.production synced to server');
                    $console->line();
                }
            } else {
                if ($console) {
                    $console->alertWarning('⚠️  Failed to sync .env.production to server, will use .env.example');
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
