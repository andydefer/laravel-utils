<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Components\Logger;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Services\VirtualTerminalService;
use AndyDefer\Directive\DirectiveKernel;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Enums\ImageExtension;
use AndyDefer\LaravelUtils\Records\DeploymentResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;
use AndyDefer\PhpServices\Services\FileSystemService;

final class ExportAssetsOperation
{
    private const BAR_WIDTH = 40;

    private const COMPLETE_CHAR = '█';

    private const EMPTY_CHAR = '░';

    private const RENDER_INTERVAL = 300000; // 300ms

    private static float $lastRenderTime = 0;

    private static ?VirtualTerminalService $vt = null;

    public static function handle(
        SshService $sshService,
        string $remotePath,
        array $assets,
        bool $force,
        bool $noCompress,
        bool $hls,
        bool $dryRun,
        ?Console $console = null,
        ?DirectiveKernel $kernel = null,
        ?UtilsConfigInterface $config = null
    ): DeploymentResultRecord {
        $commandsExecuted = [];

        if ($dryRun) {
            if ($console) {
                echo Logger::info('🔍 DRY RUN - Would execute:')."\n";
                echo Logger::info("   rsync -avz assets to {$remotePath}")."\n";
                if (! $noCompress) {
                    echo Logger::info('   images:compress (would compress images)')."\n";
                }
                if ($hls) {
                    echo Logger::info('   videos:hls (would generate HLS)')."\n";
                }
                echo "\n";
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Assets export dry run completed',
                'commands_executed' => ['rsync assets', 'compress images', 'generate hls'],
            ]);
        }

        if ($console) {
            echo Logger::info('📦 Exporting assets to server...')."\n";
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

        $hlsConfig = $config ? $config->getHlsConfig() : [];
        $imageCompressConfig = $config ? $config->getImageCompressConfig() : [];

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

            self::createRemoteDirectory($sshService, $remoteAssetPath, $console);
            self::cleanTemporaryFiles($sshService, $remoteAssetPath, $console);

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

            // Compression des images
            if (! $noCompress && $kernel !== null) {
                if ($console) {
                    echo Logger::info("📦 Compressing images in: {$asset}")."\n";
                }

                $imageExtensions = ImageExtension::values();
                $hasImages = false;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($assetTempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $ext = strtolower($file->getExtension());
                        if (in_array($ext, $imageExtensions, true)) {
                            $hasImages = true;
                            break;
                        }
                    }
                }

                if ($hasImages) {
                    $pngQuality = $imageCompressConfig['png_quality'] ?? '45-50';
                    $jpgQuality = $imageCompressConfig['jpg_quality'] ?? 50;
                    $maxSize = $imageCompressConfig['max_size'] ?? 0;
                    $stripMeta = $imageCompressConfig['strip_meta'] ?? false;

                    $compressedDir = $assetTempDir.'_compressed';
                    $fileSystem->ensureDirectoryExists($compressedDir);

                    $imageCommand = "images:compress {$assetTempDir} {$compressedDir}";
                    $imageCommand .= " {$pngQuality} {$jpgQuality} {$maxSize}";
                    $imageCommand .= ' --recursive --force';

                    if ($stripMeta) {
                        $imageCommand .= ' --strip-meta';
                    }

                    $result = $kernel->runSignature($imageCommand);
                    $commandsExecuted[] = $imageCommand;

                    if ($result === ExitCode::SUCCESS) {
                        if ($fileSystem->exists($compressedDir)) {
                            $fileSystem->deleteDirectory($assetTempDir);
                            $fileSystem->move($compressedDir, $assetTempDir);
                            if ($console) {
                                echo Logger::success("✅ Images compressed for: {$asset}")."\n";
                            }
                        }
                    } else {
                        if ($console) {
                            echo Logger::warning("⚠️  Compression failed for: {$asset}, using original")."\n";
                        }
                        if ($fileSystem->exists($compressedDir)) {
                            $fileSystem->deleteDirectory($compressedDir);
                        }
                    }
                }
            }

            // Génération HLS
            if ($hls && $kernel !== null) {
                if ($console) {
                    echo Logger::info("📦 Generating HLS for videos in: {$asset}")."\n";
                }

                $hasVideos = false;
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($assetTempDir, \RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->isFile() && strtolower($file->getExtension()) === 'mp4') {
                        $hasVideos = true;
                        break;
                    }
                }

