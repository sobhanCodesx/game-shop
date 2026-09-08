<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class MediaOptimizationService
{
    /** @return array{path: string, type: 'image'|'video'} */
    public function store(UploadedFile $file, string $directory): array
    {
        if (str_starts_with((string) $file->getMimeType(), 'video/')) {
            return ['path' => $this->storeVideo($file, $directory), 'type' => 'video'];
        }

        return ['path' => $this->storeImage($file, $directory), 'type' => 'image'];
    }

    /** @return array{thumbnail: ?string, duration: ?int} */
    public function videoMetadata(UploadedFile|string $video, string $directory): array
    {
        $binary = (string) config('media.video.ffmpeg_binary');
        if (! is_file($binary)) {
            return ['thumbnail' => null, 'duration' => null];
        }

        $videoTemporary = null;
        $videoSource = $video instanceof UploadedFile ? $video->getRealPath() : null;
        if (! is_string($videoSource) || ! is_file($videoSource)) {
            if (! is_string($video) || ! MediaStorage::disk()->exists($video)) {
                return ['thumbnail' => null, 'duration' => null];
            }

            $videoTemporary = tempnam(sys_get_temp_dir(), 'nexus-video-');
            if (! $videoTemporary) {
                return ['thumbnail' => null, 'duration' => null];
            }
            $read = MediaStorage::disk()->readStream($video);
            $write = fopen($videoTemporary, 'wb');
            if (! is_resource($read) || ! is_resource($write)) {
                if (is_resource($read)) {
                    fclose($read);
                }
                if (is_resource($write)) {
                    fclose($write);
                }
                @unlink($videoTemporary);

                return ['thumbnail' => null, 'duration' => null];
            }
            stream_copy_to_stream($read, $write);
            fclose($read);
            fclose($write);
            $videoSource = $videoTemporary;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'nexus-thumb-');
        if (! $temporary) {
            if ($videoTemporary) {
                @unlink($videoTemporary);
            }

            return ['thumbnail' => null, 'duration' => null];
        }
        @unlink($temporary);
        $temporary .= '.jpg';

        $process = new Process([
            $binary, '-y', '-ss', '00:00:01', '-i', $videoSource,
            '-frames:v', '1', '-vf', 'scale=640:-2', '-q:v', '3', $temporary,
        ]);
        try {
            $process->setTimeout(120)->run();
        } catch (\Throwable) {
            @unlink($temporary);
            if ($videoTemporary) {
                @unlink($videoTemporary);
            }

            return ['thumbnail' => null, 'duration' => null];
        }
        preg_match('/Duration:\s*(\d{2}):(\d{2}):(\d{2}(?:\.\d+)?)/', $process->getErrorOutput(), $matches);
        $duration = $matches
            ? (int) round(((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (float) $matches[3])
            : null;
        $thumbnail = null;

        if ($process->isSuccessful() && is_file($temporary) && filesize($temporary) > 0) {
            $thumbnail = trim($directory, '/').'/'.Str::uuid().'.jpg';
            MediaStorage::disk()->put($thumbnail, fopen($temporary, 'rb'));
        }
        @unlink($temporary);
        if ($videoTemporary) {
            @unlink($videoTemporary);
        }

        return compact('thumbnail', 'duration');
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        if ($file->getMimeType() === 'image/gif') {
            return $file->store($directory, (string) config('media.disk', 'public'));
        }

        $dimensions = @getimagesize($file->getRealPath());
        if (! $dimensions) {
            throw new RuntimeException('فایل تصویر قابل پردازش نیست.');
        }

        [$width, $height] = $dimensions;
        if (! $this->hasEnoughMemoryForGd($file, $width, $height)) {
            return $this->storeLargeImage($file, $directory);
        }

        $source = match ($file->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };
        if (! $source) {
            throw new RuntimeException('فایل تصویر قابل پردازش نیست.');
        }

        if ($file->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
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
        $sourceCopies = $file->getMimeType() === 'image/jpeg' ? 2 : 1;
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
        $temporary = tempnam(sys_get_temp_dir(), 'nexus-large-image-');
        $binary = (string) config('media.video.ffmpeg_binary');

        if ($temporary && is_file($binary)) {
            @unlink($temporary);
            $temporary .= '.webp';
            $process = new Process([
                $binary,
                '-y',
                '-i',
                $file->getRealPath(),
                '-vf',
                sprintf(
                    'scale=%d:%d:force_original_aspect_ratio=decrease',
                    (int) config('media.image.max_width', 1600),
                    (int) config('media.image.max_height', 1600),
                ),
                '-frames:v',
                '1',
                '-quality',
                (string) config('media.image.webp_quality', 82),
                $temporary,
            ]);

            try {
                $process->setTimeout(120)->run();
                if ($process->isSuccessful() && is_file($temporary) && filesize($temporary) > 0) {
                    $path = trim($directory, '/').'/'.Str::uuid().'.webp';
                    MediaStorage::disk()->put($path, fopen($temporary, 'rb'));
                    @unlink($temporary);

                    return $path;
                }
            } catch (\Throwable) {
                // Fall through to stream storage; never decode a risky image in PHP.
            }

            @unlink($temporary);
        } elseif ($temporary) {
            @unlink($temporary);
        }

        $extension = match ($file->getMimeType()) {
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
        return $file->store($directory, (string) config('media.disk', 'public'));
    }
}
