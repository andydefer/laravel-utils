<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

class GitCommandExecutor
{
    public function execute(string $command): array
    {
        $output = shell_exec($command.' 2>&1');

        $tempCommand = $command.'; echo "EXIT_CODE:$?"';
        $tempOutput = shell_exec($tempCommand.' 2>&1');
        $returnCode = 0;

        if (preg_match('/EXIT_CODE:(\d+)/', $tempOutput ?? '', $matches)) {
            $returnCode = (int) $matches[1];
        }

        return [
            'output' => trim($output ?? ''),
            'exit_code' => $returnCode,
        ];
    }
}
