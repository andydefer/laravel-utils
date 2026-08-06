<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Utilities;

use AndyDefer\PhpServices\Contracts\FileSystemInterface;
use Illuminate\Support\Collection;

final class FileFinderUtility
{
    /**
     * Find all video files in a directory recursively or get a single file.
     *
     * @param  string  $source  Source directory or file path
     * @param  array<string>  $extensions  List of allowed extensions
     * @return Collection<string>
     */
    public static function findVideos(
        string $source,
        array $extensions,
        FileSystemInterface $fileSystem
    ): Collection {
        $files = [];

        if ($fileSystem->isFile($source)) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if (in_array($extension, $extensions, true)) {
                $files[] = $source;
            }
        } elseif ($fileSystem->isDirectory($source)) {
            $files = self::findFilesRecursively($source, $extensions, $fileSystem);
        }

        return collect($files);
    }

    /**
     * Find all image files in a directory recursively or get a single file.
     *
     * @param  string  $source  Source directory or file path
     * @param  array<string>  $extensions  List of allowed extensions
     * @return Collection<string>
     */
    public static function findImages(
        string $source,
        array $extensions,
        FileSystemInterface $fileSystem
    ): Collection {
        $files = [];

        if ($fileSystem->isFile($source)) {
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if (in_array($extension, $extensions, true)) {
                $files[] = $source;
            }
        } elseif ($fileSystem->isDirectory($source)) {
            $files = self::findFilesRecursively($source, $extensions, $fileSystem);
        }

        return collect($files);
    }

    /**
     * Find all files with given extensions in a directory recursively.
     *
     * @param  string  $directory  Directory to scan
     * @param  array<string>  $extensions  List of allowed extensions
     * @return array<string>
     */
    public static function findFilesRecursively(
        string $directory,
        array $extensions,
        FileSystemInterface $fileSystem
    ): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (in_array($extension, $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Get relative path from source.
     */
    public static function getRelativePath(string $file, string $source): string
    {
        $normalizedSource = self::normalizePath($source);
        $normalizedFile = self::normalizePath($file);

        if (str_starts_with($normalizedFile, $normalizedSource.'/')) {
            return ltrim(substr($normalizedFile, strlen($normalizedSource) + 1), '/');
        }

        return basename($file);
    }

    /**
     * Normalize path (convert backslashes to forward slashes).
     */
    public static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Ensure source exists.
     *
     * @throws \RuntimeException If source does not exist
     */
    public static function ensureSourceExists(string $source, FileSystemInterface $fileSystem): void
    {
        if (! $fileSystem->exists($source)) {
            throw new \RuntimeException("Source not found: {$source}");
        }
    }

    /**
     * Ensure destination exists, create if not.
     */
    public static function ensureDestinationExists(string $destination, FileSystemInterface $fileSystem): void
    {
        if (! $fileSystem->exists($destination)) {
            $fileSystem->ensureDirectoryExists($destination);
        }
    }
}
