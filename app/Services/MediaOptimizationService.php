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
    public function videoMetadata(string $videoPath, string $directory): array
    {
        $binary = (string) config('media.video.ffmpeg_binary');
        if (! is_file($binary) || ! MediaStorage::disk()->exists($videoPath)) {
            return ['thumbnail' => null, 'duration' => null];
        }

        $videoTemporary = tempnam(sys_get_temp_dir(), 'nexus-video-');
        if (! $videoTemporary) {
            return ['thumbnail' => null, 'duration' => null];
        }
        $read = MediaStorage::disk()->readStream($videoPath);
        $write = fopen($videoTemporary, 'wb');
        if (! is_resource($read) || ! is_resource($write)) {
            @unlink($videoTemporary);
            return ['thumbnail' => null, 'duration' => null];
        }
        stream_copy_to_stream($read, $write);
        fclose($read);
        fclose($write);

        $temporary = tempnam(sys_get_temp_dir(), 'nexus-thumb-');
        if (! $temporary) {
            return ['thumbnail' => null, 'duration' => null];
        }
        @unlink($temporary);
        $temporary .= '.jpg';

        $process = new Process([
            $binary, '-y', '-ss', '00:00:01', '-i', $videoTemporary,
            '-frames:v', '1', '-vf', 'scale=640:-2', '-q:v', '3', $temporary,
        ]);
        $process->setTimeout(120)->run();
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
        @unlink($videoTemporary);

        return compact('thumbnail', 'duration');
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        if ($file->getMimeType() === 'image/gif') {
            return $file->store($directory, (string) config('media.disk', 'public'));
        }

        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
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

    private function storeVideo(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, (string) config('media.disk', 'public'));
    }
}
