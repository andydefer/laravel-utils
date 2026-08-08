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

    /**
     * Get the source directory for publishing directives.
     */
    public function getPublishSourcePath(): string;

    /**
     * Get the target directory for publishing directives.
     */
    public function getPublishTargetPath(): string;

    /**
     * Get the list of assets to export.
     *
     * @return array<string>
     */
    public function getExportAssets(): array;

    /**
     * Get the pipelines configuration.
     *
     * @return array<int, string>
     */
    public function getPipelines(): array;

    /**
     * Get the binary path for executing directives (e.g., 'bin/afya' or 'bin/ut').
     *
     * @return string The binary path relative to the project root
     */
    public function getBinaryPath(): string;

    /**
     * Get the export tracker base path.
     *
     * @return string The base path for export tracker storage
     */
    public function getExportTrackerBasePath(): string;

    /**
     * Get the export tracker TTL in seconds (0 = infinite).
     *
     * @return int TTL in seconds, 0 means never expire
     */
    public function getExportTrackerTTL(): int;
}
