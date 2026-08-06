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
     * Get the HLS configuration.
     *
     * @return array{segment_duration: int, crf: int, preset: string, audio_bitrate: string, resolutions: array<string>}
     */
    public function getHlsConfig(): array;

    /**
     * Get the video compression configuration.
     *
     * @return array{width: int, height: int, crf: int, preset: string, video_codec: string, audio_codec: string, audio_bitrate: string, pixel_format: string}
     */
    public function getVideoCompressConfig(): array;

    /**
     * Get the image compression configuration.
     *
     * @return array{png_quality: string, jpg_quality: int, max_size: int, strip_meta: bool}
     */
    public function getImageCompressConfig(): array;
}
