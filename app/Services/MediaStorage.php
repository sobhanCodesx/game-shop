<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class MediaStorage
{
    public static function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('media.disk', 'public'));
    }

    public static function isExternalUrl(?string $path): bool
    {
        return is_string($path) && preg_match('#^https?://#i', trim($path)) === 1;
    }

    public static function normalizePath(?string $path): ?string
    {
        if (! is_string($path)) {
            return null;
        }

        $path = trim($path);
        if ($path === '' || self::isExternalUrl($path)) {
            return $path === '' ? null : $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        // Historical records occasionally stored the public URL prefix in the
        // database even though filesystem paths are relative to the disk root.
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return $path !== '' ? $path : null;
    }

    public static function url(?string $path): ?string
    {
        $path = self::normalizePath($path);
        if ($path === null) {
            return null;
        }

        if (self::isExternalUrl($path)) {
            return $path;
        }

        // Serve internal media through the application gateway. This removes
        // the public/storage symlink and CDN base URL as single points of
        // failure, while MediaStreamController can still redirect non-local
        // assets to their configured origin when appropriate.
        if (app()->bound('router') && app('router')->has('media.stream')) {
            return route('media.stream', ['path' => $path], false);
        }

        $disk = (string) config('media.disk', 'public');
        $baseUrl = config("filesystems.disks.{$disk}.url");
        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        return self::disk()->url($path);
    }
}
