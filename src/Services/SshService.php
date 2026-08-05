<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Collections\SshCommandResultRecordCollection;
use AndyDefer\LaravelUtils\Records\SshBatchResultRecord;
use AndyDefer\LaravelUtils\Records\SshCommandResultRecord;
use AndyDefer\LaravelUtils\Records\SshResultRecord;

final class SshService
{
    private ?string $sshKey = null;

    private ?string $remotePath = null;

    private int $timeout = 300;

    private bool $verbose = false;

    public function __construct(
        private ShellCommandExecutor $executor
    ) {}

    public function sshKey(string $key): self
    {
        $this->sshKey = $key;

        return $this;
    }

    public function remotePath(string $path): self
    {
        $this->remotePath = $path;

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function verbose(bool $verbose = true): self
    {
        $this->verbose = $verbose;

        return $this;
    }

    public function execute(string $command, bool $changeDir = true): SshResultRecord
    {
        if (empty($this->sshKey)) {
            return SshResultRecord::from([
                'success' => false,
                'output' => '',
                'error' => 'SSH key not set',
                'exit_code' => ExitCode::FAILURE,
                'command' => $command,
            ]);
        }

        $fullCommand = $this->buildCommand($command, $changeDir);

        $result = $this->executor->execute($fullCommand);

        $exitCode = ExitCode::tryFrom($result['exit_code']) ?? ExitCode::FAILURE;

        return SshResultRecord::from([
            'success' => $result['exit_code'] === 0,
            'output' => trim($result['output']),
            'error' => $result['exit_code'] !== 0 ? trim($result['output']) : '',
            'exit_code' => $exitCode,
            'command' => $command,
        ]);
    }

    public function executeMultiple(array $commands, bool $changeDir = true): SshBatchResultRecord
    {
        $results = new SshCommandResultRecordCollection;
        $allOutput = '';
        $allError = '';
        $globalSuccess = true;

        foreach ($commands as $command) {
            $result = $this->execute($command, $changeDir);

            $commandResult = SshCommandResultRecord::from([
                'command' => $command,
                'success' => $result->success,
                'output' => $result->output,
                'error' => $result->error,
                'exit_code' => $result->exit_code,
            ]);

            $results->add($commandResult);
            $allOutput .= $result->output."\n";

            if (! $result->success) {
                $allError .= "Command failed: {$command}\n".$result->error."\n";
                $globalSuccess = false;
                break;
            }
        }

        $exitCode = $globalSuccess ? ExitCode::SUCCESS : ExitCode::FAILURE;

        return SshBatchResultRecord::from([
            'success' => $globalSuccess,
            'output' => trim($allOutput),
            'error' => trim($allError),
            'exit_code' => $exitCode,
            'results' => $results,
        ]);
    }

    public function isReachable(): bool
    {
        if (empty($this->sshKey)) {
            return false;
        }

        $result = $this->execute('echo "OK"', false);

        return $result->success && str_contains($result->output, 'OK');
    }

    public function remotePathExists(): bool
    {
        if (empty($this->remotePath)) {
            return false;
        }

        $result = $this->execute('test -d '.$this->remotePath.' && echo "EXISTS"', false);

        return $result->success && str_contains($result->output, 'EXISTS');
    }

    private function buildCommand(string $command, bool $changeDir): string
    {
        $fullCommand = $command;

        if ($changeDir && ! empty($this->remotePath)) {
            $fullCommand = 'cd '.$this->remotePath.' && '.$fullCommand;
        }

        return "ssh {$this->sshKey} '".str_replace("'", "'\\''", $fullCommand)."'";
    }

    public function gitFetch(string $remote = 'origin', ?string $branch = null): SshResultRecord
    {
        $command = "cd {$this->remotePath} && git fetch {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }

        return $this->execute($command, false);
    }

    public function gitReset(string $target, bool $hard = true): SshResultRecord
    {
        $mode = $hard ? '--hard' : '--soft';
        $command = "cd {$this->remotePath} && git reset {$mode} {$target}";

        return $this->execute($command, false);
    }

    public function gitPull(string $remote = 'origin', ?string $branch = null): SshResultRecord
    {
        $command = "cd {$this->remotePath} && git pull {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }

        return $this->execute($command, false);
    }
}
