<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

class MediaOptimizationService
{
    /** @return array{path: string, type: 'image'|'video'} */
    public function store(UploadedFile $file, string $directory): array
    {
        if (str_starts_with($this->mimeType($file), 'video/')) {
            return ['path' => $this->storeVideo($file, $directory), 'type' => 'video'];
        }

        return ['path' => $this->storeImage($file, $directory), 'type' => 'image'];
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        $mime = $this->mimeType($file);
        if ($mime === 'image/gif') {
            return $file->store($directory, (string) config('media.disk', 'public'));
        }

        $dimensions = @getimagesize($file->getRealPath());
        if (! $dimensions) {
            throw new RuntimeException('فایل تصویر قابل پردازش نیست.');
        }

        [$width, $height] = $dimensions;
        if (! $this->supportsGd($mime) || ! $this->hasEnoughMemoryForGd($file, $width, $height)) {
            return $this->storeLargeImage($file, $directory);
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };
        if (! $source) {
            throw new RuntimeException('فایل تصویر قابل پردازش نیست.');
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
            $source = match ($orientation) {
                3 => imagerotate($source, 180, 0),
                6 => imagerotate($source, -90, 0),
                8 => imagerotate($source, 90, 0),
                default => $source,
            };
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(
            1,
            (int) config('media.image.max_width', 1600) / $width,
            (int) config('media.image.max_height', 1600) / $height,
        );
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $temporary = tempnam(sys_get_temp_dir(), 'nexus-image-');
        if (! $temporary || ! imagewebp($canvas, $temporary, (int) config('media.image.webp_quality', 82))) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('ذخیره نسخه بهینه تصویر انجام نشد.');
        }

        imagedestroy($source);
        imagedestroy($canvas);

        $path = trim($directory, '/').'/'.Str::uuid().'.webp';
        MediaStorage::disk()->put($path, fopen($temporary, 'rb'));
        @unlink($temporary);

        return $path;
    }

    private function hasEnoughMemoryForGd(UploadedFile $file, int $width, int $height): bool
    {
        $limit = $this->memoryLimitBytes();
        if ($limit === null) {
            return true;
        }

        $targetWidthLimit = (int) config('media.image.max_width', 1600);
        $targetHeightLimit = (int) config('media.image.max_height', 1600);
        $scale = min(1, $targetWidthLimit / max(1, $width), $targetHeightLimit / max(1, $height));
        $targetPixels = (int) ceil($width * $scale) * (int) ceil($height * $scale);

        // GD keeps an uncompressed source bitmap in memory. JPEG orientation
        // may temporarily create a second full-size bitmap during rotation.
        $sourceCopies = $this->mimeType($file) === 'image/jpeg' ? 2 : 1;
        $estimated = ($width * $height * 4 * $sourceCopies)
            + ($targetPixels * 4)
            + max(16 * 1024 * 1024, $file->getSize() * 2);
        $available = $limit - memory_get_usage(true) - (16 * 1024 * 1024);

        return $estimated < $available;
    }

    private function memoryLimitBytes(): ?int
    {
        $value = trim((string) ini_get('memory_limit'));
        if ($value === '' || $value === '-1') {
            return null;
        }

        $unit = strtolower(substr($value, -1));
        $amount = (float) $value;
        $multiplier = match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return (int) floor($amount * $multiplier);
    }

    private function storeLargeImage(UploadedFile $file, string $directory): string
    {
        $extension = match ($this->mimeType($file)) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('فرمت تصویر پشتیبانی نمی‌شود.'),
        };

        return $file->storeAs(
            trim($directory, '/'),
            Str::uuid().'.'.$extension,
            (string) config('media.disk', 'public'),
        );
    }

    private function storeVideo(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, (string) config('media.disk', 'public'));
        if (! is_string($path) || $path === '') {
            throw new RuntimeException('ذخیره فایل ویدیو روی فضای رسانه انجام نشد. دسترسی نوشتن فضای ذخیره‌سازی را بررسی کنید.');
        }

        return $path;
    }

    private function mimeType(UploadedFile $file): string
    {
        $detected = (string) $file->getMimeType();
        if (str_starts_with($detected, 'image/') || str_starts_with($detected, 'video/')) {
            return $detected;
        }

        return (string) $file->getClientMimeType();
    }

    private function supportsGd(string $mime): bool
    {
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
            return false;
        }

        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg'),
            'image/png' => function_exists('imagecreatefrompng'),
            'image/webp' => function_exists('imagecreatefromwebp'),
            default => false,
        };
    }
}
