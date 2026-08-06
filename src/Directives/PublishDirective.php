<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\PhpServices\Services\FileSystemService;

/**
 * CLI directive for publishing directives from the package to the application.
 *
 * @example
 * // Publish all directives
 * ./bin/afya utils:publish
 *
 * // Force overwrite existing files
 * ./bin/afya utils:publish --force
 */
final class PublishDirective extends AbstractDirective
{
    private Console $console;

    private FileSystemService $filesystem;

    private UtilsConfigInterface $config;

    private int $copied = 0;

    private int $skipped = 0;

    private array $publishedFiles = [];

    public function getSignature(): string
    {
        return 'utils:publish {--force}#"Force overwrite existing files"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['up']);
    }

    public function getDescription(): string
    {
        return 'Publish application directives from package to the user\'s project';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->filesystem = new FileSystemService;
        $this->copied = 0;
        $this->skipped = 0;
        $this->publishedFiles = [];

        $app = $this->getApplication();
        $this->config = $app->make(UtilsConfigInterface::class);

        $this->console->title('📦 PUBLISH DIRECTIVES');
        $this->console->separatorDouble();
        $this->console->line();
    }

    protected function execute(): ExitCode
    {
        $force = $this->getFlag('force');

        $sourceDir = $this->config->getPublishSourcePath();
        $targetDir = $this->config->getPublishTargetPath();

        $this->console->info('📋 Source: '.$sourceDir);
        $this->console->info('📋 Target: '.$targetDir);
        $this->console->line();

        if (! $this->filesystem->exists($sourceDir)) {
            $this->console->error('❌ Source directory not found: '.$sourceDir);

            return ExitCode::FAILURE;
        }

        $this->console->info('📋 Copying directives from package to application...');
        $this->console->line();

        $this->buildTree($sourceDir);

        $result = $this->copyDirectory($sourceDir, $targetDir, $force);

        if (! $result) {
            $this->console->error('❌ Failed to copy directives');

            return ExitCode::FAILURE;
        }

        $this->displaySummary();
        $this->displayTree();

        $this->console->line();
        $this->console->success('🎉 Directives published successfully!');

        return ExitCode::SUCCESS;
    }

    private function buildTree(string $sourceDir): void
    {
        $this->buildTreeRecursive($sourceDir, '');
    }

    private function buildTreeRecursive(string $sourceDir, string $prefix): void
    {
        $items = $this->filesystem->glob($sourceDir.'/*');

        foreach ($items as $item) {
            $fileName = basename($item);
            $relativePath = $prefix.'/'.$fileName;

            if ($this->filesystem->isDirectory($item)) {
                $this->buildTreeRecursive($item, $relativePath);
            } else {
                $extension = $this->filesystem->extension($item);
                if ($extension === 'php') {
                    $this->publishedFiles[] = $relativePath;
                }
            }
        }
    }

    private function displayTree(): void
    {
        if (empty($this->publishedFiles)) {
            return;
        }

        $this->console->line();
        $this->console->info('📁 Published files:');

        $tree = $this->buildTreeStructure($this->publishedFiles);
        $this->console->treeWithIcons($tree, 'app/Directives', '📂', '📄');
    }

    private function buildTreeStructure(array $files): MapCollection
    {
        $tree = [];

        foreach ($files as $file) {
            $parts = explode('/', trim($file, '/'));
            $current = &$tree;

            foreach ($parts as $part) {
                if (! isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }

        return $this->arrayToMapCollection($tree);
    }

    private function arrayToMapCollection(array $array): MapCollection
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                $result[$key] = $this->arrayToMapCollection($value);
            } else {
                $result[$key] = MapCollection::from([]);
            }
        }

        return MapCollection::from($result);
    }

    private function copyDirectory(string $source, string $target, bool $force): bool
    {
        if (! $this->filesystem->exists($target)) {
            $this->filesystem->makeDirectory($target, recursive: true);
            $this->console->success('✅ Created directory: '.$target);
            $this->console->line();
        }

        $items = $this->filesystem->glob($source.'/*');

        foreach ($items as $item) {
            $fileName = basename($item);
            $targetPath = $target.'/'.$fileName;

            if ($this->filesystem->isDirectory($item)) {
                $this->copyDirectory($item, $targetPath, $force);

                continue;
            }

            $extension = $this->filesystem->extension($item);
            if ($extension !== 'php') {
                continue;
            }

            if ($this->filesystem->exists($targetPath) && ! $force) {
                $this->console->alertWarning(' ⏭️  Skipping: '.$fileName.' (already exists, use --force to overwrite)');
                $this->skipped++;

                continue;
            }

            $content = $this->filesystem->get($item);
            $this->filesystem->put($targetPath, $content);
            $this->console->success('✅ Published: '.$fileName);
            $this->copied++;
        }

        return true;
    }

    private function displaySummary(): void
    {
        $this->console->line();
        $this->console->info('📊 Summary:');
        $this->console->line('   Copied: '.$this->copied.' file(s)');
        if ($this->skipped > 0) {
            $this->console->alertWarning('  Skipped: '.$this->skipped.' file(s) (use --force to overwrite)');
        }
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->console->line();
        if ($exitCode->isSuccess()) {
            $this->console->success('🎉 Publishing completed successfully!');
        } else {
            $this->console->error('❌ Publishing failed');
        }
        $this->console->render();
    }
}
