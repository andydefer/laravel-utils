<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Configs;

use AndyDefer\Directive\Helpers\Paths;
use AndyDefer\LaravelUtils\Contracts\Config\UtilsConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use InvalidArgumentException;

final class UtilsConfig implements UtilsConfigInterface
{
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

    private const DEFAULT_PUBLISH_SOURCE = 'app/Directives';

    private const DEFAULT_PUBLISH_TARGET = 'app/Directives';

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function getRepositories(): array
    {
        $repositories = $this->config->get('utils.repositories');

        if (! is_array($repositories) || empty($repositories)) {
            throw new InvalidArgumentException(
                'No repositories configured. Please add at least one repository in config/utils.php under "repositories" key.'
            );
        }

        return $repositories;
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
        $deployment = $this->config->get('utils.deployment');

        if (! is_array($deployment) || empty($deployment)) {
            throw new InvalidArgumentException(
                'No deployment configuration found. Please configure "deployment" in config/utils.php with ssh_key, remote_path, and git_branch.'
            );
        }

        $requiredKeys = ['ssh_key', 'remote_path', 'git_branch'];
        $missingKeys = [];

        foreach ($requiredKeys as $key) {
            if (! isset($deployment[$key]) || empty($deployment[$key])) {
                $missingKeys[] = $key;
            }
        }

        if (! empty($missingKeys)) {
            throw new InvalidArgumentException(
                'Deployment configuration is missing required keys: '.implode(', ', $missingKeys).
                '. Please configure them in config/utils.php under "deployment".'
            );
        }

        return $deployment;
    }

    public function getPublishSourcePath(): string
    {
        $source = $this->config->get('utils.publish_source', self::DEFAULT_PUBLISH_SOURCE);

        return str_replace('laravel-directive', 'laravel-utils', Paths::packageRoot().'/'.$source);
    }

    public function getPublishTargetPath(): string
    {
        $target = $this->config->get('utils.publish_target', self::DEFAULT_PUBLISH_TARGET);

        return Paths::projectRoot().'/'.$target;
    }
}
