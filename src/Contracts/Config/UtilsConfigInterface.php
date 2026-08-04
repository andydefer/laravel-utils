<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Contracts\Config;

interface UtilsConfigInterface
{
    /**
     * Get the list of configured git repositories.
     *
     * @return array<string, string> Repository alias => URL
     */
    public function getRepositories(): array;
}
