<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

/**
 * Wrapper for shell command execution.
 * Allows easy mocking in tests.
 */
class ShellCommandExecutor
{
    /**
     * Execute a shell command and return the output and exit code.
     *
     * @param  string  $command  The command to execute
     * @return array{output: string, exit_code: int}
     */
    public function execute(string $command): array
    {
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        return [
            'output' => implode("\n", $output),
            'exit_code' => $exitCode,
        ];
    }
}
