<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Directives;

use AndyDefer\ConsoleWriter\Console\Console;
use AndyDefer\ConsoleWriter\Console\Services\VirtualTerminalService;
use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Enums\ImageExtension;
use AndyDefer\LaravelUtils\Utilities\FileFinderUtility;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * CLI directive for compressing PNG and JPG/JPEG images.
 *
 * @example
 * // Compress all images in a directory
 * ./bin/afya images:compress storage/app/public/images compressed/images --recursive
 *
 * // Compress with custom quality
 * ./bin/afya images:compress storage/app/public/images compressed/images png-quality=30-40 jpg-quality=70
 *
 * // Dry run to see what would be compressed
 * ./bin/afya images:compress storage/app/public/images compressed/images --dry-run
 */
final class CompressImagesDirective extends AbstractDirective
{
    private const PNG_QUALITY_DEFAULT = '45-50';

    private const JPG_QUALITY_DEFAULT = 50;

    private const MIN_SIZE_THRESHOLD_BYTES = 10240;

    private const PNG_METADATA_RATIO_COMPRESSED = 0.10;

    private const PNG_METADATA_RATIO_UNCOMPRESSED = 0.30;

    private const BAR_WIDTH = 40;

    private const COMPLETE_CHAR = '█';

    private const EMPTY_CHAR = '░';

    private const RENDER_INTERVAL = 300000; // 300ms

    private static float $lastRenderTime = 0;

    private static ?VirtualTerminalService $vt = null;

    private FileSystemInterface $fileSystem;

    private Console $console;

    public function getSignature(): string
    {
        return 'images:compress 
                {source}#"Source directory containing images to compress" 
                {destination}#"Destination directory" 
                {png-quality=45-50}#"PNG quality range (min-max, e.g. 30-40)" 
                {jpg-quality=50}#"JPEG quality (0-100)" 
                {max-size=0}#"Skip images smaller than this size (in KB, 0 = disabled)" 
                {--strip-meta}#"Remove metadata (Exif, comments, etc.)" 
                {--recursive}#"Process subdirectories recursively" 
                {--dry-run}#"Simulate compression without modifying files" 
                {--force}#"Force overwrite existing files" 
                {--skip-compressed}#"Skip already compressed images"';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['imc']);
    }

    public function getDescription(): string
    {
        return 'Compress PNG and JPG/JPEG images using pngquant and jpegoptim';
    }

    protected function beforeExecute(): void
    {
        $this->console = new Console;
        self::$vt = new VirtualTerminalService($this->console->getAnsiConverter());
        self::$lastRenderTime = 0;

        $this->info('📷 Starting image compression...');
        $this->newLine();

        $this->initializeServices();
        $this->ensureDependenciesAreInstalled();
        FileFinderUtility::ensureSourceExists($this->getArgument('source'), $this->fileSystem);
        FileFinderUtility::ensureDestinationExists($this->getArgument('destination'), $this->fileSystem);
    }

    protected function execute(): ExitCode
    {
        $config = $this->buildCompressionConfig();
        $this->initializeContext($config);

        $files = FileFinderUtility::findImages(
            $config['source'],
            ImageExtension::values(),
            $this->fileSystem
        );

        if ($files->isEmpty()) {
            $this->getConsole()->alertWarning('⚠️ No images found to compress');

            return ExitCode::SUCCESS;
        }

        $this->info('📁 Found '.$files->count().' images to process');
        $this->newLine();

        if ($config['dryRun']) {
            $this->performDryRun($files);

            return ExitCode::SUCCESS;
        }

        $this->processImages($files, $config);
        $this->displaySummary();

        return ExitCode::SUCCESS;
    }

    protected function afterExecute(ExitCode $exitCode): void
    {
        $this->newLine();
        $this->info('✅ Compression completed');
    }

    private function initializeServices(): void
    {
        $app = $this->getApplication();
        $this->fileSystem = $app->make(FileSystemInterface::class);
    }

