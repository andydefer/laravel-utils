<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Tests\Fixtures\Directives;

use AndyDefer\Directive\AbstractDirective;
use AndyDefer\Directive\Enums\ExitCode;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;

/**
 * Simple test directive that responds with pong.
 */
final class PingDirective extends AbstractDirective
{
    public function getSignature(): string
    {
        return 'ping {delay=0}';
    }

    public function getDescription(): string
    {
        return 'Respond with pong';
    }

    public function getAliases(): StringTypedCollection
    {
        return StringTypedCollection::from(['pong']);
    }

    protected function execute(): ExitCode
    {
        $delay = (int) ($this->getArgument('delay') ?? 0);

        if ($delay > 0) {
            usleep($delay * 1000000);
        }

        $this->line('pong');

        return ExitCode::SUCCESS;
    }
}
