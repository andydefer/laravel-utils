<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Components\KeyValue;
use AndyDefer\ConsoleWriter\Console\Components\Logger;
use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\MapCollection;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Enums\VideoExtension;
use AndyDefer\LaravelUtils\Utilities\FileFinderUtility;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * CLI directive for compressing video files (MP4, AVI, MOV, MKV, etc.).
 *
 * @example
 * // Compress all videos in a directory
 * ./bin/afya videos:compress storage/app/public/videos compressed/videos ::preset=medium ::video_codec=libx264
 *
 * // Compress a specific video
 * ./bin/afya videos:compress storage/app/public/videos/video.mp4 compressed/videos ::crf=28
 *
 * // Compress with specific resolution
 * ./bin/afya videos:compress storage/app/public/videos compressed/videos width=1280 height=720
 */
final class CompressVideosDirective extends AbstractDirective
{
    private const DEFAULT_CRF = 28;

    private const DEFAULT_PRESET = 'medium';

    private const DEFAULT_VIDEO_CODEC = 'libx264';

    private const DEFAULT_AUDIO_CODEC = 'aac';

    private const DEFAULT_AUDIO_BITRATE = '128k';

    private const DEFAULT_PIXEL_FORMAT = 'yuv420p';

    private FileSystemInterface $fileSystem;

    private Console $console;

    public function getSignature(): string
    {
        return 'videos:compress 
                {source}#"Source directory or file path (directory scans recursively)" 
                {destination}#"Destination directory for compressed videos" 
                {width=0}#"Output width (0 = auto)" 
                {height=0}#"Output height (0 = auto)" 
                {crf=28}#"CRF quality (18-51, lower = better quality, default: 28)" 
                ::preset->[ultrafast,superfast,veryfast,faster,fast,medium,slow,slower,veryslow]=medium#"Encoding preset" 
                ::video_codec->[libx264,libx265,libvpx-vp9]=libx264#"Video codec" 
                ::audio_codec->[aac,mp3,ac3]=aac#"Audio codec" 
                ::audio_bitrate->[64k,96k,128k,192k,256k,320k]=128k#"Audio bitrate" 
                ::pixel_format->[yuv420p,yuv444p,rgb24]=yuv420p#"Pixel format" 
                {--dry-run}#"Simulate compression without actually encoding" 
                {--force}#"Force overwrite existing files"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['vc', 'compress-videos']);
    }

    public function getDescription(): string
    {
        return 'Compress video files using ffmpeg';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        $this->info('🎬 Starting video compression...');
        $this->newLine();

        $this->initializeServices();
        $this->ensureDependenciesAreInstalled();
        FileFinderUtility::ensureSourceExists($this->getArgument('source'), $this->fileSystem);
        FileFinderUtility::ensureDestinationExists($this->getArgument('destination'), $this->fileSystem);
    }

    protected function execute(): ExitCode
    {
        $config = $this->buildConfig();
        $this->initializeContext($config);

        $files = FileFinderUtility::findVideos(
            $config['source'],
            VideoExtension::values(),
            $this->fileSystem
        );

        if ($files->isEmpty()) {
            $this->getConsole()->alertWarning('⚠️ No video files found to compress');

            return ExitCode::SUCCESS;
        }

        $this->info('📁 Found '.$files->count().' videos to process');
        $this->newLine();

        if ($config['dryRun']) {
            $this->performDryRun($files, $config);

            return ExitCode::SUCCESS;
        }

        $this->processVideos($files, $config);
        $this->displaySummary();

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->newLine();
        if ($exitCode->isSuccess()) {
            $this->info('✅ Video compression completed');
        } else {
            $this->error('❌ Video compression failed');
        }
    }

    private function initializeServices(): void
    {
        $app = $this->getApplication();
        $this->fileSystem = $app->make(FileSystemInterface::class);
    }

