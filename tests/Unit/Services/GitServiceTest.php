<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Records\GitResultRecord;
use AndyDefer\LaravelUtils\Services\GitService;
use PHPUnit\Framework\TestCase;

final class GitServiceTest extends TestCase
{
    private GitService $service;

    private string $testRepoPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GitService;
        $this->testRepoPath = sys_get_temp_dir().'/git-test-'.uniqid();

        // Create a test repository
        $this->createTestRepository();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test repository
        if (is_dir($this->testRepoPath)) {
            $this->removeDirectory($this->testRepoPath);
        }
    }

    private function createTestRepository(): void
    {
        mkdir($this->testRepoPath, 0777, true);

        exec('cd '.escapeshellarg($this->testRepoPath).' && git init');
        exec('cd '.escapeshellarg($this->testRepoPath).' && git config user.name "Test User"');
        exec('cd '.escapeshellarg($this->testRepoPath).' && git config user.email "test@example.com"');

        file_put_contents($this->testRepoPath.'/test.txt', 'Initial content');

        exec('cd '.escapeshellarg($this->testRepoPath).' && git add test.txt');
        exec('cd '.escapeshellarg($this->testRepoPath).' && git commit -m "Initial commit"');
    }

    private function removeDirectory(string $path): void
    {
        exec('rm -rf '.escapeshellarg($path));
    }

    public function test_can_set_repository_path(): void
    {
        $result = $this->service->repositoryPath($this->testRepoPath);

        $this->assertSame($this->service, $result);
    }

    public function test_can_set_timeout(): void
    {
        $result = $this->service->timeout(600);

        $this->assertSame($this->service, $result);
    }

    public function test_can_set_verbose(): void
    {
        $result = $this->service->verbose(true);

        $this->assertSame($this->service, $result);
    }

    public function test_execute_returns_result_record(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->execute('status');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(ExitCode::SUCCESS, $result->exit_code);
        $this->assertSame('status', $result->command);
    }

    public function test_execute_returns_failure_for_invalid_command(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->execute('invalid-command');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertFalse($result->success);
        $this->assertSame(ExitCode::FAILURE, $result->exit_code);
        $this->assertNotEmpty($result->error);
    }

    public function test_fetch_returns_result(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->fetch('origin');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertSame('fetch origin', $result->command);
    }

    public function test_reset_returns_result(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->reset('HEAD~0', true);

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertSame('reset --hard HEAD~0', $result->command);
    }

    public function test_get_current_branch_returns_branch_name(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $branch = $this->service->getCurrentBranch();

        $this->assertSame('master', $branch);
    }

    public function test_get_current_branch_returns_null_when_not_in_repo(): void
    {
        $this->service->repositoryPath('/invalid/path');

        $branch = $this->service->getCurrentBranch();

        $this->assertNull($branch);
    }

    public function test_is_clean_returns_true_for_clean_repo(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->isClean();

        $this->assertTrue($result);
    }

    public function test_is_clean_returns_false_for_dirty_repo(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        // Modify a file
        file_put_contents($this->testRepoPath.'/test.txt', 'Modified content');

        $result = $this->service->isClean();

        $this->assertFalse($result);
    }

    public function test_get_current_commit_returns_hash(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $commit = $this->service->getCurrentCommit();

        $this->assertNotNull($commit);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $commit);
    }

    public function test_repository_exists_returns_true(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->repositoryExists();

        $this->assertTrue($result);
    }

    public function test_repository_exists_returns_false(): void
    {
        $this->service->repositoryPath('/invalid/path');

        $result = $this->service->repositoryExists();

        $this->assertFalse($result);
    }

    public function test_get_latest_tag_returns_null_when_no_tags(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $tag = $this->service->getLatestTag();

        $this->assertNull($tag);
    }

    public function test_create_tag_returns_success(): void
    {
        $this->service->repositoryPath($this->testRepoPath);

        $result = $this->service->createTag('v1.0.0', 'Initial release');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('tag v1.0.0', $result->command);
        $this->assertStringContainsString('Initial release', $result->command);
    }
}
