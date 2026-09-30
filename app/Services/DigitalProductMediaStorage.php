<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DigitalProductMediaStorage
{
    public static function diskName(): string
    {
        return (string) config('digital_media.disk', 'downloads');
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
        $baseUrl = config('filesystems.disks.'.self::diskName().'.url');

        // URL generation must not open an FTP connection on storefront requests.
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
            return false;
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
                // Best effort. A stale file must never make product editing fail.
            }

            try {
                MediaStorage::disk()->delete($path);
            } catch (Throwable) {
                // Legacy FTP can be temporarily unavailable; do not fail the edit.
            }
        }
    }
}
