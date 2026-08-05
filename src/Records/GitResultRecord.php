<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Records;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Record representing the result of a Git command execution.
 */
final class GitResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly string $error,
        public readonly ExitCode $exit_code,
        public readonly ?string $command = null,
    ) {}
}
