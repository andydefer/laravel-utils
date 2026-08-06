<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Console;
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
                $console->info('🔍 DRY RUN - Would execute:');
                $console->line("   rsync -avz assets to {$remotePath}");
                if (! $noCompress) {
                    $console->line('   images:compress (would compress images)');
                }
                if ($hls) {
                    $console->line('   videos:hls (would generate HLS)');
                }
                $console->line();
            }

            return DeploymentResultRecord::from([
                'success' => true,
                'message' => 'Assets export dry run completed',
                'commands_executed' => ['rsync assets', 'compress images', 'generate hls'],
            ]);
        }

        if ($console) {
            $console->info('📦 Exporting assets to server...');
            $console->line();
        }

        $fileSystem = new FileSystemService;
        $tempDir = sys_get_temp_dir().'/laravel-utils-export-'.uniqid();
        $fileSystem->ensureDirectoryExists($tempDir);

        $totalSizeBefore = 0;
        $totalSizeAfter = 0;
        $processedAssets = 0;
        $skippedAssets = 0;

        // Récupérer les configurations
        $hlsConfig = $config ? $config->getHlsConfig() : [];
        $imageCompressConfig = $config ? $config->getImageCompressConfig() : [];

        foreach ($assets as $asset) {
            $sourcePath = getcwd().'/'.$asset;
            $assetName = basename($asset);
            $remoteAssetPath = $remotePath.'/'.$assetName;

            if (! $fileSystem->exists($sourcePath)) {
                if ($console) {
                    $console->alertWarning("⚠️  Asset not found locally: {$asset}");
                }
                $skippedAssets++;

                continue;
            }

            $remoteExists = self::remoteAssetExists($sshService, $remoteAssetPath);

            if (! $force && $remoteExists) {
                if ($console) {
                    $console->logWarning("⏭️  Asset already exists on server: {$asset} (use --force to overwrite)");
                }
                $skippedAssets++;

                continue;
            }

            if ($console) {
                $console->logInfo("📦 Processing asset: {$asset}");
                if ($remoteExists) {
                    $console->logWarning('   ⚠️  Asset exists on server, will overwrite (--force enabled)');
                }
            }

            $assetTempDir = $tempDir.'/'.$assetName;
            $fileSystem->ensureDirectoryExists($assetTempDir);

            $copyResult = $fileSystem->copy($sourcePath, $assetTempDir.'/'.$assetName);
            $commandsExecuted[] = "cp {$asset} to temp";

            if (! $copyResult) {
                if ($console) {
                    $console->logError("❌ Failed to copy asset: {$asset}");
                }
                $skippedAssets++;

                continue;
            }

            $sizeBefore = $fileSystem->size($assetTempDir.'/'.$assetName);
            $totalSizeBefore += $sizeBefore;

            // Compression des images
            if (! $noCompress && $kernel !== null) {
                if ($console) {
                    $console->logInfo("📦 Compressing images in: {$asset}");
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
                    // Construction de la commande avec la config
                    $pngQuality = $imageCompressConfig['png_quality'] ?? '45-50';
                    $jpgQuality = $imageCompressConfig['jpg_quality'] ?? 50;
                    $maxSize = $imageCompressConfig['max_size'] ?? 0;
                    $stripMeta = $imageCompressConfig['strip_meta'] ?? false;

                    $imageCommand = "images:compress {$assetTempDir}/{$assetName} {$assetTempDir}/compressed";
                    $imageCommand .= " {$pngQuality} {$jpgQuality} {$maxSize}";
                    $imageCommand .= ' --recursive --force';

                    if ($stripMeta) {
                        $imageCommand .= ' --strip-meta';
                    }

                    $result = $kernel->runSignature($imageCommand);
                    $commandsExecuted[] = $imageCommand;

                    if ($result === ExitCode::SUCCESS) {
                        if ($fileSystem->exists($assetTempDir.'/compressed/'.$assetName)) {
                            $fileSystem->delete($assetTempDir.'/'.$assetName);
                            $fileSystem->move($assetTempDir.'/compressed/'.$assetName, $assetTempDir.'/'.$assetName);
                            $fileSystem->deleteDirectory($assetTempDir.'/compressed');
                            if ($console) {
                                $console->logSuccess("✅ Images compressed for: {$asset}");
                            }
                        }
                    } else {
                        if ($console) {
                            $console->logWarning("⚠️  Compression failed for: {$asset}, using original");
                        }
                    }
                }
            }

            // Génération HLS
            if ($hls && $kernel !== null) {
                if ($console) {
                    $console->logInfo("📦 Generating HLS for videos in: {$asset}");
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
                    // Construction de la commande HLS avec la config
                    $segmentDuration = $hlsConfig['segment_duration'] ?? 4;
                    $crf = $hlsConfig['crf'] ?? 28;
                    $preset = $hlsConfig['preset'] ?? 'fast';
                    $audioBitrate = $hlsConfig['audio_bitrate'] ?? '128k';
                    $resolutions = implode(',', $hlsConfig['resolutions'] ?? ['144', '240', '360', '480', '720']);

                    $hlsCommand = "videos:hls {$assetTempDir}/{$assetName} {$assetTempDir}/hls";
                    $hlsCommand .= " {$segmentDuration} {$crf}";
                    $hlsCommand .= " {$preset} {$audioBitrate}";
                    $hlsCommand .= " [{$resolutions}]";
                    $hlsCommand .= ' --force';

                    $result = $kernel->runSignature($hlsCommand);
                    $commandsExecuted[] = $hlsCommand;

                    if ($result === ExitCode::SUCCESS) {
                        if ($console) {
                            $console->logSuccess("✅ HLS generated for: {$asset}");
                        }
                    } else {
                        if ($console) {
                            $console->logWarning("⚠️  HLS generation failed for: {$asset}");
                        }
                    }
                }
            }

            $sizeAfter = $fileSystem->size($assetTempDir);
            $totalSizeAfter += $sizeAfter;

            if ($console) {
                $console->logInfo("📤 Uploading {$asset} to server...");
            }

            $rsyncCommand = "rsync -avz --delete {$assetTempDir}/ {$sshService->getSshKey()}:{$remotePath}/{$assetName}/";
            $rsyncResult = $sshService->execute($rsyncCommand, false);
            $commandsExecuted[] = "rsync -avz {$asset} to server";

            if (! $rsyncResult->success) {
                if ($console) {
                    $console->logError("❌ Failed to upload asset: {$asset}");
                    $console->logError('Error: '.$rsyncResult->error);
                }
                $skippedAssets++;

                continue;
            }

            if ($console) {
                $console->logSuccess("✅ Asset uploaded: {$asset}");
                $saved = $sizeBefore - $sizeAfter;
                if ($saved > 0) {
                    $console->logInfo('   Saved: '.FileSizeUnit::format($saved));
                }
                $console->logInfo('   ───────────────────────────────────────────────');
            }

            $processedAssets++;
        }

        $fileSystem->deleteDirectory($tempDir);

        if ($console) {
            $console->line();
            $summary = MapCollection::from([
                'Assets processed' => $processedAssets,
                'Assets skipped' => $skippedAssets,
                'Size before' => FileSizeUnit::format($totalSizeBefore),
                'Size after' => FileSizeUnit::format($totalSizeAfter),
                'Space saved' => FileSizeUnit::format($totalSizeBefore - $totalSizeAfter),
            ]);
            $console->raw(KeyValue::renderWithValueColor($summary, 'yellow'));
            $console->line();
            $console->logSuccess("✅ Assets export completed: {$processedAssets} assets");
        }

        return DeploymentResultRecord::from([
            'success' => true,
            'message' => 'Assets exported successfully',
            'commands_executed' => $commandsExecuted,
        ]);
    }

    private static function remoteAssetExists(SshService $sshService, string $remotePath): bool
    {
        $result = $sshService->execute("test -d {$remotePath} && echo 'EXISTS'", false);

        return $result->success && str_contains($result->output, 'EXISTS');
    }
}