    private function ensureDependenciesAreInstalled(): void
    {
        $requiredTools = ['pngquant', 'jpegoptim'];
        $missing = array_filter($requiredTools, fn (string $tool): bool => ! $this->isToolInstalled($tool));

        if ($missing === []) {
            return;
        }

        $this->error('❌ Required tools not installed: '.implode(', ', $missing));
        $this->line('📦 Install them with:');
        $this->line('   sudo apt install '.implode(' ', $missing));

        throw new RuntimeException('Missing dependencies: '.implode(', ', $missing));
    }

    private function isToolInstalled(string $tool): bool
    {
        $process = new Process(['which', $tool]);
        $process->run();

        return $process->isSuccessful();
    }

    private function buildCompressionConfig(): array
    {
        $maxSizeKB = (int) ($this->getArgument('max-size') ?? 0);

        return [
            'source' => $this->getArgument('source'),
            'destination' => $this->getArgument('destination'),
            'pngQuality' => $this->getArgument('png-quality') ?? self::PNG_QUALITY_DEFAULT,
            'jpgQuality' => (int) ($this->getArgument('jpg-quality') ?? self::JPG_QUALITY_DEFAULT),
            'maxSize' => $maxSizeKB * 1024,
            'stripMeta' => $this->getFlag('strip-meta'),
            'recursive' => $this->getFlag('recursive'),
            'dryRun' => $this->getFlag('dry-run'),
            'force' => $this->getFlag('force'),
            'skipCompressed' => $this->getFlag('skip-compressed'),
        ];
    }

    private function initializeContext(array $config): void
    {
        $this->contextSet('processed_count', 0);
        $this->contextSet('skipped_count', 0);
        $this->contextSet('total_size_before', 0);
        $this->contextSet('total_size_after', 0);
        $this->contextSet('errors', []);
        $this->contextSet('max_size_threshold', $config['maxSize']);
        $this->contextSet('skip_compressed', $config['skipCompressed']);
    }

    private function performDryRun(Collection $files): void
    {
        $this->newLine();
        $this->info('📋 DRY RUN - No changes will be made');
        $this->newLine();
        $this->line('📋 Images to compress:');
        $this->newLine();

        $totalSize = 0;

        foreach ($files as $file) {
            $size = $this->fileSystem->size($file);
            $totalSize += $size;
            $relative = FileFinderUtility::getRelativePath($file, $this->getArgument('source'));
            $this->line("   • {$relative} (".FileSizeUnit::format($size).')');
        }

        $this->newLine();
        $this->line('📊 Total: '.$files->count().' files, '.FileSizeUnit::format($totalSize));
    }

    private function processImages(Collection $files, array $config): void
    {
        $totalFiles = $files->count();
        $currentFile = 0;
        $processedCount = 0;
        $skippedCount = 0;

        $this->console->line();

        if (self::$vt) {
            self::$vt->clear();
            self::$vt->add('status', '📦 Compressing images...');
            self::$vt->add('progress', '');
            self::$vt->add('current_file', '');
            self::$vt->add('count', '');
            self::$vt->render();
            self::$lastRenderTime = microtime(true) * 1000000;
        }

        foreach ($files as $file) {
            $currentFile++;
            $relativePath = FileFinderUtility::getRelativePath($file, $config['source']);
            $relativePath = str_replace($config['source'].'/', '', $relativePath);

            if (self::$vt) {
                $percentage = $totalFiles > 0 ? round(($currentFile / $totalFiles) * 100, 1) : 0;
                $bar = $this->buildProgressBar($currentFile, $totalFiles);
                self::$vt->update('progress', $bar);
                self::$vt->update('current_file', "   📤 {$relativePath}");
                self::$vt->update('count', "   📊 {$currentFile}/{$totalFiles} ({$percentage}%)");
                $this->renderWithThrottle();
            }

            if ($this->shouldSkipImage($file, $config)) {
                $skippedCount++;
                if (self::$vt) {
                    self::$vt->update('current_file', "   ⏭️  Skipping: {$relativePath}");
                    $this->renderWithThrottle();
                }

                continue;
            }

            $processedCount++;
            $this->compressSingleImage($file, $config);
        }

        // ✅ Mettre à jour le contexte via contextSet (écrit dans le kernel)
        $this->contextSet('processed_count', $processedCount);
        $this->contextSet('skipped_count', $skippedCount);

        if (self::$vt) {
            $bar = $this->buildProgressBar($totalFiles, $totalFiles);
            self::$vt->update('progress', $bar);
            self::$vt->update('current_file', '');
            self::$vt->update('count', "   ✅ {$processedCount} files processed");
            self::$vt->render();
        }

        $this->console->line();
    }

