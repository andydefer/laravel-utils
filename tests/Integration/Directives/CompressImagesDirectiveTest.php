<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Directives\CompressImagesDirective;
use AndyDefer\LaravelUtils\Enums\FileSizeUnit;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use AndyDefer\PhpServices\Services\FileSystemService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;

final class CompressImagesDirectiveTest extends IntegrationTestCase
{
    use DatabaseMigrations;

    private DirectiveTestingService $service;

    private FileSystemInterface $fileSystem;

    private string $testDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->areToolsInstalled()) {
            $this->markTestSkipped('pngquant or jpegoptim not installed. Run: sudo apt install pngquant jpegoptim');
        }

        $this->fileSystem = new FileSystemService;

        $this->testDirectory = 'storage/app/public/images/test';
        $this->fileSystem->ensureDirectoryExists($this->testDirectory);
        $this->cleanTestDirectory();

        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $this->service->getKernel()->addDirective(CompressImagesDirective::class);
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
        $tools = ['pngquant', 'jpegoptim'];

        foreach ($tools as $tool) {
            $process = new Process(['which', $tool]);
            $process->run();

            if (! $process->isSuccessful()) {
                return false;
            }
        }

        return true;
    }

    private function createTestImage(string $filename, int $width = 100, int $height = 100, string $format = 'jpg'): string
    {
        $this->fileSystem->ensureDirectoryExists($this->testDirectory);

        $fullPath = $this->testDirectory.'/'.$filename;

        $this->fileSystem->ensureDirectoryExists(dirname($fullPath));

        $image = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($image, 255, 255, 255);
        $red = imagecolorallocate($image, 255, 0, 0);
        $blue = imagecolorallocate($image, 0, 0, 255);

        imagefill($image, 0, 0, $white);
        imagerectangle($image, 10, 10, $width - 10, $height - 10, $red);
        imageline($image, 0, 0, $width, $height, $blue);

        match ($format) {
            'jpg', 'jpeg' => imagejpeg($image, $fullPath, 95),
            'png' => imagepng($image, $fullPath, 9),
            default => imagejpeg($image, $fullPath, 95),
        };

        imagedestroy($image);

        return $fullPath;
    }

    private function formatSize(int $bytes): string
    {
        return FileSizeUnit::format($bytes);
    }

    // ============================================================
    // TESTS
    // ============================================================

    public function test_compress_images_successfully(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.jpg', 800, 600, 'jpg');
        $this->createTestImage('image3.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('📁 Found 3 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image1.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image2.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image3.png'));
    }

    public function test_compress_with_destination(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image1.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image2.png'));
    }

    public function test_compress_with_subdirectories(): void
    {
        $this->createTestImage('root.jpg', 800, 600, 'jpg');
        $this->createTestImage('subdir/image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('subdir/image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 3 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/root.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/subdir/image1.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/subdir/image2.png'));
    }

    public function test_compress_with_deep_subdirectories(): void
    {
        $this->createTestImage('root.jpg', 800, 600, 'jpg');
        $this->createTestImage('level1/image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('level1/level2/image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 3 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/root.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/level1/image1.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/level1/level2/image2.png'));
    }

    public function test_compress_with_custom_png_quality(): void
    {
        $this->createTestImage('image.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} 45-50 50 --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image.png'));
    }

    public function test_compress_with_custom_jpg_quality(): void
    {
        $this->createTestImage('image.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} 45-50 40 --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image.jpg'));
    }

    public function test_compress_dry_run(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive --dry-run");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 DRY RUN - No changes will be made', $response->output);
        $this->assertStringContainsString('📋 Images to compress:', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertFalse($this->fileSystem->exists('storage/app/public/images/compressed/image1.jpg'));
        $this->assertFalse($this->fileSystem->exists('storage/app/public/images/compressed/image2.png'));
    }

    public function test_compress_with_strip_meta(): void
    {
        $this->createTestImage('image.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive --strip-meta");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image.jpg'));
    }

    public function test_compress_with_force(): void
    {
        $this->createTestImage('image.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive --force");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed/image.jpg'));
    }

    public function test_compress_shows_summary(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📊 Summary:', $response->output);
        $this->assertStringContainsString('📁 Files processed:', $response->output);
        $this->assertStringContainsString('📦 Size before:', $response->output);
        $this->assertStringContainsString('📦 Size after:', $response->output);
        $this->assertStringContainsString('💾 Space saved:', $response->output);
    }

    public function test_compress_with_invalid_source(): void
    {
        $response = $this->service->run('images:compress invalid/path');

        $this->assertSame(ExitCode::RUNTIME_ERROR, $response->exit_code);
        $this->assertStringContainsString('Source not found', $response->output);
    }

    public function test_compress_with_no_images(): void
    {
        $emptyDir = $this->testDirectory.'/empty';
        $this->fileSystem->ensureDirectoryExists($emptyDir);

        $source = 'storage/app/public/images/test/empty';

        $response = $this->service->run("images:compress {$source} {$source}/output");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('⚠️ No images found to compress', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_large_number_of_images(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->createTestImage("image_{$i}.jpg", 800, 600, 'jpg');
        }

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $start = microtime(true);
        $response = $this->service->run("images:compress {$source} {$destination} --recursive");
        $duration = microtime(true) - $start;

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 10 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
        $this->assertLessThan(30, $duration);
    }

    public function test_compress_jpg_images(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.jpg', 800, 600, 'jpg');
        $this->createTestImage('image3.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} 45-50 45 --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 3 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_png_images(): void
    {
        $this->createTestImage('image1.png', 800, 600, 'png');
        $this->createTestImage('image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} 30-40 50 --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 2 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_mixed_formats(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.jpeg', 800, 600, 'jpeg');
        $this->createTestImage('image3.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 3 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_alias_works(): void
    {
        $this->createTestImage('image.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("imc {$source} {$destination} --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_skip_images_smaller_than_max_size(): void
    {
        $this->createTestImage('small.jpg', 50, 50, 'jpg');
        $this->createTestImage('large.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} 45-50 50 20 --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 2 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        // ✅ Vérifier via le contexte que l'image small a été skip
        $context = $this->service->getKernel()->getContext();
        $this->assertSame(1, $context['processed_count'] ?? 0);
        $this->assertSame(1, $context['skipped_count'] ?? 0);
    }

    public function test_compress_with_max_size_zero(): void
    {
        $this->createTestImage('small.jpg', 50, 50, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} 45-50 50 0 --recursive");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📁 Found 1 images to process', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_skip_already_compressed_images(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        // Première compression
        $response1 = $this->service->run("images:compress {$source} {$destination} --recursive");
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);

        // Deuxième compression avec skip-compressed
        $response2 = $this->service->run("images:compress {$source} {$destination} --recursive --skip-compressed");

        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('📁 Found 2 images to process', $response2->output);
        $this->assertStringContainsString('✅ Compression completed', $response2->output);

        // ✅ Vérifier via le contexte que les 2 images ont été skip
        $context = $this->service->getKernel()->getContext();
        $this->assertSame(0, $context['processed_count'] ?? 0);
        $this->assertSame(2, $context['skipped_count'] ?? 0);
    }

    public function test_compress_with_skip_compressed_only(): void
    {
        $this->createTestImage('image.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        // Première compression
        $response1 = $this->service->run("images:compress {$source} {$destination} --recursive");
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);

        // Deuxième compression avec skip-compressed
        $response2 = $this->service->run("images:compress {$source} {$destination} --recursive --skip-compressed");

        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('📁 Found 1 images to process', $response2->output);
        $this->assertStringContainsString('✅ Compression completed', $response2->output);

        // ✅ Vérifier que l'image a été skip
        $context = $this->service->getKernel()->getContext();
        $this->assertSame(0, $context['processed_count'] ?? 0);
        $this->assertSame(1, $context['skipped_count'] ?? 0);
    }

    public function test_compress_with_max_size_and_skip_compressed(): void
    {
        $this->createTestImage('small.jpg', 50, 50, 'jpg');
        $this->createTestImage('large.jpg', 800, 600, 'jpg');
        $this->createTestImage('image.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response1 = $this->service->run("images:compress {$source} {$destination} --recursive");
        $this->assertSame(ExitCode::SUCCESS, $response1->exit_code);

        $response2 = $this->service->run("images:compress {$source} {$destination} --recursive --skip-compressed");

        $this->assertSame(ExitCode::SUCCESS, $response2->exit_code);
        $this->assertStringContainsString('📁 Found 3 images to process', $response2->output);
        $this->assertStringContainsString('✅ Compression completed', $response2->output);

        // ✅ Vérifier via le contexte que les 3 images ont été skip
        $context = $this->service->getKernel()->getContext();
        $this->assertSame(0, $context['processed_count'] ?? 0);
        $this->assertSame(3, $context['skipped_count'] ?? 0);
    }

    public function test_compress_with_all_flags(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.png', 800, 600, 'png');
        $this->createTestImage('small.jpg', 50, 50, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed-all';

        if ($this->fileSystem->exists($destination)) {
            $this->fileSystem->deleteDirectory($destination);
        }

        $command = "images:compress {$source} {$destination} --recursive --strip-meta --force";
        $response = $this->service->run($command);

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);

        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed-all/image1.jpg'));
        $this->assertTrue($this->fileSystem->exists('storage/app/public/images/compressed-all/image2.png'));

        $originalPath = 'storage/app/public/images/test/image1.jpg';
        $compressedPath = 'storage/app/public/images/compressed-all/image1.jpg';

        $originalSize = $this->fileSystem->size($originalPath);
        $compressedSize = $this->fileSystem->size($compressedPath);

        $this->assertLessThan($originalSize, $compressedSize, 'Image1.jpg should be compressed');
    }

    public function test_compress_dry_run_with_skip_compressed(): void
    {
        $this->createTestImage('image1.jpg', 800, 600, 'jpg');
        $this->createTestImage('image2.png', 800, 600, 'png');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("images:compress {$source} {$destination} --recursive --dry-run --skip-compressed");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📋 DRY RUN - No changes will be made', $response->output);
        $this->assertStringContainsString('📋 Images to compress:', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }

    public function test_compress_alias_with_flags(): void
    {
        $this->createTestImage('image.jpg', 800, 600, 'jpg');

        $source = 'storage/app/public/images/test';
        $destination = 'storage/app/public/images/compressed';

        $response = $this->service->run("imc {$source} {$destination} --recursive --skip-compressed");

        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('📷 Starting image compression...', $response->output);
        $this->assertStringContainsString('✅ Compression completed', $response->output);
    }
}
