<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Configs;

use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class UtilsConfig implements UtilsConfigInterface
{
    private const DEFAULT_REPOSITORIES = [];

    private const DEFAULT_EXTENSIONS = [
        'php',
        'js',
        'ts',
        'tsx',
        'jsx',
        'vue',
        'css',
        'scss',
        'sass',
        'less',
        'html',
        'xml',
        'json',
        'yaml',
        'yml',
        'md',
        'txt',
        'sh',
        'bash',
        'zsh',
        'fish',
        'py',
        'rb',
        'go',
        'rs',
        'java',
        'c',
        'cpp',
        'h',
        'hpp',
    ];

    private const DEFAULT_RECIPES = [
        'frontend' => [
            'js',
            'ts',
            'tsx',
            'jsx',
            'vue',
            'css',
            'scss',
            'sass',
            'less',
            'html',
            'xml',
        ],
        'backend' => [
            'php',
            'py',
            'rb',
            'go',
            'rs',
            'java',
            'c',
            'cpp',
            'h',
            'hpp',
        ],
    ];

    private const DEFAULT_DEPLOYMENT = [
        'ssh_key' => 'o2switch',
        'remote_path' => '~/sites/laravel-utils.com',
        'git_branch' => 'master',
    ];

    private const DEFAULT_PUBLISH_SOURCE = 'app/Directives';

    private const DEFAULT_PUBLISH_TARGET = 'app/Directives';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getRepositories(): array
    {
        $repositories = $this->config->get('utils.repositories', self::DEFAULT_REPOSITORIES);

        return is_array($repositories) ? $repositories : [];
    }

    public function getDefaultExtensions(): array
    {
        $extensions = $this->config->get('utils.default_extensions', self::DEFAULT_EXTENSIONS);

        return is_array($extensions) ? $extensions : self::DEFAULT_EXTENSIONS;
    }

    public function getExtensionRecipes(): array
    {
        $recipes = $this->config->get('utils.extension_recipes', self::DEFAULT_RECIPES);

        return is_array($recipes) ? $recipes : self::DEFAULT_RECIPES;
    }

    public function getDeploymentConfig(): array
    {
        $deployment = $this->config->get('utils.deployment', self::DEFAULT_DEPLOYMENT);

        return is_array($deployment) ? array_merge(self::DEFAULT_DEPLOYMENT, $deployment) : self::DEFAULT_DEPLOYMENT;
    }

    public function getPublishSourcePath(): string
    {
        $source = $this->config->get('utils.publish_source', self::DEFAULT_PUBLISH_SOURCE);

        return Paths::packageRoot().'/'.$source;
    }

    public function getPublishTargetPath(): string
    {
        $target = $this->config->get('utils.publish_target', self::DEFAULT_PUBLISH_TARGET);

        return Paths::projectRoot().'/'.$target;
    }
}
