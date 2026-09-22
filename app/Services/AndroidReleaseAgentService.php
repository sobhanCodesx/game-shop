<?php

namespace App\Services;

use App\Models\AndroidRelease;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

final class AndroidReleaseAgentService
{
    private const MAX_APK_BYTES = 1073741824;

    private const ALLOWED_SOURCE_HOSTS = [
        'media.githubusercontent.com',
        'release-assets.githubusercontent.com',
        'objects.githubusercontent.com',
    ];

    public function publish(array $arguments): array
    {
        $this->ensureAllowed();

        $data = Validator::make($arguments, [
            'source_url' => ['required', 'url:https', 'max:2048'],
            'release_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'file_name' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/\.apk$/i'],
            'version' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/', Rule::unique('android_releases', 'version')],
            'version_code' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::unique('android_releases', 'version_code')],
            'sha256' => ['sometimes', 'nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ])->validate();

        $sourceUrl = (string) $data['source_url'];
        $this->validateSourceUrl($sourceUrl);

        $next = AndroidRelease::nextVersion();
        $version = (string) ($data['version'] ?? $next['version']);
        $versionCode = (int) ($data['version_code'] ?? $next['version_code']);
        $fileName = trim((string) ($data['file_name'] ?? '')) ?: 'PlayNexus-Android-'.$version.'.apk';

        $directory = storage_path('app/private/android-release-agent');
        File::ensureDirectoryExists($directory);
        $temporaryPath = $directory.'/'.bin2hex(random_bytes(16)).'.apk';

        $storedDisk = null;
        $storedPath = null;

        try {
            $this->download($sourceUrl, $temporaryPath);

            $size = (int) File::size($temporaryPath);
            if ($size < 4 || $size > self::MAX_APK_BYTES) {
                throw new RuntimeException('حجم APK معتبر نیست یا از سقف ۱ گیگابایت بیشتر است.');
            }

            $header = (string) file_get_contents($temporaryPath, false, null, 0, 4);
            if (! in_array($header, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
                throw new RuntimeException('فایل دانلودشده ساختار APK/ZIP معتبر ندارد.');
            }

            $checksum = hash_file('sha256', $temporaryPath);
            if (! is_string($checksum) || $checksum === '') {
                throw new RuntimeException('محاسبه checksum فایل APK ناموفق بود.');
            }

            $expectedChecksum = strtolower(trim((string) ($data['sha256'] ?? '')));
            if ($expectedChecksum !== '' && ! hash_equals($expectedChecksum, strtolower($checksum))) {
                throw new RuntimeException('checksum فایل APK با مقدار مورد انتظار یکسان نیست.');
            }

            $storedDisk = $this->storageDisk();
            $storedPath = 'android-releases/playnexus-'.$version.'.apk';
            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('امکان خواندن APK دانلودشده وجود ندارد.');
            }

            try {
                $stored = Storage::disk($storedDisk)->put($storedPath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException('ذخیره APK روی فضای دانلود PlayNexus ناموفق بود.');
            }

            $authorId = $this->authorUserId();

            $release = DB::transaction(function () use (
                $version,
                $versionCode,
                $storedDisk,
                $storedPath,
                $fileName,
                $size,
                $checksum,
                $data,
                $authorId,
            ) {
                AndroidRelease::query()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                return AndroidRelease::query()->create([
                    'version' => $version,
                    'version_code' => $versionCode,
                    'disk' => $storedDisk,
                    'file_path' => $storedPath,
                    'file_name' => $fileName,
                    'file_size' => $size,
                    'checksum_sha256' => strtolower($checksum),
                    'release_notes' => $data['release_notes'] ?? null,
                    'is_active' => true,
                    'released_by' => $authorId,
                    'released_at' => now(),
                ]);
            });

            Cache::forget('android.latest-release.v1');

            return [
                'id' => $release->id,
                'version' => $release->version,
                'version_code' => $release->version_code,
                'file_name' => $release->file_name,
                'file_size' => $release->file_size,
                'checksum_sha256' => $release->checksum_sha256,
                'release_notes' => $release->release_notes,
                'is_active' => $release->is_active,
                'released_at' => $release->released_at?->toISOString(),
                'download_url' => $release->directUrl(),
                'public_download_url' => route('android.apk.download'),
            ];
        } catch (Throwable $exception) {
            if ($storedDisk && $storedPath) {
                try {
                    Storage::disk($storedDisk)->delete($storedPath);
                } catch (Throwable) {
                    // Preserve the original failure.
                }
            }

            throw $exception;
        } finally {
            File::delete($temporaryPath);
        }
    }

    private function download(string $sourceUrl, string $temporaryPath): void
    {
        $response = Http::connectTimeout(20)
            ->timeout(900)
            ->retry(2, 1000, throw: false)
            ->withOptions([
                'allow_redirects' => false,
                'sink' => $temporaryPath,
                'progress' => static function (
                    int $downloadTotal,
                    int $downloadedBytes,
                    int $uploadTotal,
                    int $uploadedBytes,
                ): void {
                    if ($downloadedBytes > self::MAX_APK_BYTES) {
                        throw new RuntimeException('دانلود APK از سقف مجاز ۱ گیگابایت عبور کرد.');
                    }
                },
            ])
            ->get($sourceUrl);

        if (! $response->successful()) {
            throw new RuntimeException('دریافت APK از منبع امن با HTTP '.$response->status().' ناموفق بود.');
        }

        if (! File::isFile($temporaryPath)) {
            throw new RuntimeException('فایل APK پس از دانلود روی سرور ساخته نشد.');
        }
    }

    private function validateSourceUrl(string $sourceUrl): void
    {
        $parts = parse_url($sourceUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        if ($scheme !== 'https' || ! in_array($host, self::ALLOWED_SOURCE_HOSTS, true)) {
            throw new RuntimeException('منبع APK باید HTTPS و از میزبان امن GitHub باشد.');
        }

        if (! str_ends_with($path, '.apk')) {
            throw new RuntimeException('آدرس منبع باید مستقیماً به فایل APK ختم شود.');
        }
    }

    private function ensureAllowed(): void
    {
        if (! filter_var(config('content_agent.allow_uploads'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Android release uploads are disabled by server configuration.');
        }

        if (! filter_var(config('content_agent.allow_publish'), FILTER_VALIDATE_BOOL)) {
            throw new RuntimeException('Android release publishing is disabled by server configuration.');
        }
    }

    private function storageDisk(): string
    {
        $downloads = (array) config('filesystems.disks.downloads', []);

        if (
            filled($downloads['url'] ?? null)
            && filled($downloads['host'] ?? null)
            && filled($downloads['username'] ?? null)
        ) {
            return 'downloads';
        }

        return 'public';
    }

    private function authorUserId(): ?int
    {
        $authorId = (int) config('content_agent.author_user_id');

        if ($authorId < 1 || ! User::query()->whereKey($authorId)->exists()) {
            return null;
        }

        return $authorId;
    }
}
