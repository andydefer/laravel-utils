<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Directives\GenerateHlsDirective;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;

final class GenerateHlsDirectiveTest extends IntegrationTestCase
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

        $this->service->getKernel()->addDirective(GenerateHlsDirective::class);
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

    private function createTestVideo(string $filename, int $duration = 2): string
    {
        $this->fileSystem->ensureDirectoryExists($this->testDirectory);

        $fullPath = $this->testDirectory.'/'.$filename;

        $this->fileSystem->ensureDirectoryExists(dirname($fullPath));

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

    public function test_generate_hls_successfully(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        $response = $this->service->run("videos:hls {$source} {$destination}");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('📁 Found 1 videos to process', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/240p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/360p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/480p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/720p/playlist.m3u8'));
    }

    public function test_generate_hls_with_custom_resolutions(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        // Ordre: {source} {destination} {segment-duration} {crf} {resolutions*}
        // Variadique avec crochets
        $response = $this->service->run("videos:hls {$source} {$destination} 4 28 [360,720]");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/360p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/720p/playlist.m3u8'));
        $this->assertFalse($this->fileSystem->exists('storage/app/public/videos/hls/video/240p/playlist.m3u8'));
        $this->assertFalse($this->fileSystem->exists('storage/app/public/videos/hls/video/480p/playlist.m3u8'));
    }

    public function test_generate_hls_with_custom_segment_duration(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        // Ordre: {source} {destination} {segment-duration} {crf}
        $response = $this->service->run("videos:hls {$source} {$destination} 6 28");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_with_custom_crf(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        // Ordre: {source} {destination} {segment-duration} {crf}
        $response = $this->service->run("videos:hls {$source} {$destination} 4 23");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_with_custom_preset(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        // Enum: il faut juste la valeur
        $response = $this->service->run("videos:hls {$source} {$destination} slow");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_with_custom_audio_bitrate(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        // Enum: il faut juste la valeur
        $response = $this->service->run("videos:hls {$source} {$destination} 192k");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_dry_run(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage';
        $destination = 'storage2';

        $response = $this->service->run("videos:hls {$source} {$destination}  --dry-run --force");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 DRY RUN - No changes will be made', $response->output);
        $this->assertStringContainsString('📋 Videos to process:', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertFalse($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_with_force(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        $response1 = $this->service->run("videos:hls {$source} {$destination}");
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);

        $response2 = $this->service->run("videos:hls {$source} {$destination} --force");

        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response2->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response2->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_shows_summary(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        $response = $this->service->run("videos:hls {$source} {$destination}");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📊 Summary:', $response->output);
        $this->assertStringContainsString('Videos processed', $response->output);
        $this->assertStringContainsString('Size before', $response->output);
        $this->assertStringContainsString('Size after', $response->output);
        $this->assertStringContainsString('Space saved', $response->output);
    }

    public function test_generate_hls_with_invalid_source(): void
    {
        $response = $this->service->run('videos:hls invalid/path');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Source not found', $response->output);
    }

    public function test_generate_hls_with_no_videos(): void
    {
        $emptyDir = $this->testDirectory.'/empty';
        $this->fileSystem->ensureDirectoryExists($emptyDir);

        $source = 'storage/app/public/videos/test/empty';
        $destination = 'storage/app/public/videos/hls/empty';

        $response = $this->service->run("videos:hls {$source} {$destination}");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⚠️ No MP4 videos found to process', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);
    }

    public function test_generate_hls_alias_works(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        $response = $this->service->run("hls {$source} {$destination}");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/video/playlist.m3u8'));
    }

    public function test_generate_hls_with_subdirectories(): void
    {
        $this->createTestVideo('root.mp4', 3);
        $this->createTestVideo('subdir/video1.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls';

        $response = $this->service->run("videos:hls {$source} {$destination}");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 2 videos to process', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/root/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls/subdir/video1/playlist.m3u8'));
    }

    public function test_generate_hls_with_all_options(): void
    {
        $this->createTestVideo('video.mp4', 3);

        $source = 'storage/app/public/videos/test';
        $destination = 'storage/app/public/videos/hls-all';

        if ($this->fileSystem->exists($destination)) {
            $this->fileSystem->deleteDirectory($destination);
        }

        // Ordre correct: {source} {destination} {segment-duration} {crf} {preset} {audio_bitrate} {resolutions*} {flags}
        // Variadique avec crochets
        $response = $this->service->run("videos:hls {$source} {$destination} 6 23 slow 192k [240,360,480,720] --force");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🎬 Starting HLS generation...', $response->output);
        $this->assertStringContainsString('✅ HLS generation completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls-all/video/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls-all/video/240p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls-all/video/360p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls-all/video/480p/playlist.m3u8'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/videos/hls-all/video/720p/playlist.m3u8'));
    }
}