    private function ensureDependenciesAreInstalled(): void
    {
        $requiredTools = ['ffmpeg', 'ffprobe'];
        $missing = array_filter($requiredTools, fn (string $tool): bool => ! $this->isToolInstalled($tool));

        if ($missing === []) {
            return;
        }

        $this->error('❌ Required tools not installed: '.implode(', ', $missing));
        $this->line('📦 Install them with:');
        $this->line('   sudo apt install ffmpeg');

        throw new RuntimeException('Missing dependencies: '.implode(', ', $missing));
    }

    private function isToolInstalled(string $tool): bool
    {
        $process = new Process(['which', $tool]);
        $process->run();

        return $process->isSuccessful();
    }

    private function buildConfig(): array
    {
        $width = (int) ($this->getArgument('width') ?? 0);
        $height = (int) ($this->getArgument('height') ?? 0);

        return [
            'source' => $this->getArgument('source'),
            'destination' => $this->getArgument('destination'),
            'crf' => (int) ($this->getArgument('crf') ?? self::DEFAULT_CRF),
            'preset' => $this->getEnum('preset') ?? self::DEFAULT_PRESET,
            'videoCodec' => $this->getEnum('video_codec') ?? self::DEFAULT_VIDEO_CODEC,
            'audioCodec' => $this->getEnum('audio_codec') ?? self::DEFAULT_AUDIO_CODEC,
            'audioBitrate' => $this->getEnum('audio_bitrate') ?? self::DEFAULT_AUDIO_BITRATE,
            'pixelFormat' => $this->getEnum('pixel_format') ?? self::DEFAULT_PIXEL_FORMAT,
            'width' => $width,
            'height' => $height,
            'dryRun' => $this->getFlag('dry-run'),
            'force' => $this->getFlag('force'),
        ];
    }

    private function initializeContext(array $config): void
    {
        $this->contextSet('processed_count', 0);
        $this->contextSet('skipped_count', 0);
        $this->contextSet('failed_count', 0);
        $this->contextSet('total_size_before', 0);
        $this->contextSet('total_size_after', 0);
        $this->contextSet('errors', []);
        $this->contextSet('config', $config);
    }

    private function performDryRun(Collection $files, array $config): void
    {
        $this->newLine();
        $this->info('📋 DRY RUN - No changes will be made');
        $this->newLine();
        $this->line('📋 Videos to process:');
        $this->newLine();

        $totalSize = 0;

        foreach ($files as $file) {
            $size = $this->fileSystem->size($file);
            $totalSize += $size;
            $relative = FileFinderUtility::getRelativePath($file, $config['source']);

            $outputParams = '';
            if ($config['width'] > 0 && $config['height'] > 0) {
                $outputParams .= " width={$config['width']} height={$config['height']}";
            }

            $this->line("   • {$relative} (".FileSizeUnit::format($size).") → CRF: {$config['crf']}, Preset: {$config['preset']}, Codec: {$config['videoCodec']}{$outputParams}");
        }

        $this->newLine();
        $this->line('📊 Total: '.$files->count().' videos, '.FileSizeUnit::format($totalSize));
    }

    private function processVideos(Collection $files, array $config): void
    {
        $totalVideos = $files->count();
        $currentVideo = 0;

        foreach ($files as $file) {
            $currentVideo++;
            $relativePath = FileFinderUtility::getRelativePath($file, $config['source']);

            // Log avant de commencer la compression
            echo Logger::info("🎬 Processing video {$currentVideo}/{$totalVideos}: {$relativePath}")."\n";

            $this->processSingleVideo($file, $config);

            // Log après la compression
            echo Logger::success("✅ Completed video {$currentVideo}/{$totalVideos}: {$relativePath}")."\n";
            echo Logger::info('   ───────────────────────────────────────────────')."\n";
        }
    }

