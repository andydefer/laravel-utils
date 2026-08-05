<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\LaravelUtils\Collections\SshCommandResultRecordCollection;
use AndyDefer\LaravelUtils\Records\SshBatchResultRecord;
use AndyDefer\LaravelUtils\Records\SshCommandResultRecord;
use AndyDefer\LaravelUtils\Records\SshResultRecord;
use Symfony\Component\Process\Process;

/**
 * Service for executing SSH commands on remote servers.
 *
 * Ce service utilise des Records pour retourner les résultats
 * de manière typée et structurée.
 */
final class SshService
{
    private ?string $sshKey = null;

    private ?string $remotePath = null;

    private int $timeout = 300;

    private bool $verbose = false;

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

    /**
     * Execute a command on the remote server.
     */
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

        return SshResultRecord::from([
            'success' => $process->isSuccessful(),
            'output' => trim($output),
            'error' => trim($error),
            'exit_code' => $exitCode,
            'command' => $command,
        ]);
    }

    /**
     * Execute multiple commands on the remote server.
     */
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

    /**
     * Check if the remote server is reachable.
     */
    public function isReachable(): bool
    {
        if (empty($this->sshKey)) {
            return false;
        }

        $result = $this->execute('echo "OK"', false);

        return $result->success && str_contains($result->output, 'OK');
    }

    /**
     * Check if the remote path exists.
     */
    public function remotePathExists(): bool
    {
        if (empty($this->remotePath)) {
            return false;
        }

        $result = $this->execute('test -d '.escapeshellarg($this->remotePath).' && echo "EXISTS"', false);

        return $result->success && str_contains($result->output, 'EXISTS');
    }

    /**
     * Build the full SSH command.
     */
    private function buildCommand(string $command, bool $changeDir): string
    {
        $fullCommand = $command;

        if ($changeDir && ! empty($this->remotePath)) {
            $fullCommand = 'cd '.escapeshellarg($this->remotePath).' && '.$fullCommand;
        }

        return "ssh {$this->sshKey} ".escapeshellarg($fullCommand);
    }
}
