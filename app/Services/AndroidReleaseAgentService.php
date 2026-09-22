<?php

namespace App\Services;

use App\Models\AndroidRelease;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

        $directory = storage_path('app/private/android-release-agent');
        File::ensureDirectoryExists($directory);
        $temporaryPath = $directory.'/'.bin2hex(random_bytes(16)).'.apk';

        try {
            $this->download($sourceUrl, $temporaryPath);

            return $this->persistRelease($temporaryPath, $data);
        } finally {
            File::delete($temporaryPath);
        }
    }

    public function startUpload(array $arguments): array
    {
        $this->ensureAllowed();

        $maxChunkSize = $this->maxChunkSize();
        $data = Validator::make($arguments, [
            'release_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'file_name' => ['required', 'string', 'max:255', 'regex:/\.apk$/i'],
            'version' => ['sometimes', 'nullable', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/', Rule::unique('android_releases', 'version')],
            'version_code' => ['sometimes', 'nullable', 'integer', 'min:1', Rule::unique('android_releases', 'version_code')],
            'size' => ['required', 'integer', 'min:4', 'max:'.self::MAX_APK_BYTES],
            'chunk_size' => ['required', 'integer', 'min:1', 'max:'.$maxChunkSize],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:2000'],
            'sha256' => ['required', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
        ])->validate();

        $expectedChunks = (int) ceil((int) $data['size'] / (int) $data['chunk_size']);
        if ((int) $data['total_chunks'] !== $expectedChunks) {
            throw new RuntimeException('total_chunks does not match Android release size and chunk_size.');
        }

        $this->purgeExpiredUploads();

        $uploadId = (string) Str::uuid();
        $directory = $this->uploadDirectory($uploadId);
        File::ensureDirectoryExists($directory.'/chunks');

        $metadata = [
            'upload_id' => $uploadId,
            'file_name' => basename((string) $data['file_name']),
            'release_notes' => isset($data['release_notes']) ? trim((string) $data['release_notes']) : null,
            'version' => isset($data['version']) && $data['version'] !== null ? (string) $data['version'] : null,
            'version_code' => isset($data['version_code']) && $data['version_code'] !== null ? (int) $data['version_code'] : null,
            'size' => (int) $data['size'],
            'chunk_size' => (int) $data['chunk_size'],
            'total_chunks' => (int) $data['total_chunks'],
            'sha256' => strtolower((string) $data['sha256']),
            'created_at' => now()->toISOString(),
        ];

        File::put(
            $directory.'/metadata.json',
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        );

        return [
            'upload_id' => $uploadId,
            'chunk_size' => $metadata['chunk_size'],
            'total_chunks' => $metadata['total_chunks'],
            'max_upload_size' => self::MAX_APK_BYTES,
            'max_chunk_size' => $maxChunkSize,
            'expires_at' => now()->addSeconds($this->uploadTtl())->toISOString(),
        ];
    }

    public function uploadChunkFile(array $arguments, UploadedFile $chunk): array
    {
        $this->ensureAllowed();

        $data = Validator::make($arguments, [
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:1999'],
        ])->validate();

        $metadata = $this->metadata((string) $data['upload_id']);
        $index = (int) $data['chunk_index'];

        if ($index >= (int) $metadata['total_chunks']) {
            throw new RuntimeException('chunk_index is outside this Android release upload.');
        }

        $size = (int) $chunk->getSize();
        if ($size < 1 || $size > $this->maxChunkSize()) {
            throw new RuntimeException('Android release chunk size is outside the configured limit.');
        }

        $expectedBytes = min(
            (int) $metadata['chunk_size'],
            (int) $metadata['size'] - ($index * (int) $metadata['chunk_size']),
        );

        if ($expectedBytes < 1 || $size !== $expectedBytes) {
            throw new RuntimeException('Android release chunk size does not match the upload manifest.');
        }

        $realPath = $chunk->getRealPath();
        if ($realPath === false || ! File::isFile($realPath)) {
            throw new RuntimeException('Android release chunk is missing its temporary file.');
        }

        $path = $this->uploadDirectory((string) $data['upload_id']).'/chunks/'.$index;
        if (! File::copy($realPath, $path)) {
            throw new RuntimeException('Could not persist Android release upload chunk.');
        }

        return [
            'upload_id' => (string) $data['upload_id'],
            'chunk_index' => $index,
            'received_bytes' => $size,
        ];
    }

    public function completeUpload(array $arguments): array
    {
        $this->ensureAllowed();

        $data = Validator::make($arguments, [
            'upload_id' => ['required', 'uuid'],
        ])->validate();

        $uploadId = (string) $data['upload_id'];
        $metadata = $this->metadata($uploadId);
        $directory = $this->uploadDirectory($uploadId);
        $assembled = $directory.'/assembled.apk';

        $target = fopen($assembled, 'wb');
        if (! is_resource($target)) {
            throw new RuntimeException('Could not create assembled Android release.');
        }

        $hash = hash_init('sha256');
        $written = 0;

        try {
            for ($index = 0; $index < (int) $metadata['total_chunks']; $index++) {
                $chunkPath = $directory.'/chunks/'.$index;
                if (! File::isFile($chunkPath)) {
                    throw new RuntimeException("Android release upload chunk {$index} is missing.");
                }

                $chunk = File::get($chunkPath);
                $expectedBytes = min(
                    (int) $metadata['chunk_size'],
                    (int) $metadata['size'] - ($index * (int) $metadata['chunk_size']),
                );

                if (strlen($chunk) !== $expectedBytes) {
                    throw new RuntimeException("Android release upload chunk {$index} has an invalid size.");
                }

                $result = fwrite($target, $chunk);
                if ($result === false || $result !== strlen($chunk)) {
                    throw new RuntimeException('Could not assemble Android release upload.');
                }

                hash_update($hash, $chunk);
                $written += $result;
            }
        } finally {
            fclose($target);
        }

        if ($written !== (int) $metadata['size'] || File::size($assembled) !== (int) $metadata['size']) {
            throw new RuntimeException('Assembled Android release size does not match the upload manifest.');
        }

        $actualHash = strtolower(hash_final($hash));
        if (! hash_equals((string) $metadata['sha256'], $actualHash)) {
            throw new RuntimeException('SHA-256 verification failed for Android release upload.');
        }

        try {
            return $this->persistRelease($assembled, $metadata);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function abortUpload(array $arguments): array
    {
        $data = Validator::make($arguments, [
            'upload_id' => ['required', 'uuid'],
        ])->validate();

        $directory = $this->uploadDirectory((string) $data['upload_id']);
        $existed = File::isDirectory($directory);
        File::deleteDirectory($directory);

        return [
            'upload_id' => (string) $data['upload_id'],
            'aborted' => true,
            'existed' => $existed,
        ];
    }

    private function persistRelease(string $path, array $data): array
    {
        if (! File::isFile($path)) {
            throw new RuntimeException('فایل APK برای انتشار پیدا نشد.');
        }

        $size = (int) File::size($path);
        if ($size < 4 || $size > self::MAX_APK_BYTES) {
            throw new RuntimeException('حجم APK معتبر نیست یا از سقف ۱ گیگابایت بیشتر است.');
        }

        $header = (string) file_get_contents($path, false, null, 0, 4);
        if (! in_array($header, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
            throw new RuntimeException('فایل ساختار APK/ZIP معتبر ندارد.');
        }

        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum) || $checksum === '') {
            throw new RuntimeException('محاسبه checksum فایل APK ناموفق بود.');
        }
        $checksum = strtolower($checksum);

        $expectedChecksum = strtolower(trim((string) ($data['sha256'] ?? '')));
        if ($expectedChecksum !== '' && ! hash_equals($expectedChecksum, $checksum)) {
            throw new RuntimeException('checksum فایل APK با مقدار مورد انتظار یکسان نیست.');
        }

        $next = AndroidRelease::nextVersion();
        $version = filled($data['version'] ?? null) ? (string) $data['version'] : (string) $next['version'];
        $versionCode = filled($data['version_code'] ?? null) ? (int) $data['version_code'] : (int) $next['version_code'];
        $fileName = trim((string) ($data['file_name'] ?? '')) ?: 'PlayNexus-Android-'.$version.'.apk';

        Validator::make([
            'version' => $version,
            'version_code' => $versionCode,
            'file_name' => $fileName,
        ], [
            'version' => ['required', 'string', 'max:32', 'regex:/^\d+\.\d+\.\d+$/', Rule::unique('android_releases', 'version')],
            'version_code' => ['required', 'integer', 'min:1', Rule::unique('android_releases', 'version_code')],
            'file_name' => ['required', 'string', 'max:255', 'regex:/\.apk$/i'],
        ])->validate();

        $storedDisk = $this->storageDisk();
        $storedPath = 'android-releases/playnexus-'.$version.'.apk';
        $stream = fopen($path, 'rb');

        if ($stream === false) {
            throw new RuntimeException('امکان خواندن APK وجود ندارد.');
        }

        try {
            $stored = Storage::disk($storedDisk)->put($storedPath, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException('ذخیره APK روی فضای دانلود PlayNexus ناموفق بود.');
        }

        try {
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
                    'checksum_sha256' => $checksum,
                    'release_notes' => $data['release_notes'] ?? null,
                    'is_active' => true,
                    'released_by' => $authorId,
                    'released_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            try {
                Storage::disk($storedDisk)->delete($storedPath);
            } catch (Throwable) {
                // Preserve the original database failure.
            }

            throw $exception;
        }

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

    private function metadata(string $uploadId): array
    {
        $path = $this->uploadDirectory($uploadId).'/metadata.json';
        if (! File::isFile($path)) {
            throw new RuntimeException('Android release upload session was not found or has expired.');
        }

        try {
            $metadata = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Android release upload metadata is corrupted.');
        }

        if (! is_array($metadata)) {
            throw new RuntimeException('Android release upload metadata is invalid.');
        }

        return $metadata;
    }

    private function uploadDirectory(string $uploadId): string
    {
        return storage_path('app/private/android-release-uploads/'.$uploadId);
    }

    private function purgeExpiredUploads(): void
    {
        $root = storage_path('app/private/android-release-uploads');
        if (! File::isDirectory($root)) {
            return;
        }

        $cutoff = time() - $this->uploadTtl();
        foreach (File::directories($root) as $directory) {
            $modified = @filemtime($directory);
            if ($modified !== false && $modified < $cutoff) {
                File::deleteDirectory($directory);
            }
        }
    }

    private function maxChunkSize(): int
    {
        return max(1, min(2097152, (int) config('content_agent.uploads.max_chunk_size', 2097152)));
    }

    private function uploadTtl(): int
    {
        return max(300, (int) config('content_agent.uploads.ttl_seconds', 86400));
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
