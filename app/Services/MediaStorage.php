<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class MediaStorage
{
    public static function disk(): FilesystemAdapter
    {
        return Storage::disk((string) config('media.disk', 'downloads'));
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');

        // Older PlayNexus assets may still live in public/storage. Prefer a real
        // legacy local file when it exists so routing new media to the download
        // host never makes existing content disappear.
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
            // Fall through to the configured canonical media disk.
        }

        $disk = (string) config('media.disk', 'downloads');
        $baseUrl = config("filesystems.disks.{$disk}.url");

        // Building a public URL must not open an FTP connection. Public pages
        // should remain available even when the storage server is unreachable.
        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        return self::disk()->url($path);
    }
}
