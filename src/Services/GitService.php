<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Records\GitResultRecord;

final class GitService
{
    private ?string $repositoryPath = null;

    private int $timeout = 300;

    private bool $verbose = false;

    public function __construct(
        private GitCommandExecutor $executor
    ) {}

    public function repositoryPath(string $path): self
    {
        $this->repositoryPath = $path;

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

    public function execute(string $command): GitResultRecord
    {
        $fullCommand = 'git '.$command;

        if (! empty($this->repositoryPath)) {
            $fullCommand = 'cd '.$this->repositoryPath.' && '.$fullCommand;
        }

        $result = $this->executor->execute($fullCommand);

        $exitCode = ExitCode::tryFrom($result['exit_code']) ?? ExitCode::FAILURE;

        return GitResultRecord::from([
            'success' => $result['exit_code'] === 0,
            'output' => trim($result['output']),
            'error' => $result['exit_code'] !== 0 ? trim($result['output']) : '',
            'exit_code' => $exitCode,
            'command' => $command,
        ]);
    }

    public function fetch(string $remote = 'origin', ?string $branch = null): GitResultRecord
    {
        $command = "fetch {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }

        return $this->execute($command);
    }

    public function reset(string $target, bool $hard = true): GitResultRecord
    {
        $mode = $hard ? '--hard' : '--soft';

        return $this->execute("reset {$mode} {$target}");
    }

    public function pull(string $remote = 'origin', ?string $branch = null): GitResultRecord
    {
        $command = "pull {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }

        return $this->execute($command);
    }

    public function getCurrentBranch(): ?string
    {
        $result = $this->execute('branch --show-current');

        return $result->success ? $result->output : null;
    }

    public function getLatestTag(): ?string
    {
        $result = $this->execute('describe --tags --abbrev=0 2>/dev/null || echo ""');

        return $result->success && ! empty($result->output) ? $result->output : null;
    }

    public function createTag(string $tagName, ?string $message = null): GitResultRecord
    {
        $command = "tag {$tagName}";
        if ($message) {
            $command .= ' -m '.escapeshellarg($message);
        }

        return $this->execute($command);
    }

    public function push(string $remote = 'origin', ?string $branch = null, bool $force = false, bool $tags = false): GitResultRecord
    {
        $command = "push {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }
        if ($force) {
            $command .= ' --force-with-lease';
        }
        if ($tags) {
            $command .= ' --tags';
        }

        return $this->execute($command);
    }

    public function isClean(): bool
    {
        $result = $this->execute('status --porcelain');

        return $result->success && empty($result->output);
    }

    public function getCurrentCommit(): ?string
    {
        $result = $this->execute('rev-parse HEAD');

        return $result->success ? $result->output : null;
    }

    public function repositoryExists(): bool
    {
        if (empty($this->repositoryPath)) {
            return false;
        }
        $result = $this->execute('rev-parse --git-dir 2>/dev/null');

        return $result->success && ! empty($result->output);
    }

    public function clone(string $url, ?string $path = null, ?string $branch = null): GitResultRecord
    {
        $command = "clone {$url}";
        if ($path) {
            $command .= ' '.escapeshellarg($path);
        }
        if ($branch) {
            $command .= " --branch {$branch}";
        }

        $originalPath = $this->repositoryPath;
        $this->repositoryPath = null;

        $result = $this->execute($command);

        $this->repositoryPath = $originalPath;

        return $result;
    }
}
