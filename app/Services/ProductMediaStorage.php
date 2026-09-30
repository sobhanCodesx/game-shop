<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProductMediaStorage
{
    public static function diskName(): string
    {
        return (string) config('product_media.disk', 'public');
    }

    public static function disk(): FilesystemAdapter
    {
        return Storage::disk(self::diskName());
    }

    public static function url(?string $path): ?string
    {
        $path = MediaStorage::normalizePath($path);
        if ($path === null) {
            return null;
        }

        if (MediaStorage::isExternalUrl($path)) {
            return $path;
        }

        if (app()->bound('router') && app('router')->has('media.stream')) {
            return route('media.stream', ['path' => $path], false);
        }

        $baseUrl = config('filesystems.disks.'.self::diskName().'.url');
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        return self::disk()->url($path);
    }

    public static function exists(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        $path = MediaStorage::normalizePath((string) $path);
        if ($path === null || MediaStorage::isExternalUrl($path)) {
            return false;
        }

        try {
            if (self::disk()->exists($path)) {
                return true;
            }
        } catch (Throwable) {
            // Fall through to the legacy media disk.
        }

        $legacyDisk = (string) config('media.disk', 'public');
        if ($legacyDisk === self::diskName()) {
            return false;
        }

        try {
            return Storage::disk($legacyDisk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public static function delete(string|array|null $paths): void
    {
        $paths = array_values(array_filter(
            is_array($paths) ? $paths : [$paths],
            fn ($path) => is_string($path) && filled($path) && ! MediaStorage::isExternalUrl($path),
        ));

        if ($paths === []) {
            return;
        }

        $paths = array_values(array_filter(array_map(
            fn (string $path) => MediaStorage::normalizePath($path),
            $paths,
        )));

        foreach (array_unique([self::diskName(), (string) config('media.disk', 'public')]) as $disk) {
            try {
                Storage::disk($disk)->delete($paths);
            } catch (Throwable) {
                // Deletion is best effort across current and legacy media disks.
            }
        }
    }
}
