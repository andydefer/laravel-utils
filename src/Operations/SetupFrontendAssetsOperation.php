<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;

final class SetupFrontendAssetsOperation
{
    public static function handle(SshService $sshService, string $remotePath, bool $dryRun = false, ?Console $console = null): DeploymentResultRecord
    {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line('   test -f public/build/manifest.json');
                $console->line('   npm install (if manifest missing or outdated)');
                $console->line('   npm run build (if manifest missing or outdated)');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Frontend assets dry run completed',
                'commands_executed' => ['npm install', 'npm run build'],
            ]);
        }

        // Étape 1: Vérifier si manifest.json existe
        if ($console) {
            $console->info('📦 Checking frontend assets...');
        }

        $manifestPath = "{$remotePath}/public/build/manifest.json";
        $manifestExists = $sshService->execute("test -f {$manifestPath} && echo 'EXISTS'", false);
        $commandsExecuted[] = "test -f {$manifestPath}";

        $needsBuild = false;

        // Si manifest.json n'existe pas, il faut build
        if (! str_contains($manifestExists->output, 'EXISTS')) {
            if ($console) {
                $console->alertWarning('⚠️  public/build/manifest.json not found, building assets...');
            }
            $needsBuild = true;
        } else {
            // Étape 2: Comparer les dates de package.json et manifest.json
            if ($console) {
                $console->info('📦 Checking asset freshness...');
            }

            $packageJsonPath = "{$remotePath}/package.json";
            $packageExists = $sshService->execute("test -f {$packageJsonPath} && echo 'EXISTS'", false);
            $commandsExecuted[] = "test -f {$packageJsonPath}";

            if (str_contains($packageExists->output, 'EXISTS')) {
                // Comparer les dates de modification
                $manifestMtime = $sshService->execute("stat -c %Y {$manifestPath} 2>/dev/null || echo '0'", false);
                $commandsExecuted[] = "stat -c %Y {$manifestPath}";

                $packageMtime = $sshService->execute("stat -c %Y {$packageJsonPath} 2>/dev/null || echo '0'", false);
                $commandsExecuted[] = "stat -c %Y {$packageJsonPath}";

                $manifestTime = (int) trim($manifestMtime->output);
                $packageTime = (int) trim($packageMtime->output);

                // Si package.json est plus récent que manifest.json, il faut rebuild
                if ($packageTime > $manifestTime) {
                    if ($console) {
                        $console->alertWarning('⚠️  package.json is newer than manifest.json, rebuilding assets...');
                    }
                    $needsBuild = true;
                } else {
                    if ($console) {
                        $console->success('✅ Frontend assets are up to date');
                    }
                }
            } else {
                if ($console) {
                    $console->alertWarning('⚠️  package.json not found, skipping asset build');
                }
            }
        }

        // Étape 3: Builder les assets si nécessaire
        if ($needsBuild) {
            // Installer les dépendances npm
            if ($console) {
                $console->info('📦 Installing npm dependencies...');
            }

            $npmInstallResult = $sshService->execute("cd {$remotePath} && npm install", false);
            $commandsExecuted[] = 'npm install';

            if (! $npmInstallResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'npm install failed',
                    'error' => $npmInstallResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ npm dependencies installed');
            }

            // Builder les assets
            if ($console) {
                $console->info('📦 Building frontend assets...');
            }

            $npmBuildResult = $sshService->execute("cd {$remotePath} && npm run build", false);
            $commandsExecuted[] = 'npm run build';

            if (! $npmBuildResult->success) {
                return DeploymentResultRecord::from([
                    'success' => false,
                    'message' => 'npm run build failed',
                    'error' => $npmBuildResult->error,
                    'commands_executed' => $commandsExecuted,
                ]);
            }

            if ($console) {
                $console->success('✅ Frontend assets built successfully');
            }
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Frontend assets setup completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
