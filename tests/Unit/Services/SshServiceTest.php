<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Collections\SshCommandResultRecordCollection;
use AndyDefer\LaravelUtils\Records\SshBatchResultRecord;
use AndyDefer\LaravelUtils\Records\SshResultRecord;
use AndyDefer\LaravelUtils\Services\ShellCommandExecutor;
use AndyDefer\LaravelUtils\Services\SshService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class SshServiceTest extends TestCase
{
    private SshService $service;

    private ShellCommandExecutor|MockObject $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = $this->createMock(ShellCommandExecutor::class);
        $this->service = new SshService($this->executor);
    }

    public function test_can_set_ssh_key(): void
    {
        $result = $this->service->sshKey('o2switch');
        $this->assertSame($this->service, $result);
    }

    public function test_can_set_remote_path(): void
    {
        $result = $this->service->remotePath('~/sites/laravel-utils.com');
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

    public function test_execute_returns_failure_when_ssh_key_not_set(): void
    {
        $result = $this->service->execute('echo "test"');

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertFalse($result->success);
        $this->assertSame('SSH key not set', $result->error);
        $this->assertSame(ExitCode::FAILURE, $result->exit_code);
        $this->assertSame('echo "test"', $result->command);
    }

    public function test_execute_returns_success(): void
    {
        $this->service->sshKey('o2switch');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'OK',
                'exit_code' => 0,
            ]);

        $result = $this->service->execute('echo "OK"');

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertTrue($result->success);
        $this->assertSame('OK', $result->output);
        $this->assertSame(ExitCode::SUCCESS, $result->exit_code);
    }

    public function test_execute_returns_failure_when_command_fails(): void
    {
        $this->service->sshKey('o2switch');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'command not found',
                'exit_code' => 127,
            ]);

        $result = $this->service->execute('invalid-command');

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->error);
        $this->assertSame(ExitCode::FAILURE, $result->exit_code);
    }

    public function test_execute_multiple_returns_batch_result(): void
    {
        $this->service->sshKey('o2switch');

        $this->executor
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                ['output' => 'Command 1 OK', 'exit_code' => 0],
                ['output' => 'Command 2 OK', 'exit_code' => 0]
            );

        $commands = [
            'echo "Command 1"',
            'echo "Command 2"',
        ];

        $result = $this->service->executeMultiple($commands);

        $this->assertInstanceOf(SshBatchResultRecord::class, $result);
        $this->assertInstanceOf(SshCommandResultRecordCollection::class, $result->results);
        $this->assertIsArray($result->results->toArray());
        $this->assertCount(2, $result->results);
        $this->assertTrue($result->success);
    }

    public function test_execute_multiple_stops_on_failure(): void
    {
        $this->service->sshKey('o2switch');

        $this->executor
            ->expects($this->exactly(2))
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                ['output' => 'Command 1 OK', 'exit_code' => 0],
                ['output' => 'Command 2 failed', 'exit_code' => 1]
            );

        $commands = [
            'echo "Command 1"',
            'invalid-command',
            'echo "Command 3"',
        ];

        $result = $this->service->executeMultiple($commands);

        $this->assertInstanceOf(SshBatchResultRecord::class, $result);
        $this->assertFalse($result->success);
        $this->assertLessThanOrEqual(2, $result->results->count());
    }

    public function test_is_reachable_returns_false_when_ssh_key_not_set(): void
    {
        $result = $this->service->isReachable();
        $this->assertFalse($result);
    }

    public function test_is_reachable_returns_true_when_ssh_ok(): void
    {
        $this->service->sshKey('o2switch');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'OK',
                'exit_code' => 0,
            ]);

        $result = $this->service->isReachable();
        $this->assertTrue($result);
    }

    public function test_is_reachable_returns_false_when_ssh_fails(): void
    {
        $this->service->sshKey('invalid-host');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'ssh: Could not resolve hostname',
                'exit_code' => 255,
            ]);

        $result = $this->service->isReachable();
        $this->assertFalse($result);
    }

    public function test_remote_path_exists_returns_false_when_remote_path_not_set(): void
    {
        $result = $this->service->remotePathExists();
        $this->assertFalse($result);
    }

    public function test_remote_path_exists_returns_true_when_path_exists(): void
    {
        $this->service->sshKey('o2switch');
        $this->service->remotePath('~/sites');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'EXISTS',
                'exit_code' => 0,
            ]);

        $result = $this->service->remotePathExists();
        $this->assertTrue($result);
    }

    public function test_remote_path_exists_returns_false_when_path_does_not_exist(): void
    {
        $this->service->sshKey('o2switch');
        $this->service->remotePath('~/sites/inexistant');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => '',
                'exit_code' => 1,
            ]);

        $result = $this->service->remotePathExists();
        $this->assertFalse($result);
    }

    public function test_git_fetch_returns_success(): void
    {
        $this->service->sshKey('o2switch');
        $this->service->remotePath('~/sites');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Fetch completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->gitFetch('origin', 'master');

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertTrue($result->success);
    }

    public function test_git_reset_returns_success(): void
    {
        $this->service->sshKey('o2switch');
        $this->service->remotePath('~/sites');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Reset completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->gitReset('origin/master', true);

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertTrue($result->success);
    }

    public function test_git_pull_returns_success(): void
    {
        $this->service->sshKey('o2switch');
        $this->service->remotePath('~/sites');

        $this->executor
            ->expects($this->once())
            ->method('execute')
            ->willReturn([
                'output' => 'Pull completed',
                'exit_code' => 0,
            ]);

        $result = $this->service->gitPull('origin', 'master');

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertTrue($result->success);
    }
}
