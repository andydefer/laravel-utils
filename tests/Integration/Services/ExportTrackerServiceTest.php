<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Services;

use AndyDefer\LaravelUtils\Services\ExportTrackerService;
use AndyDefer\PhpServices\Services\FileSystemService;
use PHPUnit\Framework\TestCase;

final class ExportTrackerServiceTest extends TestCase
{
    private ExportTrackerService $tracker;

    private FileSystemService $fileSystem;

    private string $tempDir;

    private string $trackerBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/export-tracker-test-'.uniqid();
        $this->trackerBasePath = $this->tempDir.'/tracker';

        $this->fileSystem = new FileSystemService;
        $this->fileSystem->ensureDirectoryExists($this->tempDir);

        $this->tracker = new ExportTrackerService($this->trackerBasePath, 0);
    }

    protected function tearDown(): void
    {
        if ($this->fileSystem->exists($this->tempDir)) {
            $this->fileSystem->deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    private function createTestFile(string $path, string $content = 'test content'): string
    {
        $fullPath = $this->tempDir.'/'.$path;
        $dir = dirname($fullPath);

        if (! $this->fileSystem->exists($dir)) {
            $this->fileSystem->ensureDirectoryExists($dir);
        }

        $this->fileSystem->put($fullPath, $content);

        return $fullPath;
    }

    private function createTestDirectory(string $path): void
    {
        $fullPath = $this->tempDir.'/'.$path;
        $this->fileSystem->ensureDirectoryExists($fullPath);
    }

    public function test_mark_and_check_single_file(): void
    {
        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $this->assertFalse($this->tracker->isExported($filePath));

        $this->tracker->markAsExported($filePath);

        $this->assertTrue($this->tracker->isExported($filePath));
    }

    public function test_mark_and_check_multiple_files(): void
    {
        $files = [
            'images/photo1.jpg',
            'images/photo2.jpg',
            'videos/video1.mp4',
        ];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        foreach ($files as $file) {
            $this->assertFalse($this->tracker->isExported($file));
        }

        $this->tracker->markMultipleAsExported($files);

        foreach ($files as $file) {
            $this->assertTrue($this->tracker->isExported($file));
        }
    }

    public function test_is_exported_returns_false_for_new_file(): void
    {
        $filePath = 'images/new_photo.jpg';
        $this->createTestFile($filePath);

        $this->assertFalse($this->tracker->isExported($filePath));
    }

    public function test_remove_single_file(): void
    {
        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $this->tracker->markAsExported($filePath);
        $this->assertTrue($this->tracker->isExported($filePath));

        $result = $this->tracker->remove($filePath);
        $this->assertTrue($result);

        $this->assertFalse($this->tracker->isExported($filePath));
    }

    public function test_remove_multiple_files(): void
    {
        $files = [
            'images/photo1.jpg',
            'images/photo2.jpg',
            'videos/video1.mp4',
        ];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        $this->tracker->markMultipleAsExported($files);

        foreach ($files as $file) {
            $this->assertTrue($this->tracker->isExported($file));
        }

        $this->tracker->removeMultiple($files);

        foreach ($files as $file) {
            $this->assertFalse($this->tracker->isExported($file));
        }
    }

    public function test_filter_already_exported(): void
    {
        $files = [
            'images/photo1.jpg',
            'images/photo2.jpg',
            'images/photo3.jpg',
            'videos/video1.mp4',
            'videos/video2.mp4',
        ];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        $exportedFiles = ['images/photo1.jpg', 'images/photo2.jpg'];
        $this->tracker->markMultipleAsExported($exportedFiles);

        $notExported = $this->tracker->filterAlreadyExported($files);

        $this->assertCount(3, $notExported);
        $this->assertContains('images/photo3.jpg', $notExported);
        $this->assertContains('videos/video1.mp4', $notExported);
        $this->assertContains('videos/video2.mp4', $notExported);
        $this->assertNotContains('images/photo1.jpg', $notExported);
        $this->assertNotContains('images/photo2.jpg', $notExported);
    }

    public function test_clear_all_tracker(): void
    {
        $files = [
            'images/photo1.jpg',
            'images/photo2.jpg',
            'videos/video1.mp4',
        ];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        $this->tracker->markMultipleAsExported($files);

        foreach ($files as $file) {
            $this->assertTrue($this->tracker->isExported($file));
        }

        $this->tracker->clear();

        foreach ($files as $file) {
            $this->assertFalse($this->tracker->isExported($file));
        }
    }

    public function test_get_stats(): void
    {
        $stats = $this->tracker->getStats();

        $this->assertArrayHasKey('base_path', $stats);
        $this->assertEquals($this->trackerBasePath, $stats['base_path']);
        $this->assertArrayHasKey('ttl', $stats);
        $this->assertEquals(0, $stats['ttl']);
    }

    public function test_tracker_persists_after_recreation(): void
    {
        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $this->tracker->markAsExported($filePath);

        $newTracker = new ExportTrackerService($this->trackerBasePath, 0);

        $this->assertTrue($newTracker->isExported($filePath));
    }

    public function test_hash_changes_when_file_content_changes(): void
    {
        $filePath = 'images/photo.jpg';
        $fullPath = $this->createTestFile($filePath, 'initial content');

        // ✅ Passe le chemin absolu du fichier
        $initialHash = $this->tracker->generateHash($fullPath);
        $this->assertIsString($initialHash);

        $this->tracker->markAsExported($fullPath);
        $this->assertTrue($this->tracker->isExported($fullPath));

        $this->fileSystem->put($fullPath, 'modified content');
        clearstatcache();

        $newHash = $this->tracker->generateHash($fullPath);
        $this->assertNotEquals($initialHash, $newHash, 'Hash should change when content changes');
        $this->assertFalse($this->tracker->isExported($fullPath));

        $this->tracker->markAsExported($fullPath);
        $this->assertTrue($this->tracker->isExported($fullPath));
    }

    public function test_hash_changes_when_file_size_changes(): void
    {
        $filePath = 'images/photo.jpg';
        $fullPath = $this->createTestFile($filePath, 'short');

        $initialHash = $this->tracker->generateHash($fullPath);
        $this->assertIsString($initialHash);

        $this->tracker->markAsExported($fullPath);
        $this->assertTrue($this->tracker->isExported($fullPath));

        $this->fileSystem->put($fullPath, 'this is a much longer content that changes the file size');
        clearstatcache();

        $newHash = $this->tracker->generateHash($fullPath);
        $this->assertNotEquals($initialHash, $newHash, 'Hash should change when file size changes');
        $this->assertFalse($this->tracker->isExported($fullPath));
    }

    public function test_tracker_with_subdirectories(): void
    {
        $this->createTestDirectory('app/images');
        $this->createTestDirectory('app/videos');
        $this->createTestDirectory('public/assets');

        $files = [
            'app/images/photo1.jpg',
            'app/images/photo2.jpg',
            'app/videos/video1.mp4',
            'public/assets/css/style.css',
            'public/assets/js/app.js',
        ];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        $this->tracker->markMultipleAsExported($files);

        foreach ($files as $file) {
            $this->assertTrue($this->tracker->isExported($file));
        }
    }

    public function test_remove_nonexistent_file_returns_false(): void
    {
        $filePath = 'images/nonexistent.jpg';

        $result = $this->tracker->remove($filePath);

        $this->assertFalse($result);
    }

    public function test_remove_files_from_different_directories(): void
    {
        $files = [
            'images/photo1.jpg',
            'images/photo2.jpg',
            'videos/video1.mp4',
            'documents/file1.pdf',
            'documents/file2.pdf',
        ];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        $this->tracker->markMultipleAsExported($files);

        $filesToRemove = ['images/photo1.jpg', 'images/photo2.jpg'];
        $this->tracker->removeMultiple($filesToRemove);

        foreach ($filesToRemove as $file) {
            $this->assertFalse($this->tracker->isExported($file));
        }

        $remainingFiles = ['videos/video1.mp4', 'documents/file1.pdf', 'documents/file2.pdf'];
        foreach ($remainingFiles as $file) {
            $this->assertTrue($this->tracker->isExported($file));
        }
    }

    public function test_clean_expired_with_zero_ttl_does_not_expire(): void
    {
        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $this->tracker->markAsExported($filePath);

        $this->tracker->cleanExpired();

        $this->assertTrue($this->tracker->isExported($filePath));
    }

    public function test_tracker_with_ttl_expiration(): void
    {
        $trackerWithTTL = new ExportTrackerService($this->tempDir.'/tracker_ttl', 1);

        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $trackerWithTTL->markAsExported($filePath);
        $this->assertTrue($trackerWithTTL->isExported($filePath));

        sleep(2);

        $cleaned = $trackerWithTTL->cleanExpired();

        $this->assertFalse($trackerWithTTL->isExported($filePath));
        $this->assertGreaterThan(0, $cleaned);
    }

    public function test_mark_as_exported_twice_persists(): void
    {
        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $this->tracker->markAsExported($filePath);
        $this->assertTrue($this->tracker->isExported($filePath));

        $this->tracker->markAsExported($filePath);
        $this->assertTrue($this->tracker->isExported($filePath));
    }

    public function test_large_number_of_files(): void
    {
        $fileCount = 50;
        $files = [];

        for ($i = 0; $i < $fileCount; $i++) {
            $filePath = "images/photo_{$i}.jpg";
            $this->createTestFile($filePath);
            $files[] = $filePath;
        }

        $start = microtime(true);
        $this->tracker->markMultipleAsExported($files);
        $markTime = microtime(true) - $start;

        $this->assertLessThan(1, $markTime, 'Marking 50 files should take less than 1 second');

        $start = microtime(true);
        foreach ($files as $file) {
            $this->assertTrue($this->tracker->isExported($file));
        }
        $checkTime = microtime(true) - $start;

        $this->assertLessThan(1, $checkTime, 'Checking 50 files should take less than 1 second');
    }

    public function test_relative_paths_work_with_same_file(): void
    {
        $filePath = 'images/photo.jpg';
        $this->createTestFile($filePath);

        $this->tracker->markAsExported($filePath);
        $this->assertTrue($this->tracker->isExported($filePath));

        $this->assertTrue($this->tracker->isExported($filePath));
    }

    public function test_filter_already_exported_with_empty_array(): void
    {
        $result = $this->tracker->filterAlreadyExported([]);
        $this->assertEmpty($result);
    }

    public function test_filter_already_exported_with_all_exported(): void
    {
        $files = ['images/photo1.jpg', 'images/photo2.jpg'];

        foreach ($files as $file) {
            $this->createTestFile($file);
        }

        $this->tracker->markMultipleAsExported($files);

        $result = $this->tracker->filterAlreadyExported($files);
        $this->assertEmpty($result);
    }

    public function test_multiple_trackers_on_different_paths(): void
    {
        $tracker1 = new ExportTrackerService($this->tempDir.'/tracker1', 0);
        $tracker2 = new ExportTrackerService($this->tempDir.'/tracker2', 0);

        $file1 = 'file1.txt';
        $file2 = 'file2.txt';

        $this->createTestFile($file1);
        $this->createTestFile($file2);

        $tracker1->markAsExported($file1);
        $tracker2->markAsExported($file2);

        $this->assertTrue($tracker1->isExported($file1));
        $this->assertFalse($tracker1->isExported($file2));

        $this->assertFalse($tracker2->isExported($file1));
        $this->assertTrue($tracker2->isExported($file2));
    }
}