    private function renderWithThrottle(): void
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

    private function buildProgressBar(int $current, int $total): string
    {
        $percentage = $total > 0 ? ($current / $total) * 100 : 0;
        $filled = (int) round(self::BAR_WIDTH * ($current / max($total, 1)));

        return '['.
            str_repeat(self::COMPLETE_CHAR, $filled).
            str_repeat(self::EMPTY_CHAR, self::BAR_WIDTH - $filled).
            '] '.number_format($percentage, 0).'%';
    }

    private function shouldSkipImage(string $file, array $config): bool
    {
        $fileSize = $this->fileSystem->size($file);

        if ($this->isBelowMinSize($file, $fileSize, $config)) {
            return true;
        }

        if ($this->isAlreadyCompressed($file, $config)) {
            return true;
        }

        return false;
    }

    private function isBelowMinSize(string $file, int $fileSize, array $config): bool
    {
        if ($config['maxSize'] <= 0 || $fileSize >= $config['maxSize']) {
            return false;
        }

        return true;
    }

    private function isAlreadyCompressed(string $file, array $config): bool
    {
        if (! $config['skipCompressed']) {
            return false;
        }

        return $this->isImageAlreadyCompressed($file);
    }

    private function isImageAlreadyCompressed(string $filePath): bool
    {
        $fileSize = $this->fileSystem->size($filePath);

        if ($fileSize < self::MIN_SIZE_THRESHOLD_BYTES) {
            return true;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => $this->isPngAlreadyCompressed($filePath),
            'jpg', 'jpeg' => $this->isJpegAlreadyCompressed($filePath),
            default => false,
        };
    }

    private function isPngAlreadyCompressed(string $filePath): bool
    {
        $content = $this->fileSystem->get($filePath);
        $metadataRatio = $this->estimatePngMetadataRatio($content);

        return $metadataRatio < self::PNG_METADATA_RATIO_COMPRESSED;
    }

    private function isJpegAlreadyCompressed(string $filePath): bool
    {
        $content = $this->fileSystem->get($filePath);

        return ! str_contains($content, 'Exif');
    }

    private function estimatePngMetadataRatio(string $content): float
    {
        $hasAllChunks = str_contains($content, 'IHDR')
            && str_contains($content, 'PLTE')
            && str_contains($content, 'IDAT')
            && str_contains($content, 'IEND');

        return $hasAllChunks
            ? self::PNG_METADATA_RATIO_COMPRESSED
            : self::PNG_METADATA_RATIO_UNCOMPRESSED;
    }

    private function compressSingleImage(string $file, array $config): void
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $relativePath = FileFinderUtility::getRelativePath($file, $config['source']);
        $relativePath = str_replace($config['source'].'/', '', $relativePath);

        $destinationPath = $config['destination'].'/'.$relativePath;
        $destinationDir = dirname($destinationPath);

        if (! $this->fileSystem->exists($destinationDir)) {
            $this->fileSystem->ensureDirectoryExists($destinationDir);
        }

        match ($extension) {
            'png' => $this->compressPng($file, $destinationPath, $config['pngQuality'], $config['force']),
            'jpg', 'jpeg' => $this->compressJpg(
                $file,
                $destinationPath,
                $config['jpgQuality'],
                $config['stripMeta'],
                $config['force']
            ),
            default => null,
        };

