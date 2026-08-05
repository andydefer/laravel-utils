<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\LaravelUtils\Records\SshCommandResultRecord;

/**
 * Type-safe collection for SSH command result records.
 *
 * @extends AbstractTypedCollection<SshCommandResultRecord>
 */
final class SshCommandResultRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SshCommandResultRecord::class);
    }

    /**
     * Get only successful commands.
     */
    public function successful(): self
    {
        return $this->filter(fn (SshCommandResultRecord $record) => $record->success);
    }

    /**
     * Get only failed commands.
     */
    public function failed(): self
    {
        return $this->filter(fn (SshCommandResultRecord $record) => ! $record->success);
    }

    /**
     * Get all command strings.
     */
    public function getCommands(): array
    {
        return $this->map(fn (SshCommandResultRecord $record) => $record->command)->toArray();
    }

    /**
     * Check if any command failed.
     */
    public function hasFailures(): bool
    {
        return $this->failed()->isNotEmpty();
    }
}
