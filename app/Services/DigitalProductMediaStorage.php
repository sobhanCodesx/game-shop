<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;

final class DigitalProductMediaStorage
{
    public static function diskName(): string
    {
        return ProductMediaStorage::diskName();
    }

    public static function disk(): FilesystemAdapter
    {
        return ProductMediaStorage::disk();
    }

    public static function url(?string $path): ?string
    {
        return ProductMediaStorage::url($path);
    }

    public static function exists(?string $path): bool
    {
        return ProductMediaStorage::exists($path);
    }

    public static function delete(string|array|null $paths): void
    {
        ProductMediaStorage::delete($paths);
    }
}
