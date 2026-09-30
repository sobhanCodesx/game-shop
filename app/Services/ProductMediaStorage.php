<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ProductMediaStorage
{
    public static function diskName(): string
    {
        return (string) config('product_media.disk', 'downloads');
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

        // Product media created before the download-host migration may still
        // exist in public/storage. Serve that exact file when present.
        try {
            $legacy = Storage::disk('public');
            if ($legacy->exists($path)) {
                $legacyUrl = config('filesystems.disks.public.url');

                if (is_string($legacyUrl) && $legacyUrl !== '') {
                    return rtrim($legacyUrl, '/').'/'.$path;
                }

                return $legacy->url($path);
            }
        } catch (Throwable) {
            // Fall through to the canonical download host.
        }

        $baseUrl = config('filesystems.disks.'.self::diskName().'.url');

        // Public URL generation must not open an FTP connection.
        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        return self::disk()->url($path);
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
            if (Storage::disk('public')->exists($path)) {
                return true;
            }
        } catch (Throwable) {
            // Ignore an unavailable legacy public disk.
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
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    continue;
                }
            } catch (Throwable) {
                // Ignore a missing/unavailable legacy public disk.
            }

            try {
                MediaStorage::disk()->delete($path);
            } catch (Throwable) {
                // Canonical remote media can be unavailable temporarily.
            }
        }
    }
}
