<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Components\ProgressBar;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Services\VirtualTerminalService;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Symfony\Component\Process\Process;

/**
 * CLI directive for pushing code to remote repositories with interactive mode.
 *
 * @example
 * ./bin/afya ugp
 * ./bin/afya ugp [github] --dry-run <message="Fix bug">
 * ./bin/afya ugp [github] --force-with-lease --no-tests <message="Hotfix">
 */
final class GitPushDirective extends AbstractDirective
{
    private Console $console;

    private UtilsConfigInterface $config;

    private array $repositories;

    private bool $isVerbose = false;

    private int $totalTests = 0;

    private int $currentTest = 0;

    private string $currentTestName = '';

    private VirtualTerminalService $vt;

    private float $lastRenderTime = 0; // ✅ Changé en float

    private const RENDER_INTERVAL = 300000; // 300ms en microsecondes

    public function getSignature(): string
    {
        return 'utils:git-push 
                {sources*}#"Repository aliases to push to (empty = push to all)" 
                {folders*}#"Folders to add (empty = add all files)" 
                {--no-tests}#"Skip running tests before push" 
                {--force-with-lease}#"Use --force-with-lease instead of standard push" 
                {--force}#"Force push even if tests fail"
                {--no-interactive}#"Disable interactive mode"
                {--dry-run}#"Simulate the push without actually executing"
                {--verbose}#"Show detailed output"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ugp']);
    }

    public function getDescription(): string
    {
        return 'Push code to configured remote repositories with interactive mode';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->vt = new VirtualTerminalService($this->console->getAnsiConverter());
        $this->loadConfiguration();

        $this->console->title('🚀 GIT PUSH');
        $this->console->separatorDouble();
        $this->console->line();

        $this->isVerbose = $this->getFlag('verbose');
        $this->lastRenderTime = 0.0;
    }

