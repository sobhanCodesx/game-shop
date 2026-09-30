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
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');

        try {
            if (self::disk()->exists($path)) {
                $baseUrl = config('filesystems.disks.'.self::diskName().'.url');

                if (is_string($baseUrl) && $baseUrl !== '') {
                    return rtrim($baseUrl, '/').'/'.$path;
                }

                return self::disk()->url($path);
            }
        } catch (Throwable) {
            // Fall through to the legacy media disk without breaking storefront rendering.
        }

        return MediaStorage::url($path);
    }

    public static function exists(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        try {
            if (self::disk()->exists($path)) {
                return true;
            }
        } catch (Throwable) {
            // Continue with the legacy disk fallback below.
        }

        try {
            return MediaStorage::disk()->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public static function delete(string|array|null $paths): void
    {
        $paths = array_values(array_filter((array) $paths));

        foreach ($paths as $path) {
            try {
                if (self::disk()->exists($path)) {
                    self::disk()->delete($path);
                    continue;
                }
            } catch (Throwable) {
                // Best effort. A stale product file must never break editing.
            }

            try {
                MediaStorage::disk()->delete($path);
            } catch (Throwable) {
                // Legacy remote media can be unavailable temporarily.
            }
        }
    }
}
