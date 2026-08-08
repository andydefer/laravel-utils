<?php

declare(strict_types=1);

namespace AndyDefer\LaravelUtils\Services;

use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\StorageKit\Storage\JsonlStorage;

final class ExportTrackerService
{
    private JsonlStorage $storage;

    private FileSystemService $fileSystem;

    private string $basePath;

    private string $projectRoot;

    public function __construct(string $basePath, int $ttl = 0)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->projectRoot = getcwd();
        $this->storage = new JsonlStorage($this->basePath, $ttl);
        $this->fileSystem = new FileSystemService;
    }

    public function generateHash(string $filePath): string
    {
        if (str_starts_with($filePath, '/') || str_starts_with($filePath, 'C:')) {
            $fullPath = $filePath;
        } else {
            $fullPath = $this->projectRoot.'/'.$filePath;
        }

        if (! $this->fileSystem->exists($fullPath)) {
            return md5($filePath);
        }

        return md5_file($fullPath);
    }

    public function isExported(string $filePath): bool
    {
        $hash = $this->generateHash($filePath);

        return $this->storage->exists($hash);
    }

    public function markAsExported(string $filePath): void
    {
        $hash = $this->generateHash($filePath);
        $this->storage->set($hash, [
            'file' => $filePath,
            'exported_at' => date('Y-m-d H:i:s'),
            'hash' => $hash,
        ]);
    }

    public function markMultipleAsExported(array $filePaths): void
    {
        $items = [];
        foreach ($filePaths as $path) {
            $hash = $this->generateHash($path);
            $items[$hash] = [
                'file' => $path,
                'exported_at' => date('Y-m-d H:i:s'),
                'hash' => $hash,
            ];
        }
        $this->storage->setMultiple($items);
    }

    public function remove(string $filePath): bool
    {
        $hash = $this->generateHash($filePath);

        return $this->storage->delete($hash);
    }

    public function removeMultiple(array $filePaths): void
    {
        $hashes = [];
        foreach ($filePaths as $path) {
            $hashes[] = $this->generateHash($path);
        }
        $this->storage->deleteMultiple($hashes);
    }

    public function filterAlreadyExported(array $filePaths): array
    {
        $toExport = [];
        foreach ($filePaths as $path) {
            if (! $this->isExported($path)) {
                $toExport[] = $path;
            }
        }

        return $toExport;
    }

    public function cleanExpired(): int
    {
        return $this->storage->cleanExpired();
    }

    public function clear(): void
    {
        $this->storage->clear();
    }

    public function getStats(): array
    {
        return [
            'base_path' => $this->basePath,
            'ttl' => $this->storage->getTTL(),
        ];
    }
}
