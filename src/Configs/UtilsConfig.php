<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Configs;

use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class UtilsConfig implements UtilsConfigInterface
{
    private const DEFAULT_REPOSITORIES = [];

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getRepositories(): array
    {
        $repositories = $this->config->get('utils.repositories', self::DEFAULT_REPOSITORIES);

        return is_array($repositories) ? $repositories : [];
    }
}
