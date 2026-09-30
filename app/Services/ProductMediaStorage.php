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
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '') {
            return null;
        }

        // Product media is intentionally served through the application
        // gateway. Shared hosting does not guarantee that public/storage can
        // be created as a symlink (exec may be disabled), while the gateway
        // can read storage/app/public directly and still fall back to legacy
        // remote media.
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
        if (! is_string($path) || trim($path) === '') {
            return false;
        }

        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if ($path === '' || preg_match('#^https?://#i', $path) === 1) {
            return false;
        }

        try {
            if (self::disk()->exists($path)) {
                return true;
            }
        } catch (Throwable) {
            // Continue with the legacy disk fallback below.
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
        $paths = is_array($paths) ? $paths : [$paths];

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '' || preg_match('#^https?://#i', trim($path)) === 1) {
                continue;
            }

            $path = ltrim(str_replace('\\', '/', trim($path)), '/');
            if (str_starts_with($path, 'storage/')) {
                $path = substr($path, strlen('storage/'));
            }

            if ($path === '') {
                continue;
            }

            foreach (array_unique([self::diskName(), (string) config('media.disk', 'public')]) as $disk) {
                try {
                    Storage::disk($disk)->delete($path);
                } catch (Throwable) {
                    // Best effort. A stale product file must never break editing.
                }
            }
        }
    }
}
