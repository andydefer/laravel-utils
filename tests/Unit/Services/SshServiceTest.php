<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Unit\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Collections\SshCommandResultRecordCollection;
use AndyDefer\LaravelUtils\Records\SshBatchResultRecord;
use AndyDefer\LaravelUtils\Records\SshResultRecord;
use AndyDefer\LaravelUtils\Services\SshService;
use PHPUnit\Framework\TestCase;

final class SshServiceTest extends TestCase
{
    private SshService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SshService;
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

    public function test_execute_returns_failure_when_command_fails(): void
    {
        $this->service->sshKey('invalid-host');

        $result = $this->service->execute('invalid-command');

        $this->assertInstanceOf(SshResultRecord::class, $result);
        $this->assertFalse($result->success);
        $this->assertNotEmpty($result->error);
    }

    public function test_execute_multiple_returns_batch_result(): void
    {
        $this->service->sshKey('o2switch');

        $commands = [
            'echo "Command 1"',
            'echo "Command 2"',
        ];

        $result = $this->service->executeMultiple($commands);

        $this->assertInstanceOf(SshBatchResultRecord::class, $result);
        $this->assertInstanceOf(SshCommandResultRecordCollection::class, $result->results);
        $this->assertIsArray($result->results->toArray());
        $this->assertCount(2, $result->results);
    }

    public function test_execute_multiple_stops_on_failure(): void
    {
        $this->service->sshKey('invalid-host');

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

    public function test_remote_path_exists_returns_false_when_remote_path_not_set(): void
    {
        $result = $this->service->remotePathExists();

        $this->assertFalse($result);
    }
}
