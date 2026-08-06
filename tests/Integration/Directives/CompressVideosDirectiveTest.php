<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Directives\CompressVideosDirective;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;

final class CompressVideosDirectiveTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private DirectiveTestingService $service;

    private FileSystemInterface $fileSystem;

    private string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->areToolsInstalled()) {
            $this->markTestSkipped('ffmpeg not installed. Run: sudo apt install ffmpeg');
        }

        $this->fileSystem = new FileSystemService;

        $this->testDirectory = 'storage/app/public/videos/test';
        $this->fileSystem->ensureDirectoryExists($this->testDirectory);
        $this->cleanTestDirectory();

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(CompressVideosDirective::class);
    }

    protected function tearDown(): void
    {
        $this->cleanTestDirectory();
        $this->service->destroy();
        parent::tearDown();
    }

    private function cleanTestDirectory(): void
    {
        if ($this->fileSystem->exists($this->testDirectory)) {
            $this->fileSystem->deleteDirectory($this->testDirectory);
        }
        $this->fileSystem->ensureDirectoryExists($this->testDirectory);
    }

    private function areToolsInstalled(): bool
    {
        $tools = ['ffmpeg', 'ffprobe'];

        foreach ($tools as $tool) {
            $process = new Process(['which', $tool]);
            $process->run();

            if (! $process->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    private function createTestVideo(string $filename, int $duration = 1): string
    {
        $this->fileSystem->ensureDirectoryExists($this->testDirectory);

        $fullPath = $this->testDirectory.'/'.$filename;

        $this->fileSystem->ensureDirectoryExists(dirname($fullPath));

        // Créer une vidéo de test avec ffmpeg
        $command = [
            'ffmpeg',
            '-f', 'lavfi',
            '-i', 'color=c=red:size=320x240:duration='.$duration,
            '-c:v', 'libx264',
            '-t', (string) $duration,
            '-y',
            $fullPath,
        ];

        $process = new Process($command);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->markTestSkipped('Could not create test video: '.$process->getErrorOutput());
        }

        return $fullPath;
    }

    private function formatSize(int $bytes): string
    {
        return FileSizeUnit::format($bytes);
    }

    // ============================================================
    // TESTS
    // ============================================================

    public function test_compress_videos_successfully(): void
    {
        // Arrange
        $this->createTestVideo('video1.mp4', 2);
        $this->createTestVideo('video2.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination}");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('📁 Found 2 videos to process', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video1_compressed.mp4'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video2_compressed.mp4'));
    }

    public function test_compress_with_subdirectories(): void
    {
        // Arrange
        $this->createTestVideo('root.mp4', 2);
        $this->createTestVideo('subdir/video1.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination}");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 2 videos to process', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/root_compressed.mp4'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/subdir/video1_compressed.mp4'));
    }

    public function test_compress_with_custom_crf(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act - Les arguments sont positionnels
        $response = $this->service->run("videos:compress {$source} {$destination} width=0 height=0 crf=23");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_custom_preset(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination} ::preset=slow");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_custom_resolution(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act - Les arguments sont positionnels: {source} {destination} {width} {height} {crf}
        $response = $this->service->run("videos:compress {$source} {$destination} width=640 height=360");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_custom_video_codec(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination} ::video_codec=libx265");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_custom_audio_codec(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination} ::audio_codec=mp3");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_custom_audio_bitrate(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination} ::audio_bitrate=192k");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_custom_pixel_format(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination} ::pixel_format=yuv444p");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_dry_run(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination} --dry-run");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 DRY RUN - No changes will be made', $response->output);
        $this->assertStringContainsString('📋 Videos to process:', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertFalse($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_force(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act - Flags dans l'ordre: --force
        $response = $this->service->run("videos:compress {$source} {$destination} --force");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_shows_summary(): void
    {
        // Arrange
        $this->createTestVideo('video1.mp4', 2);
        $this->createTestVideo('video2.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination}");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📊 Summary:', $response->output);
        $this->assertStringContainsString('Videos processed', $response->output);
        $this->assertStringContainsString('Size before', $response->output);
        $this->assertStringContainsString('Size after', $response->output);
        $this->assertStringContainsString('Space saved', $response->output);
    }

    public function test_compress_with_invalid_source(): void
    {
        // Act
        $response = $this->service->run('videos:compress invalid/path');

        // Assert
        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Source not found', $response->output);
    }

    public function test_compress_with_no_videos(): void
    {
        // Arrange
        $emptyDir = $this->testDirectory.'/empty';
        $this->fileSystem->ensureDirectoryExists($emptyDir);

        $source = 'storage/app/public/videos/test/empty';
        $destination = 'storage/app/public/videos/compressed/empty';

        // Act
        $response = $this->service->run("videos:compress {$source} {$destination}");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⚠️ No video files found to compress', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);
    }

    public function test_compress_alias_works(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $response = $this->service->run("vc {$source} {$destination}");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed/video_compressed.mp4'));
    }

    public function test_compress_with_all_flags_and_options(): void
    {
        // Arrange
        $this->createTestVideo('video.mp4', 2);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed-all';

        if ($this->fileSystem->exists($destination)) {
            $this->fileSystem->deleteDirectory($destination);
        }

        // Act - Flags dans l'ordre: --force --dry-run
        $response = $this->service->run("videos:compress {$source} {$destination} width=640 height=360 crf=23 ::preset=fast ::video_codec=libx264 ::audio_codec=aac ::audio_bitrate=128k ::pixel_format=yuv420p --force");

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting video compression...', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/compressed-all/video_compressed.mp4'));

        $originalPath = 'storage/app/public/videos/test/video.mp4';
        $compressedPath = 'storage/app/public/videos/compressed-all/video_compressed.mp4';

        $originalSize = $this->fileSystem->size($originalPath);
        $compressedSize = $this->fileSystem->size($compressedPath);

        $this->assertLessThan($originalSize, $compressedSize, 'Video should be compressed');
    }

    public function test_compress_large_video(): void
    {
        // Arrange
        $this->createTestVideo('large_video.mp4', 5);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/compressed';

        // Act
        $start = microtime(true);
        $response = $this->service->run("videos:compress {$source} {$destination} --force");
        $duration = microtime(true) - $start;

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 1 videos to process', $response->output);
        $this->assertStringContainsString('✅ Video compression completed', $response->output);
        $this->assertLessThan(120, $duration);
    }
}