        $this->updateStats($file, $destinationPath, $relativePath);
    }

    private function compressPng(string $source, string $destination, string $quality, bool $force): void
    {
        if ($source === $destination && ! $force) {
            $this->compressPngWithTempFile($source, $destination, $quality);
        } else {
            $this->compressPngDirect($source, $destination, $quality);
        }
    }

    private function compressPngDirect(string $source, string $destination, string $quality): void
    {
        $process = new Process([
            'pngquant',
            '--quality='.$quality,
            '--force',
            '--output',
            $destination,
            $source,
        ]);

        $process->run();

        if (! $process->isSuccessful() && $process->getErrorOutput()) {
            $this->getConsole()->alertWarning("⚠️ Error compressing {$source}: ".$process->getErrorOutput());
        }
    }

    private function compressPngWithTempFile(string $source, string $destination, string $quality): void
    {
        $tempFile = $destination.'.tmp';

        $process = new Process([
            'pngquant',
            '--quality='.$quality,
            '--force',
            '--output',
            $tempFile,
            $source,
        ]);

        $process->run();

        if ($process->isSuccessful() && $this->fileSystem->exists($tempFile)) {
            $this->fileSystem->delete($source);
            $this->fileSystem->move($tempFile, $destination);
        }

        if (! $process->isSuccessful() && $process->getErrorOutput()) {
            $this->getConsole()->alertWarning("⚠️ Error compressing {$source}: ".$process->getErrorOutput());
        }
    }

    private function compressJpg(string $source, string $destination, int $quality, bool $stripMeta, bool $force): void
    {
        $args = [
            'jpegoptim',
            '--max='.$quality,
            '--dest='.dirname($destination),
        ];

        if ($stripMeta) {
            $args[] = '--strip-all';
        }

        if ($force) {
            $args[] = '--force';
        }

        $args[] = $source;

        $process = new Process($args);
        $process->run();

        if ($source !== $destination && $process->isSuccessful()) {
            $this->handleJpgDestinationFallback($source, $destination);
        }
    }

    private function handleJpgDestinationFallback(string $source, string $destination): void
    {
        $sourceSize = $this->fileSystem->size($source);
        $destSize = $this->fileSystem->exists($destination)
            ? $this->fileSystem->size($destination)
            : 0;

        if ($sourceSize < $destSize) {
            $this->fileSystem->delete($destination);
            $this->fileSystem->copy($source, $destination);
        }
    }

    private function updateStats(string $file, string $destinationPath, string $relativePath): void
    {
        $fileSizeBefore = $this->fileSystem->size($file);
        $fileSizeAfter = $this->fileSystem->exists($destinationPath)
            ? $this->fileSystem->size($destinationPath)
            : $fileSizeBefore;

        $this->contextSet('processed_count', $this->contextGet('processed_count') + 1);
        $this->contextSet('total_size_before', $this->contextGet('total_size_before') + $fileSizeBefore);
        $this->contextSet('total_size_after', $this->contextGet('total_size_after') + $fileSizeAfter);
    }

    private function displaySummary(): void
    {
        $processedCount = $this->contextGet('processed_count');
        $skippedCount = $this->contextGet('skipped_count');
        $sizeBefore = $this->contextGet('total_size_before');
        $sizeAfter = $this->contextGet('total_size_after');
        $saved = $sizeBefore - $sizeAfter;
        $savedPercent = $sizeBefore > 0 ? round(($saved / $sizeBefore) * 100, 1) : 0;

        $this->newLine();
        $this->line('📊 Summary:');
        $this->line("   📁 Files processed: {$processedCount}");

        if ($skippedCount > 0) {
            $this->line("   ⏭️  Files skipped: {$skippedCount}");
        }

        $this->line('   📦 Size before: '.FileSizeUnit::format($sizeBefore));
        $this->line('   📦 Size after: '.FileSizeUnit::format($sizeAfter));
        $this->line('   💾 Space saved: '.FileSizeUnit::format($saved)." ({$savedPercent}%)");
    }
}
