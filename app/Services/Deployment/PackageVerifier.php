<?php

namespace App\Services\Deployment;

use RuntimeException;
use ZipArchive;

final class PackageVerifier
{
    public function verify(string $archive, ?string $staging = null): array
    {
        $this->guardConfiguration();
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('افزونه ZIP روی PHP فعال نیست.');
        }
        if (! is_file($archive) || filesize($archive) > (int) config('deployment.max_package_size')) {
            throw new RuntimeException('بسته وجود ندارد یا بزرگ‌تر از حد مجاز است.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($archive);
        if (! in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            throw new RuntimeException('نوع واقعی فایل ZIP معتبر نیست.');
        }

        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('ساختار ZIP قابل خواندن نیست.');
        }
        try {
            if ($zip->numFiles > (int) config('deployment.max_entries')) {
                throw new RuntimeException('تعداد فایل‌های بسته بیشتر از حد مجاز است.');
            }
            $entries = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i, ZipArchive::FL_UNCHANGED);
                $name = $this->safeName((string) $stat['name']);
                $total += (int) $stat['size'];
                if ($total > (int) config('deployment.max_extracted_size')) {
                    throw new RuntimeException('حجم استخراج‌شده بسته بیشتر از حد مجاز است.');
                }
                $compressed = max(1, (int) $stat['comp_size']);
                if ((int) $stat['size'] / $compressed > (float) config('deployment.max_compression_ratio')) {
                    throw new RuntimeException("نسبت فشرده‌سازی مشکوک است: {$name}");
                }
                $mode = ((int) ($stat['external_attributes'] ?? 0) >> 16) & 0170000;
                if (in_array($mode, [0120000, 010000], true) && $mode === 0120000) {
                    throw new RuntimeException("لینک داخل ZIP مجاز نیست: {$name}");
                }
                if (! str_ends_with($name, '/')) {
                    $this->assertAllowed($name);
                    $entries[$name] = ['size' => (int) $stat['size']];
                }
            }
            foreach (['deployment-manifest.json', 'deployment-manifest.sig'] as $required) {
                if (! isset($entries[$required])) throw new RuntimeException("{$required} در بسته نیست.");
            }
            $manifestRaw = $zip->getFromName('deployment-manifest.json');
            $signature = trim((string) $zip->getFromName('deployment-manifest.sig'));
            if ($manifestRaw === false || ! hash_equals(hash_hmac('sha256', $manifestRaw, (string) config('deployment.signing_key')), $signature)) {
                throw new RuntimeException('امضای بسته معتبر نیست.');
            }
            $manifest = json_decode($manifestRaw, true, flags: JSON_THROW_ON_ERROR);
            if (($manifest['app_id'] ?? null) !== config('deployment.app_id') || ($manifest['protocol_version'] ?? null) !== config('deployment.protocol_version')) {
                throw new RuntimeException('شناسه برنامه یا نسخه پروتکل بسته سازگار نیست.');
            }
            $listed = collect($manifest['files'] ?? [])->keyBy('path');
            $extras = array_diff(array_keys($entries), array_merge($listed->keys()->all(), ['deployment-manifest.json', 'deployment-manifest.sig']));
            if ($extras !== []) throw new RuntimeException('ZIP دارای فایل ثبت‌نشده در manifest است: '.reset($extras));
            foreach ($listed as $path => $expected) {
                $path = $this->safeName((string) $path);
                $stream = $zip->getStream($path);
                if (! is_resource($stream)) throw new RuntimeException("فایل manifest در ZIP نیست: {$path}");
                $hash = hash_init('sha256');
                hash_update_stream($hash, $stream);
                fclose($stream);
                if (! hash_equals((string) ($expected['sha256'] ?? ''), hash_final($hash))) {
                    throw new RuntimeException("checksum فایل معتبر نیست: {$path}");
                }
            }
            if ($staging) $this->extract($zip, $listed->keys()->all(), $staging);
            return $manifest + ['archive_size' => filesize($archive), 'extracted_size' => $total];
        } finally {
            $zip->close();
        }
    }

    private function extract(ZipArchive $zip, array $files, string $staging): void
    {
        if (! is_dir($staging) && ! mkdir($staging, 0750, true) && ! is_dir($staging)) throw new RuntimeException('ساخت staging ممکن نیست.');
        foreach (array_merge($files, ['deployment-manifest.json', 'deployment-manifest.sig']) as $name) {
            $name = $this->safeName($name);
            $target = $staging.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
            if (! is_dir(dirname($target))) mkdir(dirname($target), 0750, true);
            $source = $zip->getStream($name); $destination = fopen($target, 'wb');
            if (! is_resource($source) || ! is_resource($destination)) throw new RuntimeException("استخراج {$name} ممکن نیست.");
            stream_copy_to_stream($source, $destination); fclose($source); fclose($destination);
        }
    }

    private function safeName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '://') || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name) || in_array('..', explode('/', $name), true)) {
            throw new RuntimeException("مسیر ناامن در ZIP: {$name}");
        }
        return ltrim($name, './');
    }

    private function assertAllowed(string $name): void
    {
        if (in_array($name, config('deployment.allowed_files'), true)) return;
        foreach (config('deployment.allowed_roots') as $root) if ($name === $root || str_starts_with($name, $root.'/')) return;
        throw new RuntimeException("مسیر خارج از allowlist است: {$name}");
    }

    private function guardConfiguration(): void
    {
        if (! config('deployment.app_id') || strlen((string) config('deployment.signing_key')) < 32) {
            throw new RuntimeException('DEPLOYMENT_APP_ID و کلید امضای حداقل ۳۲ کاراکتری باید تنظیم شوند.');
        }
    }
}
