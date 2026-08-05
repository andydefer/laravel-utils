<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Records\GitResultRecord;
use Symfony\Component\Process\Process;

/**
 * Service for executing Git commands.
 *
 * Ce service utilise des Records pour retourner les résultats
 * de manière typée et structurée.
 */
final class GitService
{
    private ?string $repositoryPath = null;

    private int $timeout = 300;

    private bool $verbose = false;

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

    /**
     * Execute a Git command.
     */
    public function execute(string $command): GitResultRecord
    {
        $fullCommand = 'git '.$command;

        if (! empty($this->repositoryPath)) {
            $fullCommand = 'cd '.escapeshellarg($this->repositoryPath).' && '.$fullCommand;
        }

        $process = Process::fromShellCommandline($fullCommand);
        $process->setTimeout($this->timeout);

        $output = '';
        $error = '';

        $process->run(function ($type, $buffer) use (&$output, &$error) {
            if ($type === Process::OUT) {
                $output .= $buffer;
            } else {
                $error .= $buffer;
            }
        });

        $exitCode = ExitCode::tryFrom($process->getExitCode()) ?? ExitCode::FAILURE;

        return GitResultRecord::from([
            'success' => $process->isSuccessful(),
            'output' => trim($output),
            'error' => trim($error),
            'exit_code' => $exitCode,
            'command' => $command,
        ]);
    }

    /**
     * Fetch from remote repository.
     */
    public function fetch(string $remote = 'origin', ?string $branch = null): GitResultRecord
    {
        $command = "fetch {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }

        return $this->execute($command);
    }

    /**
     * Reset to a specific commit.
     */
    public function reset(string $target, bool $hard = true): GitResultRecord
    {
        $mode = $hard ? '--hard' : '--soft';

        return $this->execute("reset {$mode} {$target}");
    }

    /**
     * Pull from remote repository.
     */
    public function pull(string $remote = 'origin', ?string $branch = null): GitResultRecord
    {
        $command = "pull {$remote}";
        if ($branch) {
            $command .= " {$branch}";
        }

        return $this->execute($command);
    }

    /**
     * Get the current branch name.
     */
    public function getCurrentBranch(): ?string
    {
        $result = $this->execute('branch --show-current');

        return $result->success ? $result->output : null;
    }

    /**
     * Get the latest tag.
     */
    public function getLatestTag(): ?string
    {
        $result = $this->execute('describe --tags --abbrev=0 2>/dev/null || echo ""');

        return $result->success && ! empty($result->output) ? $result->output : null;
    }

    /**
     * Create a tag.
     */
    public function createTag(string $tagName, ?string $message = null): GitResultRecord
    {
        $command = "tag {$tagName}";
        if ($message) {
            $command .= ' -m '.escapeshellarg($message);
        }

        return $this->execute($command);
    }

    /**
     * Push to remote repository.
     */
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

    /**
     * Check if the repository is clean (no uncommitted changes).
     */
    public function isClean(): bool
    {
        $result = $this->execute('status --porcelain');

        return $result->success && empty($result->output);
    }

    /**
     * Get the current commit hash.
     */
    public function getCurrentCommit(): ?string
    {
        $result = $this->execute('rev-parse HEAD');

        return $result->success ? $result->output : null;
    }

    /**
     * Check if the repository exists.
     */
    public function repositoryExists(): bool
    {
        if (empty($this->repositoryPath)) {
            return false;
        }

        $result = $this->execute('rev-parse --git-dir 2>/dev/null');

        return $result->success && ! empty($result->output);
    }

    /**
     * Clone a repository.
     */
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
