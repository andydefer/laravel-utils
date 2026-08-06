<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Enums\VideoResolution;
use AndyDefer\LaravelUtils\Utilities\FileFinderUtility;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * CLI directive for generating HLS (HTTP Live Streaming) streams from MP4 videos.
 *
 * @example
 * // Generate HLS for all MP4 files in a directory
 * ./bin/afya videos:hls storage/app/public/videos compressed/hls ::preset=slow ::audio_bitrate=192k resolutions=240,360,480,720
 *
 * // Generate HLS for a specific file
 * ./bin/afya videos:hls storage/app/public/videos/video.mp4 compressed/hls resolutions=360,720
 *
 * // Generate HLS with custom segment duration
 * ./bin/afya videos:hls storage/app/public/videos compressed/hls segment-duration=6
 */
final class GenerateHlsDirective extends AbstractDirective
{
    private const DEFAULT_SEGMENT_DURATION = 4;

    private const DEFAULT_CRF = 28;

    private const DEFAULT_PRESET = 'fast';

    private const DEFAULT_AUDIO_BITRATE = '128k';

    private const VIDEO_EXTENSIONS = ['mp4'];

    private FileSystemInterface $fileSystem;

    public function getSignature(): string
    {
        return 'videos:hls 
                {source}#"Source directory or file path (directory scans for MP4 files)" 
                {destination}#"Destination directory for HLS output" 
                {segment-duration=4}#"Duration of each segment in seconds (default: 4)" 
                {crf=28}#"CRF quality (18-51, lower = better quality, default: 28)" 
                ::preset->[ultrafast,superfast,veryfast,faster,fast,medium,slow,slower,veryslow]=fast#"Encoding preset" 
                ::audio_bitrate->[64k,96k,128k,192k,256k,320k]=128k#"Audio bitrate" 
                {resolutions*>[144,240,360,480,720]}#"Resolutions to generate (e.g., 240,360,480,720)" 
                {--dry-run}#"Simulate generation without actually encoding" 
                {--force}#"Force overwrite existing files"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['hls', 'generate-hls']);
    }

    public function getDescription(): string
    {
        return 'Generate HLS (HTTP Live Streaming) streams from MP4 videos';
    }

    protected function beforeExecute(): void
    {
        $this->info('🎬 Starting HLS generation...');
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
            self::VIDEO_EXTENSIONS,
            $this->fileSystem
        );

        if ($files->isEmpty()) {
            $this->getConsole()->alertWarning('⚠️ No MP4 videos found to process');

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
            $this->info('✅ HLS generation completed');
        } else {
            $this->error('❌ HLS generation failed');
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
        $resolutionsInput = $this->getVariadic('resolutions');

        if (empty($resolutionsInput)) {
            $resolutionsInput = VideoResolution::defaults();
        }

        $resolutionsArray = array_filter($resolutionsInput, function ($r) {
            $resolution = VideoResolution::fromHeight((int) $r);

            return $resolution !== null;
        });

        if (empty($resolutionsArray)) {
            $resolutionsArray = VideoResolution::defaults();
        }

        return [
            'source' => $this->getArgument('source'),
            'destination' => $this->getArgument('destination'),
            'segmentDuration' => (int) ($this->getArgument('segment-duration') ?? self::DEFAULT_SEGMENT_DURATION),
            'crf' => (int) ($this->getArgument('crf') ?? self::DEFAULT_CRF),
            'preset' => $this->getEnum('preset') ?? self::DEFAULT_PRESET,
            'audioBitrate' => $this->getEnum('audio_bitrate') ?? self::DEFAULT_AUDIO_BITRATE,
            'resolutions' => $resolutionsArray,
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
            $resolutions = implode(',', $config['resolutions']);
            $this->line("   • {$relative} (".FileSizeUnit::format($size).") → HLS with resolutions: {$resolutions}");
        }

        $this->newLine();
        $this->line('📊 Total: '.$files->count().' videos, '.FileSizeUnit::format($totalSize));
    }

    private function processVideos(Collection $files, array $config): void
    {
        foreach ($files as $file) {
            $this->processSingleVideo($file, $config);
        }
    }

    private function processSingleVideo(string $file, array $config): void
    {
        $relativePath = FileFinderUtility::getRelativePath($file, $config['source']);
        $baseName = pathinfo($file, PATHINFO_FILENAME);

        $destinationBase = rtrim($config['destination'], '/');
        $outputDir = $destinationBase.'/'.$baseName;

        $this->info("🎬 Processing: {$relativePath}");
        $this->line("   📁 Output: {$outputDir}");

        if ($this->fileSystem->exists($outputDir) && ! $config['force']) {
            $this->getConsole()->alertWarning('   ⏭️  HLS already exists, use --force to overwrite');
            $this->contextSet('skipped_count', $this->contextGet('skipped_count') + 1);

            return;
        }

        if (! $config['dryRun']) {
            $this->fileSystem->ensureDirectoryExists($outputDir);
        }

        $fileSizeBefore = $this->fileSystem->size($file);
        $success = $this->generateHls($file, $outputDir, $config);

        if ($success) {
            $fileSizeAfter = $this->calculateHlsSize($outputDir);
            $this->contextSet('processed_count', $this->contextGet('processed_count') + 1);
            $this->contextSet('total_size_before', $this->contextGet('total_size_before') + $fileSizeBefore);
            $this->contextSet('total_size_after', $this->contextGet('total_size_after') + $fileSizeAfter);
            $this->logHlsResult($relativePath, $fileSizeBefore, $fileSizeAfter);
        } else {
            $this->contextSet('failed_count', $this->contextGet('failed_count') + 1);
            $this->error("   ❌ Failed to generate HLS for: {$relativePath}");
        }
    }

