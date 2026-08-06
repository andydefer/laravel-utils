<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Symfony\Component\Process\Process;

/**
 * CLI directive for creating a Git version tag.
 *
 * This directive creates a semantic version tag based on the current version,
 * with options for patch, minor, or major increments.
 *
 * @example
 * // Create a patch tag (0.0.0 -> 0.0.1)
 * ./bin/afya ugt
 *
 * // Create a minor tag (0.0.0 -> 0.1.0)
 * ./bin/afya ugt minor
 *
 * // Create a major tag (0.0.0 -> 1.0.0)
 * ./bin/afya ugt major
 *
 * // Republish the last tag
 * ./bin/afya ugt --republish
 */
final class GitTagDirective extends AbstractDirective
{
    private Console $console;

    private UtilsConfigInterface $config;

    public function getSignature(): string
    {
        return 'utils:git-tag 
                ::type->[patch,minor,major]=?#"Tag type (patch, minor, major)" 
                {--no-push}#"Skip pushing the tag" 
                {--republish}#"Republish the last tag (force push)"
                {--dry-run}#"Simulate the operation without actually executing"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['ugt']);
    }

    public function getDescription(): string
    {
        return 'Create a Git version tag (patch, minor, or major)';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->loadConfiguration();

        $this->console->title('🏷️ GIT TAG');
        $this->console->separatorDouble();
        $this->console->line();
    }

    private function loadConfiguration(): void
    {
        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);
    }

    protected function execute(): ExitCode
    {
        $type = $this->getArgument('type') ?? 'patch';
        $noPush = $this->getFlag('no-push');
        $dryRun = $this->getFlag('dry-run');
        $republish = $this->getFlag('republish');

        if ($republish) {
            return $this->republishTag($noPush, $dryRun);
        }

        $validTypes = ['patch', 'minor', 'major'];

        // Vérifier si le type est invalide (l'énumération retourne null)
        if ($this->getArgument('type') === null && ! in_array($this->getArgument('type'), $validTypes, true)) {
            $this->console->alertWarning('Invalid tag type. Using default: patch');
            $type = 'patch';
        }

        $lastTag = $this->getLastTag();
        [$major, $minor, $patch] = $this->parseVersion($lastTag);

        switch ($type) {
            case 'major':
                $major++;
                $minor = 0;
                $patch = 0;
                break;
            case 'minor':
                $minor++;
                $patch = 0;
                break;
            case 'patch':
            default:
                $patch++;
                break;
        }

        $newVersion = "{$major}.{$minor}.{$patch}";
        $newTag = "v{$newVersion}";
        $message = $this->getCustomDataItem('message', "Release {$newTag}");

        $this->displayConfiguration($type, $lastTag, $newTag, $message, $noPush);

        if ($dryRun) {
            $this->console->newLine();
            $this->console->success('✅ Dry run completed successfully!');
            $this->console->line('📋 No actual changes were made.');

            return ExitCode::SUCCESS;
        }

        $this->console->info("📦 Creating tag: {$newTag}");
        $this->console->line();

        $createResult = $this->createTag($newTag, $message);

        if ($createResult !== ExitCode::SUCCESS) {
            return $createResult;
        }

        $this->console->success("✅ Tag created: {$newTag}");
        $this->console->line();

        if (! $noPush) {
            $this->console->info('📤 Pushing tag to remote...');
            $this->console->line();

            $pushResult = $this->pushTag($newTag);

            if ($pushResult !== ExitCode::SUCCESS) {
                return $pushResult;
            }

            $this->console->success("✅ Tag pushed: {$newTag}");
            $this->console->line();
        } else {
            $this->console->info('⏭️  Tag push skipped');
            $this->console->line();
        }

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->console->newLine();
        if ($exitCode === ExitCode::SUCCESS) {
            $this->console->success('✅ Tag operation completed successfully!');
        } else {
            $this->console->error('❌ Tag operation failed');
        }
        $this->console->render();
    }

    private function republishTag(bool $noPush, bool $dryRun): ExitCode
    {
        $this->console->info('📋 Republishing last tag...');
        $this->console->line();

        $lastTag = $this->getLastTag();

        if ($lastTag === 'v0.0.0') {
            $this->console->error('❌ No tags found to republish');

            return ExitCode::FAILURE;
        }

        $this->console->info("📦 Last tag: {$lastTag}");
        $this->console->line();

        $this->displayConfiguration('republish', $lastTag, $lastTag, "Republish {$lastTag}", $noPush);

        if ($dryRun) {
            $this->console->newLine();
            $this->console->success('✅ Dry run completed successfully!');
            $this->console->line('📋 No actual changes were made.');

            return ExitCode::SUCCESS;
        }

        $this->console->info("📤 Republishing tag: {$lastTag} (force push)");
        $this->console->line();

        $pushResult = $this->pushTagForce($lastTag);

        if ($pushResult !== ExitCode::SUCCESS) {
            return $pushResult;
        }

        $this->console->success("✅ Tag republished: {$lastTag}");
        $this->console->line();

        return ExitCode::SUCCESS;
    }

    private function getLastTag(): string
    {
        $process = new Process([
            'git',
            'tag',
            '-l',
            '--sort=-v:refname',
            '--format=%(refname:strip=2)',
        ]);
        $process->run();

        $tags = explode("\n", trim($process->getOutput()));
        $tags = array_filter($tags);

        foreach ($tags as $tag) {
            if (preg_match('/^v?\d+\.\d+\.\d+$/', $tag)) {
                return $tag;
            }
        }

        return 'v0.0.0';
    }

    private function parseVersion(string $tag): array
    {
        $version = ltrim($tag, 'v');
        $parts = explode('.', $version);

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    private function displayConfiguration(string $type, string $lastTag, string $newTag, string $message, bool $noPush): void
    {
        $this->console->info('📋 Configuration:');
        $this->console->line();
        $this->console->keyValueWithValueColor([
            '🏷️  Type' => $type,
            '📦 Last tag' => $lastTag,
            '🆕 New tag' => $newTag,
            '💬 Message' => $message,
            '📤 Push' => $noPush ? '❌ No' : '✅ Yes',
        ], 'green');
        $this->console->line();
    }

    private function createTag(string $tag, string $message): ExitCode
    {
        $process = new Process(['git', 'tag', '-a', $tag, '-m', $message]);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Failed to create tag: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private function pushTag(string $tag): ExitCode
    {
        $process = new Process(['git', 'push', 'origin', $tag]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Failed to push tag: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }

    private function pushTagForce(string $tag): ExitCode
    {
        $process = new Process(['git', 'push', 'origin', $tag, '--force']);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->console->error('❌ Failed to republish tag: '.$process->getErrorOutput());

            return ExitCode::FAILURE;
        }

        return ExitCode::SUCCESS;
    }
}
