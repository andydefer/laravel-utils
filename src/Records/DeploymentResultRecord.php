<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Record representing the result of a deployment operation.
 */
final class DeploymentResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $error = null,
        public readonly ?float $duration = null,
        public readonly ?GitResultRecord $fetch_result = null,
        public readonly ?GitResultRecord $reset_result = null,
        public readonly ?StringTypedCollection $commands_executed = null,
    ) {}
}
