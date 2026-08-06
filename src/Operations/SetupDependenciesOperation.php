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
                $console->line('   touch vendor/autoload.php (if autoload outdated)');
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Dependencies setup dry run completed',
                'commands_executed' => ['composer install --dry-run', 'composer install', 'touch vendor/autoload.php'],
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

        $needsInstall = false;
        $needsCleanup = false;

        // Si vendor ou composer.lock manque, il faut nettoyer et réinstaller
        if (! str_contains($vendorExists->output, 'EXISTS') || ! str_contains($lockExists->output, 'EXISTS')) {
            if ($console) {
                $console->alertWarning('vendor or composer.lock missing, will reinstall dependencies');
            }
            $needsCleanup = true;
            $needsInstall = true;
        } else {
            // Étape 2: Vérifier si vendor/autoload.php est plus récent que composer.lock
            if ($console) {
                $console->info('📦 Checking autoload freshness...');
            }

            $autoloadExists = $sshService->execute("test -f {$remotePath}/vendor/autoload.php && echo 'EXISTS'", false);
            $commandsExecuted[] = "test -f {$remotePath}/vendor/autoload.php";

            if (str_contains($autoloadExists->output, 'EXISTS')) {
                // Comparer les dates de modification
                $autoloadMtime = $sshService->execute("stat -c %Y {$remotePath}/vendor/autoload.php 2>/dev/null || echo '0'", false);
                $commandsExecuted[] = "stat -c %Y {$remotePath}/vendor/autoload.php";

                $lockMtime = $sshService->execute("stat -c %Y {$remotePath}/composer.lock 2>/dev/null || echo '0'", false);
                $commandsExecuted[] = "stat -c %Y {$remotePath}/composer.lock";

                $autoloadTime = (int) trim($autoloadMtime->output);
                $lockTime = (int) trim($lockMtime->output);

                // Si autoload.php est plus ancien que composer.lock, il faut réinstaller
                if ($autoloadTime < $lockTime) {
                    if ($console) {
                        $console->alertWarning('vendor/autoload.php is outdated (older than composer.lock), reinstalling...');
                    }
                    $needsInstall = true;
                } else {
                    if ($console) {
                        $console->success('✅ vendor/autoload.php is up to date');
                    }
                }
            } else {
                if ($console) {
                    $console->alertWarning('vendor/autoload.php missing, reinstalling...');
                }
                $needsInstall = true;
            }

            // Étape 3: Vérifier si composer install --dry-run passe
            if (! $needsInstall) {
                if ($console) {
                    $console->info('📦 Checking composer dependencies...');
                }

                $dryRunResult = $sshService->execute("cd {$remotePath} && composer install --dry-run", false);
                $commandsExecuted[] = 'composer install --dry-run';

                // Si le dry-run échoue, on nettoie et réinstalle
                if (! $dryRunResult->success || str_contains($dryRunResult->error, 'not present in the lock file')) {
                    if ($console) {
                        $console->alertWarning('Composer dry-run failed, cleaning and reinstalling...');
                    }
                    $needsCleanup = true;
                    $needsInstall = true;
                } else {
                    if ($console) {
                        $console->success('✅ Composer dry-run passed');
                    }
                }
            }
        }

        // Étape 4: Nettoyer si nécessaire
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

        // Étape 5: Installer les dépendances si nécessaire
        if ($needsInstall) {
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

            // Étape 6: Toucher vendor/autoload.php pour mettre à jour sa date
            if ($console) {
                $console->info('🔄 Updating autoload.php timestamp...');
            }

            $touchResult = $sshService->execute("cd {$remotePath} && touch vendor/autoload.php", false);
            $commandsExecuted[] = 'touch vendor/autoload.php';

            if (! $touchResult->success) {
                if ($console) {
                    $console->alertWarning('Could not touch vendor/autoload.php, but installation completed');
                }
            } else {
                if ($console) {
                    $console->success('✅ vendor/autoload.php timestamp updated');
                }
            }
        } else {
            if ($console) {
                $console->success('✅ No installation needed, dependencies are up to date');
            }
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Dependencies setup completed successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }
}