                if ($hasVideos) {
                    $segmentDuration = $hlsConfig['segment_duration'] ?? 4;
                    $crf = $hlsConfig['crf'] ?? 28;
                    $preset = $hlsConfig['preset'] ?? 'fast';
                    $audioBitrate = $hlsConfig['audio_bitrate'] ?? '128k';
                    $resolutions = implode(',', $hlsConfig['resolutions'] ?? ['144', '240', '360', '480', '720']);

                    $hlsDir = $assetTempDir.'_hls';
                    $fileSystem->ensureDirectoryExists($hlsDir);

                    $hlsCommand = "videos:hls {$assetTempDir} {$hlsDir}";
                    $hlsCommand .= " {$segmentDuration} {$crf}";
                    $hlsCommand .= " {$preset} {$audioBitrate}";
                    $hlsCommand .= " [{$resolutions}]";
                    $hlsCommand .= ' --force';

                    $result = $kernel->runSignature($hlsCommand);
                    $commandsExecuted[] = $hlsCommand;

                    if ($result === ExitCode::SUCCESS) {
                        if ($fileSystem->exists($hlsDir)) {
                            self::mergeDirectory($fileSystem, $hlsDir, $assetTempDir);
                            $fileSystem->deleteDirectory($hlsDir);
                            if ($console) {
                                echo Logger::success("✅ HLS generated for: {$asset}")."\n";
                            }
                        }
                    } else {
                        if ($console) {
                            echo Logger::warning("⚠️  HLS generation failed for: {$asset}")."\n";
                        }
                        if ($fileSystem->exists($hlsDir)) {
                            $fileSystem->deleteDirectory($hlsDir);
                        }
                    }
                }
            }

            $sizeAfter = self::getDirectorySize($fileSystem, $assetTempDir);
            $totalSizeAfter += $sizeAfter;

            // ✅ Upload FICHIER PAR FICHIER vers le dossier cible
            $uploadSuccess = self::uploadDirectoryFiles(
                $fileSystem,
                $sshService,
                $assetTempDir,
                $remoteAssetPath,
                $asset,
                $console
            );

            $commandsExecuted[] = "rsync -avz {$asset} to server";

            if (! $uploadSuccess) {
                if ($console) {
                    echo Logger::error("❌ Failed to upload asset: {$asset}")."\n";
                }
                $skippedAssets++;

                continue;
            }

            if ($console) {
                echo Logger::success("✅ Asset uploaded: {$asset}")."\n";
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
                'Size before' => FileSizeUnit::format($totalSizeBefore),
                'Size after' => FileSizeUnit::format($totalSizeAfter),
                'Space saved' => FileSizeUnit::format($totalSizeBefore - $totalSizeAfter),
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
        ?Console $console
    ): bool {
        if ($console && self::$vt) {
            self::$vt->clear();
            self::$vt->add('status', '📤 Uploading files...');
            self::$vt->add('progress', '');
            self::$vt->add('current_file', '');
            self::$vt->add('count', '');
            self::$vt->render();
            self::$lastRenderTime = microtime(true) * 1000000;
        }

        // Récupérer tous les fichiers du dossier source
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

        // Upload fichier par fichier via scp
        foreach ($files as $file) {
            $remoteDir = dirname($file['remote']);
            $copiedFiles++;

            // Créer le dossier distant si nécessaire
            $createDirCmd = "ssh {$sshService->getSshKey()} 'mkdir -p {$remoteDir}'";
            exec($createDirCmd);

            // Upload du fichier
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

                return false;
            }
        }

        if ($console && self::$vt) {
            $bar = self::buildProgressBar($totalFiles, $totalFiles);
            self::$vt->update('progress', $bar);
            self::$vt->update('current_file', '');
            self::$vt->update('count', "   ✅ {$totalFiles} files uploaded");
            self::$vt->render();
        }

        return true;
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

    private static function createRemoteDirectory(SshService $sshService, string $remotePath, ?Console $console): void
    {
        if ($console) {
            echo Logger::info('📁 Creating remote directory: '.$remotePath)."\n";
        }

        $command = "ssh {$sshService->getSshKey()} 'mkdir -p {$remotePath}'";
        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            if ($console) {
                echo Logger::success('✅ Remote directory created')."\n";
                echo "\n";
            }
        } else {
            if ($console) {
                echo Logger::warning('⚠️  Could not create remote directory, continuing...')."\n";
                echo "\n";
            }
        }
    }

    private static function cleanTemporaryFiles(SshService $sshService, string $remotePath, ?Console $console): void
    {
        if ($console) {
            echo Logger::info('🧹 Cleaning temporary files on server...')."\n";
        }

        $commands = [
            "ssh {$sshService->getSshKey()} 'find {$remotePath} -name \".*.IfdBOl\" -delete 2>/dev/null || true'",
            "ssh {$sshService->getSshKey()} 'find {$remotePath} -name \"*.tmp\" -delete 2>/dev/null || true'",
        ];

        foreach ($commands as $command) {
            exec($command);
        }

        if ($console) {
            echo Logger::success('✅ Temporary files cleaned')."\n";
            echo "\n";
        }
    }

    private static function countFilesInDirectory(FileSystemService $fileSystem, string $directory): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
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

    private static function mergeDirectory(FileSystemService $fileSystem, string $source, string $destination): void
    {
        if (! is_dir($source)) {
            return;
        }

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
