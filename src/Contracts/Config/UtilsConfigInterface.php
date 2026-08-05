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

    /**
     * Get the list of default file extensions for git diff.
     *
     * @return array<string> List of default extensions
     */
    public function getDefaultExtensions(): array;

    /**
     * Get the list of configured extension recipes.
     *
     * @return array<string, array<string>> Recipe name => list of extensions
     */
    public function getExtensionRecipes(): array;

    /**
     * Get the deployment configuration.
     *
     * @return array{ssh_key: string, remote_path: string, git_branch: string}
     */
    public function getDeploymentConfig(): array;
}
