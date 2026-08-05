<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Records;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Traits\Hydratable;

/**
 * Record representing the result of a single command in a batch execution.
 */
final class SshCommandResultRecord extends AbstractRecord
{
    use Hydratable;

    public function __construct(
        public readonly string $command,
        public readonly bool $success,
        public readonly string $output,
        public readonly string $error,
        public readonly ExitCode $exit_code,
    ) {}
}
