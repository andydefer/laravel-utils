<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Records\GitResultRecord;
use AndyDefer\LaravelUtils\Services\GitCommandExecutor;
use AndyDefer\LaravelUtils\Services\GitService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class GitServiceTest extends TestCase
{
    private GitService $service;

    private GitCommandExecutor|MockObject $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = $this->createMock(GitCommandExecutor::class);
        $this->service = new GitService($this->executor);
    }

    public function test_can_set_repository_path(): void
    {
        $result = $this->service->repositoryPath('/fake/path');
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
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'On branch master',
                'exit_code' => 0,
            ]);

        $result = $this->service->execute('status');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame(ExitCode::SUCCESS, $result->exit_code);
        $this->assertSame('status', $result->command);
    }

    public function test_execute_returns_failure_for_invalid_command(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'git: invalid-command is not a git command',
                'exit_code' => 1,
            ]);

        $result = $this->service->execute('invalid-command');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertFalse($result->success);
        $this->assertSame(ExitCode::FAILURE, $result->exit_code);
        $this->assertNotEmpty($result->error);
    }

    public function test_fetch_returns_result(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Fetch completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->fetch('origin');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertSame('fetch origin', $result->command);
    }

    public function test_reset_returns_result(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Reset completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->reset('HEAD~0', true);

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertSame('reset --hard HEAD~0', $result->command);
    }

    public function test_get_current_branch_returns_branch_name(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'master',
                'exit_code' => 0,
            ]);

        $branch = $this->service->getCurrentBranch();

        $this->assertSame('master', $branch);
    }

    public function test_get_current_branch_returns_null_when_not_in_repo(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '',
                'exit_code' => 1,
            ]);

        $branch = $this->service->getCurrentBranch();

        $this->assertNull($branch);
    }

    public function test_is_clean_returns_true_for_clean_repo(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '',
                'exit_code' => 0,
            ]);

        $result = $this->service->isClean();

        $this->assertTrue($result);
    }

    public function test_is_clean_returns_false_for_dirty_repo(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => ' M test.txt',
                'exit_code' => 0,
            ]);

        $result = $this->service->isClean();

        $this->assertFalse($result);
    }

    public function test_get_current_commit_returns_hash(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1',
                'exit_code' => 0,
            ]);

        $commit = $this->service->getCurrentCommit();

        $this->assertNotNull($commit);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{42}$/', $commit);
    }

    public function test_repository_exists_returns_true(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '.git',
                'exit_code' => 0,
            ]);

        $result = $this->service->repositoryExists();

        $this->assertTrue($result);
    }

    public function test_repository_exists_returns_false(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '',
                'exit_code' => 1,
            ]);

        $result = $this->service->repositoryExists();

        $this->assertFalse($result);
    }

    public function test_get_latest_tag_returns_null_when_no_tags(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '',
                'exit_code' => 0,
            ]);

        $tag = $this->service->getLatestTag();

        $this->assertNull($tag);
    }

    public function test_get_latest_tag_returns_tag_when_exists(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'v1.0.0',
                'exit_code' => 0,
            ]);

        $tag = $this->service->getLatestTag();

        $this->assertSame('v1.0.0', $tag);
    }

    public function test_create_tag_returns_success(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '',
                'exit_code' => 0,
            ]);

        $result = $this->service->createTag('v1.0.0', 'Initial release');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('tag v1.0.0', $result->command);
        $this->assertStringContainsString('Initial release', $result->command);
    }

    public function test_push_returns_success(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Push completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->push('origin', 'master', false, false);

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('push origin master', $result->command);
    }

    public function test_push_with_force_and_tags(): void
    {
        $this->service->repositoryPath('/fake/path');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Push completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->push('origin', 'master', true, true);

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('push origin master --force-with-lease --tags', $result->command);
    }

    public function test_clone_returns_success(): void
    {
        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Clone completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->clone('https://github.com/test/repo.git');

        $this->assertInstanceOf(GitResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertStringContainsString('clone https://github.com/test/repo.git', $result->command);
    }
}