    private function processSingleVideo(string $file, array $config): void
    {
        $relativePath = FileFinderUtility::getRelativePath($file, $config['source']);
        $baseName = pathinfo($file, PATHINFO_FILENAME);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        $destinationBase = rtrim($config['destination'], '/');
        $destinationSubDir = dirname($relativePath);

        $outputFile = $destinationBase;
        if ($destinationSubDir !== '.' && $destinationSubDir !== '/') {
            $outputFile .= '/'.$destinationSubDir;
        }
        $outputFile .= '/'.$baseName.'_compressed.'.$extension;

        if ($this->fileSystem->exists($outputFile) && ! $config['force']) {
            $this->getConsole()->alertWarning('   ⏭️  Output already exists, use --force to overwrite');
            $this->contextSet('skipped_count', $this->contextGet('skipped_count') + 1);

            return;
        }

        if (! $config['dryRun']) {
            $this->fileSystem->ensureDirectoryExists(dirname($outputFile));
        }

        $fileSizeBefore = $this->fileSystem->size($file);
        $success = $this->compressVideo($file, $outputFile, $config);

        if ($success) {
            $fileSizeAfter = $this->fileSystem->size($outputFile);
            $this->contextSet('processed_count', $this->contextGet('processed_count') + 1);
            $this->contextSet('total_size_before', $this->contextGet('total_size_before') + $fileSizeBefore);
            $this->contextSet('total_size_after', $this->contextGet('total_size_after') + $fileSizeAfter);

            $this->logCompressionResult($relativePath, $fileSizeBefore, $fileSizeAfter);
        } else {
            $this->contextSet('failed_count', $this->contextGet('failed_count') + 1);
            $this->error("   ❌ Failed to compress: {$relativePath}");
        }
    }

    private function compressVideo(string $source, string $output, array $config): bool
    {
        if ($config['dryRun']) {
            $this->line("   🔍 DRY RUN: Would compress: {$source}");

            return true;
        }

        $command = [
            'ffmpeg',
            '-i', $source,
            '-c:v', $config['videoCodec'],
            '-crf', (string) $config['crf'],
            '-preset', $config['preset'],
            '-c:a', $config['audioCodec'],
            '-b:a', $config['audioBitrate'],
            '-pix_fmt', $config['pixelFormat'],
        ];

        if ($config['width'] > 0 && $config['height'] > 0) {
            $command[] = '-vf';
            $command[] = 'scale='.$config['width'].':'.$config['height'];
        }

        $command[] = '-y';
        $command[] = $output;

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('   ❌ FFmpeg error: '.$process->getErrorOutput());

            return false;
        }

        return true;
    }

    private function logCompressionResult(string $relativePath, int $sizeBefore, int $sizeAfter): void
    {
        $saved = $sizeBefore - $sizeAfter;
        $savedPercent = $sizeBefore > 0 ? round(($saved / $sizeBefore) * 100, 1) : 0;

        $data = MapCollection::from([
            'File' => $relativePath,
            'Before' => FileSizeUnit::format($sizeBefore),
            'After' => FileSizeUnit::format($sizeAfter),
            'Saved' => FileSizeUnit::format($saved).' ('.$savedPercent.'%)',
        ]);

        if ($saved > 0) {
            $this->console->raw(KeyValue::renderWithValueColor($data, 'green'));
        } else {
            $this->console->raw(KeyValue::renderWithValueColor($data, 'yellow'));
        }
    }

    private function displaySummary(): void
    {
        $processedCount = $this->contextGet('processed_count');
        $failedCount = $this->contextGet('failed_count');
        $skippedCount = $this->contextGet('skipped_count');
        $sizeBefore = $this->contextGet('total_size_before');
        $sizeAfter = $this->contextGet('total_size_after');
        $saved = $sizeBefore - $sizeAfter;
        $savedPercent = $sizeBefore > 0 ? round(($saved / $sizeBefore) * 100, 1) : 0;

        $this->newLine();
        $this->line('📊 Summary:');

        $summary = MapCollection::from([
            'Videos processed' => $processedCount,
            'Videos skipped' => $skippedCount,
            'Videos failed' => $failedCount,
            'Size before' => FileSizeUnit::format($sizeBefore),
            'Size after' => FileSizeUnit::format($sizeAfter),
            'Space saved' => FileSizeUnit::format($saved).' ('.$savedPercent.'%)',
        ]);

        $this->console->raw(KeyValue::renderWithValueColor($summary, 'yellow'));
        $this->newLine();
    }
}
