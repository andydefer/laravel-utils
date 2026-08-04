<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use Symfony\Component\Process\Process;

/**
 * CLI directive for resetting the Git repository to a clean state.
 *
 * This directive removes all uncommitted changes and untracked files,
 * restoring the repository to the last commit state.
 *
 * ⚠️ WARNING: This operation is destructive and cannot be undone!
 *
 * @example
 * // Interactive reset (with confirmation timeout)
 * ./bin/afya ugr
 *
 * // Force reset without confirmation
 * ./bin/afya ugr --force
 *
 * // Dry-run to see what would be removed
 * ./bin/afya ugr --dry-run
 */
final class GitResetDirective extends AbstractDirective
{
    private Console $console;

    public function getSignature(): string
    {
        return 'utils:git-reset 
                {--force}#"Skip confirmation" 
                {--dry-run}#"Simulate the operation without actually executing"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ugr']);
    }

    public function getDescription(): string
    {
        return 'Reset Git repository to clean state (remove all uncommitted changes)';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->console->title('🗑️ GIT RESET - DESTRUCTIVE OPERATION');
        $this->console->separatorDouble();
        $this->console->line();
    }

    protected function execute(): ExitCode
    {
        $force = $this->getFlag('force');
        $dryRun = $this->getFlag('dry-run');

        // Vérifier si nous sommes dans un dépôt Git
        if (! $this->isGitRepository()) {
            $this->console->error('❌ Not a Git repository');

            return ExitCode::FAILURE;
        }

        // Récupérer les changements à supprimer
        $changes = $this->getChanges();

        if (empty($changes)) {
            $this->console->info('✅ No changes to reset');
            $this->console->line('📋 Repository is already clean.');

            return ExitCode::SUCCESS;
        }

        // Afficher les changements
        $this->console->info('📋 Changes to be removed:');
        $this->console->line();

        $totalFiles = 0;
        $totalSize = 0;

        foreach ($changes as $type => $files) {
            $count = count($files);
            $totalFiles += $count;
            $this->console->line("   {$type}: {$count} file(s)");

            // Afficher les 5 premiers fichiers pour chaque type
            $displayFiles = array_slice($files, 0, 5);
            foreach ($displayFiles as $file) {
                $this->console->line("     - {$file}");
            }
            if ($count > 5) {
                $this->console->line('     - ... and '.($count - 5).' more');
            }

            // Calculer la taille des fichiers modifiés
            foreach ($files as $file) {
                if (file_exists($file)) {
                    $totalSize += filesize($file);
                }
            }
        }

        $this->console->line();
        $this->console->info("📊 Total: {$totalFiles} file(s), ".$this->formatSize($totalSize));

        if ($dryRun) {
            $this->console->newLine();
            $this->console->success('✅ Dry run completed successfully!');
            $this->console->line('📋 No actual changes were made.');

            return ExitCode::SUCCESS;
        }

        // Confirmation
        if (! $force) {
            $this->console->newLine();
            $this->console->alertWarning('⚠️  WARNING: This will permanently delete all uncommitted changes!');

            $confirm = $this->console->form()
                ->title('⚠️  Confirmation required')
                ->line()
                ->confirm('🔴 Are you sure you want to proceed?', 'confirm', false)
                ->submit();

            if (! $confirm->get('confirm')) {
                $this->console->alertWarning('❌ Operation cancelled');

                return ExitCode::FAILURE;
            }
        } else {
            $this->console->newLine();
            $this->console->alertWarning('⚠️  Force mode enabled - skipping confirmation');
        }

        // Exécution du reset
        $this->console->newLine();
        $this->console->info('🔄 Resetting repository...');

        $result = $this->resetRepository();

        if ($result !== ExitCode::SUCCESS) {
            $this->console->error('❌ Reset failed');

            return $result;
        }

        $this->console->success('✅ Repository reset successfully!');
        $this->console->line('📋 All uncommitted changes have been removed.');

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->console->newLine();
        if ($exitCode === ExitCode::SUCCESS) {
            $this->console->success('✅ Operation completed successfully!');
        } else {
            $this->console->error('❌ Operation failed');
        }
        $this->console->render();
    }

    private function isGitRepository(): bool
    {
        $process = new Process(['git', 'rev-parse', '--git-dir']);
        $process->run();

        return $process->isSuccessful();
    }

    private function getChanges(): array
    {
        $changes = [
            'Modified' => [],
            'Added' => [],
            'Deleted' => [],
            'Untracked' => [],
        ];

        // Fichiers modifiés
        $process = new Process(['git', 'diff', '--name-only']);
        $process->run();
        $modified = explode("\n", trim($process->getOutput()));
        $changes['Modified'] = array_filter($modified);

        // Fichiers ajoutés (stagés)
        $process = new Process(['git', 'diff', '--cached', '--name-only']);
        $process->run();
        $added = explode("\n", trim($process->getOutput()));
        $changes['Added'] = array_filter($added);

        // Fichiers supprimés (stagés)
        $process = new Process(['git', 'diff', '--cached', '--name-only', '--diff-filter=D']);
        $process->run();
        $deleted = explode("\n", trim($process->getOutput()));
        $changes['Deleted'] = array_filter($deleted);

        // Fichiers non suivis
        $process = new Process(['git', 'ls-files', '--others', '--exclude-standard']);
        $process->run();
        $untracked = explode("\n", trim($process->getOutput()));
        $changes['Untracked'] = array_filter($untracked);

        return array_filter($changes, fn ($files) => ! empty($files));
    }

    private function resetRepository(): ExitCode
    {
        // git reset --hard
        $process = new Process(['git', 'reset', '--hard', 'HEAD']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('   ❌ git reset --hard failed: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        // git clean -fd
        $process = new Process(['git', 'clean', '-fd']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('   ❌ git clean -fd failed: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
