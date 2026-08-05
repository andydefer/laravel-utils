<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Records;

use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelUtils\Collections\SshCommandResultRecordCollection;

/**
 * Record representing the result of multiple SSH commands execution.
 */
final class SshBatchResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly string $error,
        public readonly ExitCode $exit_code,
        public readonly SshCommandResultRecordCollection $results,

    ) {}
}