    private function loadConfiguration(): void
    {
        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);
        $this->repositories = $this->config->getRepositories();
    }

    private function renderWithThrottle(): void
    {
        $now = microtime(true) * 1000000; // microsecondes
        if ($now - $this->lastRenderTime >= self::RENDER_INTERVAL) {
            $this->vt->render();
            $this->lastRenderTime = $now;
        }
    }

    protected function execute(): ExitCode
    {
        $message = $this->getCustomDataItem('message');
        $sources = $this->getVariadic('sources');
        $folders = $this->getVariadic('folders');
        $noTests = $this->getFlag('no-tests');
        $forceWithLease = $this->getFlag('force-with-lease');
        $force = $this->getFlag('force');
        $noInteractive = $this->getFlag('no-interactive');
        $dryRun = $this->getFlag('dry-run');

        if ($noInteractive) {
            if ($message === null || ! preg_match('/[a-zA-Z0-9]/', $message)) {
                $this->console->error('❌ Commit message must contain at least one alphanumeric character');

                return ExitCode::FAILURE;
            }

            if (empty($sources)) {
                $this->console->error('❌ At least one target is required in non-interactive mode');

                return ExitCode::FAILURE;
            }
        }

        if ($message === null || empty($sources) || $folders === null) {
            $this->console->info('📝 Interactive mode enabled');
            $this->console->line();

            $form = $this->console->form()
                ->title('📋 Push configuration')
                ->line();

            if ($message === null) {
                $form->ask('💬 Commit message:', 'message', null, 'yellow');
            }

            if (empty($sources)) {
                $form->multiChoice('🎯 Select targets:', 'sources', array_keys($this->repositories), array_keys($this->repositories));
            }

            if ($folders === null) {
                $form->multiChoice('📁 Select folders to add:', 'folders', ['src', 'resources/views', 'config', 'database', 'tests', 'routes'], ['src', 'resources/views']);
            }

            $answers = $form->submit();

            if ($message === null) {
                $message = $answers->get('message');
            }

            if (empty($sources)) {
                $sources = $answers->get('sources');
            }

            if ($folders === null) {
                $folders = $answers->get('folders');
            }
        }

        if ($message === null || ! preg_match('/[a-zA-Z0-9]/', $message)) {
            $this->console->error('❌ Commit message must contain at least one alphanumeric character');

            return ExitCode::FAILURE;
        }

        if (empty($sources)) {
            $this->console->info('📋 No targets specified, pushing to all configured targets...');
            $this->console->line();

            $confirm = $this->console->form()
                ->confirm('⚠️  Push to all configured targets?', 'confirm', false)
                ->submit();

            if (! $confirm->get('confirm')) {
                $this->console->error('❌ Operation cancelled');

                return ExitCode::FAILURE;
            }

            $sources = array_keys($this->repositories);
        }

        $validSources = $this->validateSources($sources);

        if (empty($validSources)) {
            $this->console->error('❌ No valid targets found');

            return ExitCode::FAILURE;
        }

        $runTests = ! $noTests;

        $this->displayConfiguration($message, $validSources, $folders, $runTests, $forceWithLease, $force);

        if ($dryRun) {
            $this->console->newLine();
            $this->console->success('✅ Dry run completed successfully!');
            $this->console->line('📋 No actual changes were made.');

            return ExitCode::SUCCESS;
        }

        if ($runTests) {
            $testResult = $this->handleTests($force);
            if ($testResult !== ExitCode::SUCCESS) {
                return $testResult;
            }
        } else {
            $this->console->info('⏭️  Tests skipped');
            $this->console->line();
        }

        $commitResult = $this->commitChanges($message, $folders);
        if ($commitResult !== ExitCode::SUCCESS) {
            $this->console->error('❌ Commit failed');

            return ExitCode::FAILURE;
        }

        $this->console->success('✅ Commit completed successfully');
        $this->console->line();

        $pushResult = $this->pushToRemotes($validSources, $forceWithLease);
        if ($pushResult !== ExitCode::SUCCESS) {
            $this->console->error('❌ Push failed');

            return ExitCode::FAILURE;
        }

        $this->console->success('✅ Push completed successfully');
        $this->console->line();

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

    private function validateSources(array $sources): array
    {
        $valid = [];
        $available = array_keys($this->repositories);
        $hasInvalid = false;

        foreach ($sources as $source) {
            if (in_array($source, $available, true)) {
                $valid[] = $source;
            } else {
                $this->console->alertWarning(" Target '{$source}' does not exist in configuration");
                $hasInvalid = true;
            }
        }

        if ($hasInvalid) {
            return [];
        }

        return $valid;
    }

    private function displayConfiguration(string $message, array $sources, array $folders, bool $runTests, bool $forceWithLease, bool $force): void
    {
        $this->console->info('📋 Configuration:');
        $this->console->line();
        $this->console->keyValueWithValueColor([
            '💬 Message' => $message,
            '🎯 Targets' => implode(', ', $sources),
            '📁 Folders' => empty($folders) ? 'All files' : implode(', ', $folders),
            '🧪 Tests' => $runTests ? '✅ Enabled' : '⏭️  Skipped',
            '🔒 Force-with-lease' => $forceWithLease ? '✅ Yes' : '❌ No',
            '🔒 Force' => $force ? '✅ Yes' : '❌ No',
        ], 'green');
        $this->console->line();
    }

    private function handleTests(bool $force): ExitCode
    {
        $this->console->info('🧪 Running tests...');
        $this->console->line();

        $testResult = $this->runTests();

        if ($testResult !== ExitCode::SUCCESS) {
            if ($force) {
                $this->console->alertWarning(' Tests failed but --force is enabled, continuing...');
                $this->console->line();

                return ExitCode::SUCCESS;
            }

            $this->console->error('❌ Tests failed. Use --force to ignore.');

            return ExitCode::FAILURE;
        }

        $this->console->success('✅ Tests passed successfully');
        $this->console->line();

        return ExitCode::SUCCESS;
    }

    private function runTests(): ExitCode
    {
        $listProcess = new Process(['./vendor/bin/phpunit', '--list-tests']);
        $listProcess->run();
        $testList = $listProcess->getOutput();

        $this->totalTests = substr_count($testList, ' - ');
        $this->currentTest = 0;

        if ($this->totalTests === 0) {
            $this->console->alertWarning('No tests found');

            return ExitCode::SUCCESS;
        }

        $testNames = [];
        $lines = explode("\n", $testList);
        foreach ($lines as $line) {
            if (str_contains($line, ' - ')) {
                // Extraire la partie après " - "
                $parts = explode(' - ', $line);
                if (count($parts) >= 2) {
                    $testPath = $parts[1];
                    // Exploder par "::" pour séparer la classe et la méthode
                    $testParts = explode('::', $testPath);
                    if (count($testParts) >= 2) {
                        $className = $testParts[0];
                        $methodName = $testParts[1];
                        // Remplacer les _ par des espaces
                        $methodName = str_replace('_', ' ', $methodName);
                        // Extraire le nom court de la classe (sans le namespace complet)
                        $classParts = explode('\\', $className);
                        $shortClassName = end($classParts);
                        $testNames[] = $shortClassName.' → '.$methodName;
                    }
                }
            }
        }

        $this->vt->clear();
        $this->vt->add('status', '🧪 Running tests...');
        $this->vt->add('progress', '');
        $this->vt->add('current_test', '');
        $this->vt->add('count', '');
        $this->vt->render();
        $this->lastRenderTime = microtime(true) * 1000000;

        $bar = new ProgressBar(
            $this->totalTests,
            40,
            '🧪 Tests',
            '',
            true,
            $this->vt,
            'progress'
        );

        $process = new Process(['./vendor/bin/phpunit', '--stop-on-failure']);
        $process->setTimeout(300);
        $process->start();

        $dotCount = 0;

        $process->wait(function ($type, $buffer) use (&$dotCount, $testNames, $bar) {
            $buffer = trim($buffer);

            if ($buffer === '.') {
                $dotCount++;
                $this->currentTest = $dotCount;

                $currentTestName = $testNames[$dotCount - 1] ?? 'Running...';
                $this->currentTestName = $currentTestName;

                $bar->advance();

                $this->vt->update('current_test', '   '.$currentTestName);
                $this->vt->update('count', '   '.$this->currentTest.' / '.$this->totalTests);

                $this->renderWithThrottle();

                if ($this->isVerbose) {
                    $this->console->line('   ✅ '.$currentTestName);
                }
            } elseif ($this->isVerbose && ! empty($buffer) && $buffer !== '.') {
                $this->console->line($buffer);
            }
        });

        $bar->finish();

        $this->vt->remove('current_test');
        $this->vt->remove('count');

        if ($process->isSuccessful()) {
            $this->vt->update('status', '✅ All '.$this->totalTests.' tests passed successfully');
        } else {
            $this->vt->update('status', '❌ Tests failed at: '.$this->currentTestName);
        }
        $this->vt->render();

        if ($this->isVerbose) {
            if ($process->getOutput()) {
                $this->console->line($process->getOutput());
            }
            if ($process->getErrorOutput()) {
                $this->console->error($process->getErrorOutput());
            }
        }

        return $process->isSuccessful() ? ExitCode::SUCCESS : ExitCode::FAILURE;
    }

    private function commitChanges(string $message, array $folders): ExitCode
    {
        if (empty($folders)) {
            $args = ['git', 'add', '.'];
        } else {
            $args = ['git', 'add'];
            foreach ($folders as $folder) {
                $args[] = $folder;
            }
        }

        $process = new Process($args);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Error during git add: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        $process = new Process(['git', 'commit', '-m', $message]);
        $process->run();

        if (! $process->isSuccessful()) {
            if (str_contains($process->getErrorOutput(), 'nothing to commit')) {
                $this->console->info('ℹ️  No changes to commit');

                return ExitCode::SUCCESS;
            }

            $this->console->error('❌ Error during commit: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private function pushToRemotes(array $sources, bool $forceWithLease): ExitCode
    {
        $process = new Process(['git', 'branch', '--show-current']);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Unable to determine current branch');

            return ExitCode::FAILURE;
        }

        $branch = trim($process->getOutput());

        foreach ($sources as $source) {
            $remoteUrl = $this->repositories[$source] ?? null;

            if (! $remoteUrl) {
                $this->console->alertWarning(" Target '{$source}' has no URL configured");

                continue;
            }

            $this->console->info("   📤 Pushing to {$source} ({$remoteUrl})...");

            $args = ['git', 'push'];

            if ($forceWithLease) {
                $args[] = '--force-with-lease';
            }

            $args[] = $remoteUrl;
            $args[] = $branch;

            $process = new Process($args);
            $process->setTimeout(120);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->console->error("   ❌ Push to {$source} failed: ".$process->getErrorOutput());

                return ExitCode::FAILURE;
            }

            $this->console->success("   ✅ Push to {$source} successful");
        }

        return ExitCode::SUCCESS;
    }
}
