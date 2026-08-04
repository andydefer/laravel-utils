<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Integration\Directives;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\Directive\Services\DirectiveTestingService;
use AndyDefer\LaravelUtils\Directives\GitResetDirective;
use AndyDefer\LaravelUtils\Tests\IntegrationTestCase;
use Symfony\Component\Process\Process;

/**
 * Integration tests for the GitResetDirective.
 *
 * @group integration
 * @group directives
 * @group git-reset
 */
final class GitResetDirectiveTest extends IntegrationTestCase
{
    private DirectiveTestingService $service;

    private string $testRepoPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary test repository
        $this->testRepoPath = sys_get_temp_dir().'/laravel-utils-test-repo-'.uniqid();
        $this->createTestRepository();

        // Initialize test service with the test repository as working directory
        $this->service = new DirectiveTestingService(
            application: $this->app,
            sourcePaths: []
        );

        $kernel = $this->service->getKernel();
        $kernel->addDirective(GitResetDirective::class);

        // Change working directory to test repository
        chdir($this->testRepoPath);
    }

    protected function tearDown(): void
    {
        // Clean up test repository
        $this->removeDirectory($this->testRepoPath);

        $this->service->destroy();
        parent::tearDown();
    }

    private function createTestRepository(): void
    {
        // Create directory
        mkdir($this->testRepoPath, 0777, true);

        // Initialize git repository
        $process = new Process(['git', 'init'], $this->testRepoPath);
        $process->run();

        // Create initial commit
        file_put_contents($this->testRepoPath.'/test.txt', 'Initial content');
        $process = new Process(['git', 'add', 'test.txt'], $this->testRepoPath);
        $process->run();

        $process = new Process(['git', 'commit', '-m', 'Initial commit'], $this->testRepoPath);
        $process->run();

        // Configure git user for tests
        $process = new Process(['git', 'config', 'user.name', 'Test User'], $this->testRepoPath);
        $process->run();

        $process = new Process(['git', 'config', 'user.email', 'test@example.com'], $this->testRepoPath);
        $process->run();
    }

    private function createModifiedFile(string $filename, string $content = 'Modified content'): void
    {
        file_put_contents($this->testRepoPath.'/'.$filename, $content);
    }

    private function createUntrackedFile(string $filename, string $content = 'Untracked content'): void
    {
        file_put_contents($this->testRepoPath.'/'.$filename, $content);
    }

    private function stageFile(string $filename): void
    {
        $process = new Process(['git', 'add', $filename], $this->testRepoPath);
        $process->run();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function getFileContent(string $filename): string
    {
        $path = $this->testRepoPath.'/'.$filename;

        return file_exists($path) ? file_get_contents($path) : '';
    }

    /**
     * Tests that the alias 'ugr' works correctly.
     */
    public function test_git_reset_alias_works(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('🗑️ GIT RESET', $response->output);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
    }

    /**
     * Tests that the directive resets modified files.
     */
    public function test_git_reset_modified_file(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);

        // Verify file was NOT modified (dry-run)
        $this->assertEquals('Modified content', $this->getFileContent('test.txt'));
    }

    /**
     * Tests that the directive removes untracked files.
     */
    public function test_git_reset_untracked_file(): void
    {
        // Arrange
        $this->createUntrackedFile('untracked.txt', 'Untracked content');

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);

        // Verify untracked file still exists (dry-run)
        $this->assertFileExists($this->testRepoPath.'/untracked.txt');
    }

    /**
     * Tests that the directive unstages added files.
     */
    public function test_git_reset_staged_file(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');
        $this->stageFile('test.txt');

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);

        // Verify file was NOT restored (dry-run)
        $this->assertEquals('Modified content', $this->getFileContent('test.txt'));
    }

    /**
     * Tests that the directive handles no changes gracefully.
     */
    public function test_git_reset_no_changes(): void
    {
        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('No changes to reset', $response->output);
        $this->assertStringContainsString('Repository is already clean', $response->output);
    }

    /**
     * Tests that the --force flag skips confirmation.
     */
    public function test_git_reset_force_skips_confirmation(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');

        // Act: Run without --dry-run to test the confirmation skip
        $response = $this->service->run('ugr --force');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Force mode enabled - skipping confirmation', $response->output);
        $this->assertStringContainsString('Repository reset successfully', $response->output);

        // Verify file was restored
        $this->assertEquals('Initial content', $this->getFileContent('test.txt'));
    }

    /**
     * Tests that the --dry-run flag prevents changes.
     */
    public function test_git_reset_dry_run_prevents_changes(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');

        // Act
        $response = $this->service->run('ugr --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);

        // Verify file was NOT restored (dry-run)
        $this->assertEquals('Modified content', $this->getFileContent('test.txt'));
    }

    /**
     * Tests that the directive handles multiple changes.
     */
    public function test_git_reset_multiple_changes(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');
        $this->createModifiedFile('test2.txt', 'Modified content 2');
        $this->createUntrackedFile('untracked.txt', 'Untracked content');

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);

        // Verify all changes still exist (dry-run)
        $this->assertEquals('Modified content', $this->getFileContent('test.txt'));
        $this->assertEquals('Modified content 2', $this->getFileContent('test2.txt'));
        $this->assertFileExists($this->testRepoPath.'/untracked.txt');
    }

    /**
     * Tests that the directive shows changes before reset.
     */
    public function test_git_reset_shows_changes(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', 'Modified content');
        $this->createUntrackedFile('untracked.txt', 'Untracked content');

        // Act
        $response = $this->service->run('ugr --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Changes to be removed:', $response->output);
        $this->assertStringContainsString('Modified:', $response->output);
        $this->assertStringContainsString('Untracked:', $response->output);
        $this->assertStringContainsString('test.txt', $response->output);
        $this->assertStringContainsString('untracked.txt', $response->output);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
    }

    /**
     * Tests that the directive shows file sizes.
     */
    public function test_git_reset_shows_file_sizes(): void
    {
        // Arrange
        $this->createModifiedFile('test.txt', str_repeat('X', 1500));

        // Act
        $response = $this->service->run('ugr --dry-run');

        $output = strip_ansi($response->output);

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        // Vérifier qu'il y a un nombre suivi d'une unité (B, KB, MB)
        $this->assertMatchesRegularExpression('/\d+\s+(B|KB|MB)/', $output);
        $this->assertMatchesRegularExpression('/Total:/', $output);
        $this->assertMatchesRegularExpression('/Dry run completed successfully/', $output);
    }

    /**
     * Tests that the directive handles invalid repository gracefully.
     */
    public function test_git_reset_invalid_repository(): void
    {
        // Arrange: Change to a non-git directory
        $tempDir = sys_get_temp_dir().'/non-git-'.uniqid();
        mkdir($tempDir);
        chdir($tempDir);

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::FAILURE, $response->exit_code);
        $this->assertStringContainsString('Not a Git repository', $response->output);

        // Cleanup
        rmdir($tempDir);
    }

    /**
     * Tests that the directive handles deleted files.
     */
    public function test_git_reset_deleted_file(): void
    {
        // Arrange: Delete a tracked file
        unlink($this->testRepoPath.'/test.txt');

        // Act
        $response = $this->service->run('ugr --force --dry-run');

        // Assert
        $this->assertSame(ExitCode::SUCCESS, $response->exit_code);
        $this->assertStringContainsString('Dry run completed successfully', $response->output);
        $this->assertStringContainsString('No actual changes were made', $response->output);

        // Verify file still deleted (dry-run)
        $this->assertFileDoesNotExist($this->testRepoPath.'/test.txt');
    }
}