    private function generateHls(string $source, string $outputDir, array $config): bool
    {
        if ($config['dryRun']) {
            $this->line("   🔍 DRY RUN: Would generate HLS for: {$source}");

            return true;
        }

        $resolutions = $config['resolutions'];
        $segmentDuration = $config['segmentDuration'];
        $crf = $config['crf'];
        $preset = $config['preset'];
        $audioBitrate = $config['audioBitrate'];

        $variantStreams = [];
        foreach ($resolutions as $resolution) {
            $resolutionEnum = VideoResolution::fromHeight((int) $resolution);
            if ($resolutionEnum === null) {
                continue;
            }

            $variantDir = $outputDir.'/'.$resolutionEnum->label();
            $this->fileSystem->ensureDirectoryExists($variantDir);

            $variantPlaylist = $variantDir.'/playlist.m3u8';
            $variantSegmentPattern = $variantDir.'/segment_%03d.ts';

            $command = [
                'ffmpeg',
                '-i', $source,
                '-vf', 'scale=-2:'.$resolution,
                '-c:v', 'libx264',
                '-crf', (string) $crf,
                '-preset', $preset,
                '-c:a', 'aac',
                '-b:a', $audioBitrate,
                '-hls_time', (string) $segmentDuration,
                '-hls_playlist_type', 'vod',
                '-hls_segment_filename', $variantSegmentPattern,
                $variantPlaylist,
            ];

            $process = new Process($command);
            $process->setTimeout(3600);
            $process->run();

            if (! $process->isSuccessful()) {
                $this->error("   ❌ Error generating variant for resolution {$resolutionEnum->label()}:");
                $this->error($process->getErrorOutput());

                return false;
            }

            $bitrate = $this->estimateBitrate($source, (int) $resolution);
            $variantStreams[] = [
                'resolution' => $resolution,
                'bitrate' => $bitrate,
                'playlist' => $resolutionEnum->label().'/playlist.m3u8',
            ];

            $this->line("   ✅ Generated variant for {$resolutionEnum->label()}");
        }

        $this->generateMasterPlaylist($outputDir, $variantStreams);

        return true;
    }

    private function generateMasterPlaylist(string $outputDir, array $variantStreams): void
    {
        $masterContent = "#EXTM3U\n";
        $masterContent .= "#EXT-X-VERSION:3\n";

        foreach ($variantStreams as $variant) {
            $resolution = $variant['resolution'];
            $bitrate = $variant['bitrate'];
            $playlist = $variant['playlist'];

            $masterContent .= '#EXT-X-STREAM-INF:BANDWIDTH='.$bitrate.',RESOLUTION='.$resolution."p\n";
            $masterContent .= $playlist."\n";
        }

        $masterPath = $outputDir.'/playlist.m3u8';
        $this->fileSystem->put($masterPath, $masterContent);
        $this->line('   ✅ Master playlist created: playlist.m3u8');
    }

    private function estimateBitrate(string $source, int $resolution): int
    {
        $fileSize = $this->fileSystem->size($source);

        $process = new Process([
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $source,
        ]);
        $process->run();
        $duration = (float) trim($process->getOutput());

        if ($duration <= 0) {
            $duration = 60;
        }

        $baseBitrate = (int) ($fileSize * 8 / $duration);

        $resolutionFactor = 1 + (($resolution - 240) / 1000);
        $estimatedBitrate = (int) ($baseBitrate * $resolutionFactor);

        return max($estimatedBitrate, 300000);
    }

    private function calculateHlsSize(string $outputDir): int
    {
        $totalSize = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($outputDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        return $totalSize;
    }

    private function logHlsResult(string $relativePath, int $sizeBefore, int $sizeAfter): void
    {
        $saved = $sizeBefore - $sizeAfter;
        $savedPercent = $sizeBefore > 0 ? round(($saved / $sizeBefore) * 100, 1) : 0;

        if ($saved > 0) {
            $this->info("   ✅ {$relativePath} - saved ".FileSizeUnit::format($saved)." ({$savedPercent}%)");
        } else {
            $this->line("   ⏭️  {$relativePath} - no size reduction");
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
        $this->line("   📁 Videos processed: {$processedCount}");

        if ($skippedCount > 0) {
            $this->line("   ⏭️  Videos skipped: {$skippedCount}");
        }

        if ($failedCount > 0) {
            $this->line("   ❌ Videos failed: {$failedCount}");
        }

        $this->line('   📦 Size before: '.FileSizeUnit::format($sizeBefore));
        $this->line('   📦 Size after: '.FileSizeUnit::format($sizeAfter));
        $this->line('   💾 Space saved: '.FileSizeUnit::format($saved)." ({$savedPercent}%)");
    }
}
