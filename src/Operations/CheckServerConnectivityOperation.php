<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Operations;

use AndyDefer\LaravelUtils\Services\SshService;

final class CheckServerConnectivityOperation
{
    public static function handle(SshService $sshService, string $sshKey, string $remotePath, bool $dryRun = false): bool
    {
        if ($dryRun) {
            return true;
        }

        if (! $sshService->isReachable()) {
            return false;
        }

        if (! $sshService->remotePathExists()) {
            return false;
        }

        return true;
    }
}
