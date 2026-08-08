<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Components\Logger;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Services\VirtualTerminalService;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\ExportTrackerService;
use AndyDefer\LaravelUtils\Services\SshService;
use AndyDefer\PhpServices\Services\FileSystemService;

final class ExportAssetsOperation
{
    private const BAR_WIDTH = 40;

    private const COMPLETE_CHAR = '█';

    private const EMPTY_CHAR = '░';

    private const RENDER_INTERVAL = 300000;

    private static float $lastRenderTime = 0;

    private static ?VirtualTerminalService $vt = null;

    public static function handle(
        SshService $sshService,
        string $remotePath,
        array $assets,
        bool $forceExport,
        bool $dryRun,
        ?Console $console = null,
        ?ExportTrackerService $tracker = null
    ): DeploymentResultRecord {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                echo Logger::info('🔍 DRY RUN - Would execute:')."\n";
                echo Logger::info("   rsync -avz assets to {$remotePath}")."\n";
                if ($forceExport) {
                    echo Logger::info('   🧹 Force export: will overwrite existing files')."\n";
                } else {
                    echo Logger::info('   📝 Will skip existing files (tracker enabled)')."\n";
                }
                echo "\n";
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Assets export dry run completed',
                'commands_executed' => ['rsync assets'],
            ]);
        }

        if ($console) {
            echo Logger::info('📦 Exporting assets to server...')."\n";
            if ($forceExport) {
                echo Logger::info('🧹 Force export mode: will overwrite existing files')."\n";
            } else {
                echo Logger::info('📝 Skip mode: will skip already exported files (tracker enabled)')."\n";
            }
            echo "\n";
        }

        $fileSystem = new FileSystemService;
        $tempDir = sys_get_temp_dir().'/laravel-utils-export-'.uniqid();
        $fileSystem->ensureDirectoryExists($tempDir);

        if ($console) {
            self::$vt = new VirtualTerminalService($console->getAnsiConverter());
            self::$lastRenderTime = 0;
        }

        $totalSizeBefore = 0;
        $totalSizeAfter = 0;
        $processedAssets = 0;
        $skippedAssets = 0;
        $existingFilesSkipped = 0;

        $useTracker = $tracker !== null && ! $forceExport;

        foreach ($assets as $asset) {
            $sourcePath = getcwd().'/'.$asset;
            $assetName = $asset;
            $remoteAssetPath = rtrim($remotePath, '/').'/'.ltrim($assetName, '/');

            if (! $fileSystem->exists($sourcePath)) {
                if ($console) {
                    echo Logger::warning("⚠️  Asset not found locally: {$asset}")."\n";
                }
                $skippedAssets++;

                continue;
            }

            if ($console) {
                echo Logger::info("📦 Processing asset: {$asset}")."\n";
            }

            $assetTempDir = $tempDir.'/'.basename($asset);
            $fileSystem->ensureDirectoryExists($assetTempDir);

            if (is_dir($sourcePath)) {
                $copyResult = self::copyDirectory($fileSystem, $sourcePath, $assetTempDir);
            } else {
                $copyResult = $fileSystem->copy($sourcePath, $assetTempDir.'/'.basename($asset));
            }

            $commandsExecuted[] = "cp {$asset} to temp";

            if (! $copyResult) {
                if ($console) {
                    echo Logger::error("❌ Failed to copy asset: {$asset}")."\n";
                }
                $skippedAssets++;

                continue;
            }

            $sizeBefore = self::getDirectorySize($fileSystem, $assetTempDir);
            $totalSizeBefore += $sizeBefore;

            $sizeAfter = self::getDirectorySize($fileSystem, $assetTempDir);
            $totalSizeAfter += $sizeAfter;

            $uploadResult = self::uploadDirectoryFiles(
                $fileSystem,
                $sshService,
                $assetTempDir,
                $remoteAssetPath,
                $asset,
                $console,
                $forceExport,
                $useTracker ? $tracker : null
            );

            $commandsExecuted[] = "scp {$asset} to server";
            $existingFilesSkipped += $uploadResult['skipped'];

            if (! $uploadResult['success']) {
                if ($console) {
                    echo Logger::error("❌ Failed to upload asset: {$asset}")."\n";
                }
                $skippedAssets++;

                continue;
            }

            if ($console) {
                echo Logger::success("✅ Asset uploaded: {$asset}")."\n";
                if ($uploadResult['skipped'] > 0) {
                    echo Logger::info("   ⏭️  Skipped: {$uploadResult['skipped']} existing files")."\n";
                }
                if ($uploadResult['newly_exported'] > 0) {
                    echo Logger::info("   ✨ Newly exported: {$uploadResult['newly_exported']} files")."\n";
                }
                $saved = $sizeBefore - $sizeAfter;
                if ($saved > 0) {
                    echo Logger::info('   Saved: '.FileSizeUnit::format($saved))."\n";
                }
                echo Logger::info('   ───────────────────────────────────────────────')."\n";
            }

            $processedAssets++;
        }

        if ($fileSystem->exists($tempDir)) {
            $fileSystem->deleteDirectory($tempDir);
        }

        if ($console) {
            echo "\n";
            $summary = MapCollection::from([
                'Assets processed' => $processedAssets,
                'Assets skipped' => $skippedAssets,
                'Existing files skipped' => $existingFilesSkipped,

            ]);
            $console->raw(KeyValue::renderWithValueColor($summary, 'yellow'));
            echo "\n";
            echo Logger::success("✅ Assets export completed: {$processedAssets} assets")."\n";
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Assets exported successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }

    private static function uploadDirectoryFiles(
        FileSystemService $fileSystem,
        SshService $sshService,
        string $sourceDir,
        string $remotePath,
        string $assetName,
        ?Console $console,
        bool $forceExport = false,
        ?ExportTrackerService $tracker = null
    ): array {
        $result = [
            'success' => true,
            'skipped' => 0,
            'newly_exported' => 0,
        ];

        if ($console && self::$vt) {
            self::$vt->clear();
            self::$vt->add('status', '📤 Uploading files...');
            self::$vt->add('progress', '');
            self::$vt->add('current_file', '');
            self::$vt->add('count', '');
            self::$vt->render();
            self::$lastRenderTime = microtime(true) * 1000000;
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($sourceDir.'/', '', $file->getPathname());
                $files[] = [
                    'local' => $file->getPathname(),
                    'remote' => $remotePath.'/'.$relativePath,
                    'relative' => $relativePath,
                ];
            }
        }

        $totalFiles = count($files);
        $copiedFiles = 0;

        foreach ($files as $file) {
            $remoteDir = dirname($file['remote']);
            $copiedFiles++;

            if ($tracker !== null && $tracker->isExported($file['relative'])) {
                $result['skipped']++;
                if ($console) {
                    echo Logger::info("   ⏭️  Skipping (already exported): {$file['relative']}")."\n";
                }

                continue;
            }

            $createDirCmd = "ssh {$sshService->getSshKey()} 'mkdir -p {$remoteDir}'";
            exec($createDirCmd);

            $scpCmd = "scp {$file['local']} {$sshService->getSshKey()}:{$file['remote']}";
            exec($scpCmd, $output, $returnCode);

            if ($console && self::$vt) {
                $percentage = $totalFiles > 0 ? round(($copiedFiles / $totalFiles) * 100, 1) : 0;
                $bar = self::buildProgressBar($copiedFiles, $totalFiles);
                self::$vt->update('progress', $bar);
                self::$vt->update('current_file', "   📤 {$file['relative']}");
                self::$vt->update('count', "   📊 {$copiedFiles}/{$totalFiles} ({$percentage}%)");
                self::renderWithThrottle();
            }

            if ($returnCode !== 0) {
                if ($console) {
                    echo Logger::error("❌ Failed to upload: {$file['relative']}")."\n";
                }
                $result['success'] = false;

                return $result;
            }

            if ($tracker !== null) {
                $tracker->markAsExported($file['relative']);
                $result['newly_exported']++;
            }
        }

        if ($console && self::$vt) {
            $bar = self::buildProgressBar($totalFiles, $totalFiles);
            self::$vt->update('progress', $bar);
            self::$vt->update('current_file', '');
            self::$vt->update('count', "   ✅ {$totalFiles} files uploaded");
            self::$vt->render();
        }

        return $result;
    }

    private static function buildProgressBar(int $current, int $total): string
    {
        $percentage = $total > 0 ? ($current / $total) * 100 : 0;
        $filled = (int) round(self::BAR_WIDTH * ($current / max($total, 1)));

        return '['.
            str_repeat(self::COMPLETE_CHAR, $filled).
            str_repeat(self::EMPTY_CHAR, self::BAR_WIDTH - $filled).
            '] '.number_format($percentage, 0).'%';
    }

    private static function renderWithThrottle(): void
    {
        if (! self::$vt) {
            return;
        }

        $now = microtime(true) * 1000000;
        if ($now - self::$lastRenderTime >= self::RENDER_INTERVAL) {
            self::$vt->render();
            self::$lastRenderTime = $now;
        }
    }

    private static function copyDirectory(FileSystemService $fileSystem, string $source, string $destination): bool
    {
        if (! is_dir($source)) {
            return false;
        }

        $fileSystem->ensureDirectoryExists($destination);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relativePath = str_replace($source.'/', '', $item->getPathname());
            $targetPath = $destination.'/'.$relativePath;

            if ($item->isDir()) {
                $fileSystem->ensureDirectoryExists($targetPath);
            } else {
                $fileSystem->ensureDirectoryExists(dirname($targetPath));
                $fileSystem->copy($item->getPathname(), $targetPath);
            }
        }

        return true;
    }

    private static function getDirectorySize(FileSystemService $fileSystem, string $path): int
    {
        if (! $fileSystem->exists($path)) {
            return 0;
        }

        if ($fileSystem->isFile($path)) {
            return $fileSystem->size($path);
        }

        $totalSize = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }
}
