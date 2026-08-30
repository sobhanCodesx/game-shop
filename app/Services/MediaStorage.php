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

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = ltrim($path, '/');
        $disk = (string) config('media.disk', 'public');
        $baseUrl = config("filesystems.disks.{$disk}.url");

        // Building a public URL must not open an FTP connection. Public pages
        // should remain available even when the storage server is unreachable.
        if (is_string($baseUrl) && $baseUrl !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        return self::disk()->url($path);
    }
}
