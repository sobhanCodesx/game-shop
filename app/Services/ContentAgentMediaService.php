<?php

namespace App\Services;

use App\Models\ContentAsset;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Throwable;

class ContentAgentMediaService
{
    private const RESOURCES = ['game', 'studio', 'platform', 'collection', 'feed', 'story', 'video', 'product'];

    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const VIDEO_MIMES = ['video/mp4', 'video/webm', 'video/quicktime'];

    public function __construct(private readonly MediaOptimizationService $optimizer)
    {
    }

    public function startUpload(array $arguments): array
    {
        $this->ensureUploadsAllowed();

        $maxSize = $this->maxUploadSize();
        $maxChunkSize = $this->maxChunkSize();

        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
            'slot' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['required', 'string', 'max:120'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$maxSize],
            'chunk_size' => ['required', 'integer', 'min:1', 'max:'.$maxChunkSize],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:1000'],
            'sha256' => ['sometimes', 'nullable', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/i'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $resource = (string) $data['resource'];
        $id = (int) $data['id'];
        $slot = (string) $data['slot'];

        $this->resolveTarget($resource, $id);
        $this->ensureValidSlot($resource, $slot);

        $expectedChunks = (int) ceil((int) $data['size'] / (int) $data['chunk_size']);
        if ((int) $data['total_chunks'] !== $expectedChunks) {
            throw new RuntimeException('total_chunks does not match size and chunk_size.');
        }

        $this->purgeExpiredUploads();

        $uploadId = (string) Str::uuid();
        $directory = $this->uploadDirectory($uploadId);
        File::ensureDirectoryExists($directory.'/chunks');

        $metadata = [
            'upload_id' => $uploadId,
            'resource' => $resource,
            'id' => $id,
            'slot' => $slot,
            'name' => basename((string) $data['name']),
            'mime' => strtolower(trim((string) $data['mime'])),
            'size' => (int) $data['size'],
            'chunk_size' => (int) $data['chunk_size'],
            'total_chunks' => (int) $data['total_chunks'],
            'sha256' => isset($data['sha256']) && $data['sha256'] !== null ? strtolower((string) $data['sha256']) : null,
            'alt' => isset($data['alt']) ? trim((string) $data['alt']) : null,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            'duration' => isset($data['duration']) ? (int) $data['duration'] : null,
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
            'max_upload_size' => $maxSize,
            'expires_at' => now()->addSeconds($this->uploadTtl())->toISOString(),
        ];
    }

    public function uploadChunk(array $arguments): array
    {
        $this->ensureUploadsAllowed();

        $data = $this->validate($arguments, [
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:999'],
            'data_base64' => ['required', 'string'],
        ]);

        $metadata = $this->metadata((string) $data['upload_id']);
        $index = (int) $data['chunk_index'];

        if ($index >= (int) $metadata['total_chunks']) {
            throw new RuntimeException('chunk_index is outside this upload.');
        }

        $encoded = (string) $data['data_base64'];
        $maxEncodedLength = (int) ceil($this->maxChunkSize() * 4 / 3) + 16;
        if (strlen($encoded) > $maxEncodedLength) {
            throw new RuntimeException('Encoded chunk is larger than the configured limit.');
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new RuntimeException('Chunk data is not valid Base64.');
        }

        $expectedBytes = min(
            (int) $metadata['chunk_size'],
            (int) $metadata['size'] - ($index * (int) $metadata['chunk_size']),
        );

        if ($expectedBytes < 1 || strlen($decoded) !== $expectedBytes) {
            throw new RuntimeException('Chunk size does not match the upload manifest.');
        }

        $path = $this->uploadDirectory((string) $data['upload_id']).'/chunks/'.$index;
        File::put($path, $decoded);

        return [
            'upload_id' => (string) $data['upload_id'],
            'chunk_index' => $index,
            'received_bytes' => strlen($decoded),
        ];
    }

    public function uploadChunkFile(array $arguments, UploadedFile $chunk): array
    {
        $this->ensureUploadsAllowed();

        $data = $this->validate($arguments, [
            'upload_id' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $metadata = $this->metadata((string) $data['upload_id']);
        $index = (int) $data['chunk_index'];

        if ($index >= (int) $metadata['total_chunks']) {
            throw new RuntimeException('chunk_index is outside this upload.');
        }

        $size = (int) $chunk->getSize();
        if ($size < 1 || $size > $this->maxChunkSize()) {
            throw new RuntimeException('Binary chunk size is outside the configured limit.');
        }

        $expectedBytes = min(
            (int) $metadata['chunk_size'],
            (int) $metadata['size'] - ($index * (int) $metadata['chunk_size']),
        );

        if ($expectedBytes < 1 || $size !== $expectedBytes) {
            throw new RuntimeException('Binary chunk size does not match the upload manifest.');
        }

        $realPath = $chunk->getRealPath();
        if ($realPath === false || ! File::isFile($realPath)) {
            throw new RuntimeException('Binary chunk upload is missing its temporary file.');
        }

        $path = $this->uploadDirectory((string) $data['upload_id']).'/chunks/'.$index;
        if (! File::copy($realPath, $path)) {
            throw new RuntimeException('Could not persist binary upload chunk.');
        }

        return [
            'upload_id' => (string) $data['upload_id'],
            'chunk_index' => $index,
            'received_bytes' => $size,
        ];
    }

    public function completeUpload(array $arguments): array
    {
        $this->ensureUploadsAllowed();

        $data = $this->validate($arguments, [
            'upload_id' => ['required', 'uuid'],
        ]);

        $uploadId = (string) $data['upload_id'];
        $metadata = $this->metadata($uploadId);
        $directory = $this->uploadDirectory($uploadId);
        $assembled = $directory.'/assembled';

        $target = fopen($assembled, 'wb');
        if (! is_resource($target)) {
            throw new RuntimeException('Could not create the assembled upload.');
        }

        $hash = hash_init('sha256');
        $written = 0;

        try {
            for ($index = 0; $index < (int) $metadata['total_chunks']; $index++) {
                $chunkPath = $directory.'/chunks/'.$index;
                if (! File::isFile($chunkPath)) {
                    throw new RuntimeException("Upload chunk {$index} is missing.");
                }

                $chunk = File::get($chunkPath);
                $expectedBytes = min(
                    (int) $metadata['chunk_size'],
                    (int) $metadata['size'] - ($index * (int) $metadata['chunk_size']),
                );

                if (strlen($chunk) !== $expectedBytes) {
                    throw new RuntimeException("Upload chunk {$index} has an invalid size.");
                }

                $result = fwrite($target, $chunk);
                if ($result === false || $result !== strlen($chunk)) {
                    throw new RuntimeException('Could not assemble the uploaded file.');
                }

                hash_update($hash, $chunk);
                $written += $result;
            }
        } finally {
            fclose($target);
        }

        if ($written !== (int) $metadata['size'] || File::size($assembled) !== (int) $metadata['size']) {
            throw new RuntimeException('Assembled file size does not match the upload manifest.');
        }

        $actualHash = hash_final($hash);
        if (! empty($metadata['sha256']) && ! hash_equals((string) $metadata['sha256'], $actualHash)) {
            throw new RuntimeException('SHA-256 verification failed for the uploaded file.');
        }

        $file = new UploadedFile(
            $assembled,
            (string) $metadata['name'],
            (string) $metadata['mime'],
            null,
            true,
        );
        $actualMime = strtolower((string) ($file->getMimeType() ?: 'application/octet-stream'));

        $this->ensureMimeAllowedForSlot((string) $metadata['resource'], (string) $metadata['slot'], $actualMime);

        $asset = $this->attach($metadata, $file, $actualMime, $actualHash);
        File::deleteDirectory($directory);

        return [
            'upload_id' => $uploadId,
            'resource' => $metadata['resource'],
            'id' => (int) $metadata['id'],
            'slot' => $metadata['slot'],
            'sha256' => $actualHash,
            'asset' => $asset,
        ];
    }

    public function attachLocalFile(
        array $arguments,
        string $path,
    ): array {
        $this->ensureUploadsAllowed();

        if (! File::isFile($path)) {
            throw new RuntimeException('Local media file does not exist.');
        }

        $maxSize = $this->maxUploadSize();
        $actualSize = (int) File::size($path);
        if ($actualSize < 1 || $actualSize > $maxSize) {
            throw new RuntimeException(
                'Local media file size is outside the allowed upload range.',
            );
        }

        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
            'slot' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alt' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $resource = (string) $data['resource'];
        $id = (int) $data['id'];
        $slot = (string) $data['slot'];

        $this->resolveTarget($resource, $id);
        $this->ensureValidSlot($resource, $slot);

        $file = new UploadedFile(
            $path,
            basename((string) $data['name']),
            isset($data['mime']) && filled($data['mime'])
                ? (string) $data['mime']
                : null,
            null,
            true,
        );

        $actualMime = strtolower((string) ($file->getMimeType() ?: ($data['mime'] ?? 'application/octet-stream')));
        $this->ensureMimeAllowedForSlot($resource, $slot, $actualMime);

        $sha256 = hash_file('sha256', $path);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            throw new RuntimeException('Could not calculate media SHA-256.');
        }

        $metadata = [
            'resource' => $resource,
            'id' => $id,
            'slot' => $slot,
            'name' => basename((string) $data['name']),
            'mime' => $actualMime,
            'size' => $actualSize,
            'sha256' => $sha256,
            'alt' => isset($data['alt']) ? trim((string) $data['alt']) : null,
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : null,
            'duration' => isset($data['duration']) ? (int) $data['duration'] : null,
        ];

        return [
            'resource' => $resource,
            'id' => $id,
            'slot' => $slot,
            'sha256' => $sha256,
            'asset' => $this->attach($metadata, $file, $actualMime, $sha256),
        ];
    }

    public function abortUpload(array $arguments): array
    {
        $this->ensureUploadsAllowed();

        $data = $this->validate($arguments, [
            'upload_id' => ['required', 'uuid'],
        ]);

        $uploadId = (string) $data['upload_id'];
        File::deleteDirectory($this->uploadDirectory($uploadId));

        return ['upload_id' => $uploadId, 'aborted' => true];
    }

    public function listContentAssets(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $resource = (string) $data['resource'];
        $id = (int) $data['id'];
        $target = $this->resolveTarget($resource, $id);

        $slots = match ($resource) {
            'game' => array_values(array_filter([
                $this->directAsset('cover', 'image', $target->cover),
                $this->directAsset('background', 'image', $target->background),
            ])),
            'studio' => array_values(array_filter([
                $this->directAsset('logo', 'image', $target->logo),
                $this->directAsset('background', 'image', $target->background),
            ])),
            'platform' => array_values(array_filter([
                $this->directAsset('icon', 'image', $target->icon),
            ])),
            'collection' => array_values(array_filter([
                $this->directAsset('logo', 'image', $target->logo),
            ])),
            'feed' => $target->media()->get()->map(fn ($media) => [
                'id' => $media->id,
                'slot' => 'media',
                'kind' => $media->type,
                'path' => $media->path,
                'url' => MediaStorage::url($media->path),
                'thumbnail_url' => MediaStorage::url($media->thumbnail),
                'mime' => $media->mime,
                'alt' => $media->alt,
                'sort_order' => (int) $media->sort_order,
                'storage_exists' => filled($media->path) && MediaStorage::disk()->exists($media->path),
            ])->values()->all(),
            'story' => array_values(array_filter([
                $this->directAsset('media', $target->media_type ?: $this->kindFromMime($target->video_mime), $target->video_path, $target->video_mime),
                $this->directAsset('thumbnail', 'image', $target->thumbnail),
            ])),
            'video' => array_values(array_filter([
                $this->directAsset('video', 'video', $target->video_path, $target->video_mime),
                $this->directAsset('thumbnail', 'image', $target->thumbnail),
            ])),
            'product' => $target->media()->get()->map(fn ($media) => [
                'id' => $media->id,
                'slot' => 'media',
                'kind' => $media->type,
                'path' => $media->path,
                'url' => MediaStorage::url($media->path),
                'alt' => $media->alt,
                'sort_order' => (int) $media->sort_order,
                'is_primary' => (bool) $media->is_primary,
                'storage_exists' => filled($media->path) && MediaStorage::disk()->exists($media->path),
            ])->values()->all(),
        };

        $attachments = ContentAsset::query()
            ->where('resource', $resource)
            ->where('resource_id', $id)
            ->where('slot', 'attachment')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (ContentAsset $asset) => $this->serializeAttachment($asset))
            ->values()
            ->all();

        return [
            'resource' => $resource,
            'id' => $id,
            'slots' => $slots,
            'attachments' => $attachments,
        ];
    }

    public function removeContentAsset(array $arguments): array
    {
        $this->ensureDestructiveAllowed();

        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
            'slot' => ['required', 'string', 'max:32'],
            'asset_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $resource = (string) $data['resource'];
        $id = (int) $data['id'];
        $slot = (string) $data['slot'];
        $target = $this->resolveTarget($resource, $id);
        $this->ensureValidSlot($resource, $slot);

        if ($slot === 'attachment') {
            if (empty($data['asset_id'])) {
                throw new RuntimeException('asset_id is required when removing an attachment.');
            }

            $asset = ContentAsset::query()
                ->where('resource', $resource)
                ->where('resource_id', $id)
                ->whereKey((int) $data['asset_id'])
                ->firstOrFail();
            $path = $asset->path;
            $asset->delete();
            MediaStorage::disk()->delete($path);

            return ['resource' => $resource, 'id' => $id, 'slot' => $slot, 'asset_id' => (int) $data['asset_id'], 'removed' => true];
        }

        if (in_array($resource, ['feed', 'product'], true) && $slot === 'media') {
            if (empty($data['asset_id'])) {
                throw new RuntimeException('asset_id is required when removing list media.');
            }

            $media = $target->media()->whereKey((int) $data['asset_id'])->firstOrFail();
            $paths = $resource === 'feed'
                ? array_values(array_filter([$media->path, $media->thumbnail]))
                : array_values(array_filter([$media->path]));
            $wasPrimary = $resource === 'product' && (bool) $media->is_primary;
            $media->delete();
            MediaStorage::disk()->delete($paths);

            if ($wasPrimary) {
                $nextPrimary = $target->media()->where('type', 'image')->orderBy('sort_order')->orderBy('id')->first();
                if ($nextPrimary) {
                    $nextPrimary->update(['is_primary' => true]);
                }
            }

            return ['resource' => $resource, 'id' => $id, 'slot' => $slot, 'asset_id' => (int) $data['asset_id'], 'removed' => true];
        }

        $removedPath = match ($resource) {
            'game' => $this->removeDirectField($target, $slot, ['cover', 'background']),
            'studio' => $this->removeDirectField($target, $slot, ['logo', 'background']),
            'platform' => $this->removeDirectField($target, $slot, ['icon']),
            'collection' => $this->removeDirectField($target, $slot, ['logo']),
            'story' => $this->removeStorySlot($target, $slot),
            'video' => $this->removeVideoSlot($target, $slot),
            default => throw new RuntimeException('This slot requires an asset_id.'),
        };

        if ($removedPath) {
            MediaStorage::disk()->delete($removedPath);
        }

        return ['resource' => $resource, 'id' => $id, 'slot' => $slot, 'removed' => true];
    }

    public function deleteAttachmentsFor(string $resource, int $id): void
    {
        $assets = ContentAsset::query()
            ->where('resource', $resource)
            ->where('resource_id', $id)
            ->get();

        $paths = $assets->pluck('path')->filter()->values()->all();
        ContentAsset::query()
            ->where('resource', $resource)
            ->where('resource_id', $id)
            ->delete();

        if ($paths !== []) {
            MediaStorage::disk()->delete($paths);
        }
    }

    private function attach(array $metadata, UploadedFile $file, string $actualMime, string $sha256): array
    {
        $resource = (string) $metadata['resource'];
        $slot = (string) $metadata['slot'];
        $target = $this->resolveTarget($resource, (int) $metadata['id']);

        if ($slot === 'attachment') {
            return $this->storeAttachment($metadata, $file, $actualMime, $sha256);
        }

        return match ($resource) {
            'game' => $this->replaceImageField(
                $target,
                $slot,
                $slot === 'cover' ? 'games/covers' : 'games/backgrounds',
                $file,
                $actualMime,
            ),
            'studio' => $this->replaceImageField(
                $target,
                $slot,
                $slot === 'logo' ? 'studios/logos' : 'studios/backgrounds',
                $file,
                $actualMime,
            ),
            'platform' => $this->replaceImageField($target, 'icon', 'platforms/icons', $file, $actualMime),
            'collection' => $this->replaceImageField($target, 'logo', 'video-playlists/logos', $file, $actualMime),
            'feed' => $this->storeFeedMedia($target, $metadata, $file, $actualMime),
            'story' => $this->storeStoryMedia($target, $metadata, $file, $actualMime),
            'video' => $this->storeVideoMedia($target, $metadata, $file, $actualMime),
            'product' => $this->storeProductMedia($target, $metadata, $file, $actualMime),
        };
    }

    private function replaceImageField(
        Model $target,
        string $field,
        string $directory,
        UploadedFile $file,
        string $actualMime,
    ): array {
        $this->ensureImageMime($actualMime);

        $stored = $this->optimizer->store($file, $directory);
        $newPath = $stored['path'];
        $oldPath = $target->{$field};

        try {
            $target->{$field} = $newPath;
            $target->save();
        } catch (Throwable $exception) {
            MediaStorage::disk()->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            MediaStorage::disk()->delete($oldPath);
        }

        return $this->directAsset($field, 'image', $newPath, $actualMime) ?? [];
    }

    private function storeFeedMedia(
        SocialContent $feed,
        array $metadata,
        UploadedFile $file,
        string $actualMime,
    ): array {
        if (! in_array($actualMime, [...self::IMAGE_MIMES, ...self::VIDEO_MIMES], true)) {
            throw new RuntimeException('Feed media must be a supported image or video.');
        }

        $kind = str_starts_with($actualMime, 'video/') ? 'video' : 'image';
        if ($kind === 'image' && $file->getSize() > 8 * 1024 * 1024) {
            throw new RuntimeException('Feed images must be 8 MB or smaller.');
        }

        $dimensions = $kind === 'image' ? @getimagesize($file->getRealPath()) : null;
        $stored = $this->optimizer->store($file, 'feed');
        $sortOrder = $metadata['sort_order'] ?? ((int) $feed->media()->max('sort_order') + 1);

        try {
            $media = $feed->media()->create([
                'type' => $kind,
                'path' => $stored['path'],
                'thumbnail' => null,
                'mime' => $actualMime,
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
                'duration' => $metadata['duration'] ?? null,
                'alt' => filled($metadata['alt'] ?? null) ? trim((string) $metadata['alt']) : $feed->title,
                'sort_order' => (int) $sortOrder,
            ]);
        } catch (Throwable $exception) {
            MediaStorage::disk()->delete($stored['path']);
            throw $exception;
        }

        return [
            'id' => $media->id,
            'slot' => 'media',
            'kind' => $kind,
            'path' => $media->path,
            'url' => MediaStorage::url($media->path),
            'mime' => $actualMime,
            'alt' => $media->alt,
            'sort_order' => (int) $media->sort_order,
        ];
    }

    private function storeStoryMedia(
        SocialContent $story,
        array $metadata,
        UploadedFile $file,
        string $actualMime,
    ): array {
        $slot = (string) $metadata['slot'];

        if ($slot === 'thumbnail') {
            $this->ensureImageMime($actualMime);
            $stored = $this->optimizer->store($file, 'shorts/thumbnails');
            $old = $story->thumbnail;

            try {
                $story->thumbnail = $stored['path'];
                $story->save();
            } catch (Throwable $exception) {
                MediaStorage::disk()->delete($stored['path']);
                throw $exception;
            }

            if ($old && $old !== $story->video_path && $old !== $stored['path']) {
                MediaStorage::disk()->delete($old);
            }

            return $this->directAsset('thumbnail', 'image', $stored['path'], $actualMime) ?? [];
        }

        $stored = $this->optimizer->store($file, 'shorts');
        $kind = $stored['type'];
        $oldPaths = array_values(array_filter([$story->video_path, $story->thumbnail]));

        try {
            $story->media_type = $kind;
            $story->video_path = $stored['path'];
            $story->video_mime = $actualMime;
            $story->thumbnail = $kind === 'image' ? $stored['path'] : null;
            $story->duration = $kind === 'image' ? 5 : ($metadata['duration'] ?? null);
            $story->save();
        } catch (Throwable $exception) {
            MediaStorage::disk()->delete($stored['path']);
            throw $exception;
        }

        MediaStorage::disk()->delete(array_values(array_diff(array_unique($oldPaths), [$stored['path']])));

        return $this->directAsset('media', $kind, $stored['path'], $actualMime) ?? [];
    }

    private function storeVideoMedia(
        SocialContent $video,
        array $metadata,
        UploadedFile $file,
        string $actualMime,
    ): array {
        $slot = (string) $metadata['slot'];

        if ($slot === 'thumbnail') {
            $this->ensureImageMime($actualMime);
            $stored = $this->optimizer->store($file, 'videos/thumbnails');
            $old = $video->thumbnail;

            try {
                $video->thumbnail = $stored['path'];
                $video->save();
            } catch (Throwable $exception) {
                MediaStorage::disk()->delete($stored['path']);
                throw $exception;
            }

            if ($old && $old !== $stored['path']) {
                MediaStorage::disk()->delete($old);
            }

            return $this->directAsset('thumbnail', 'image', $stored['path'], $actualMime) ?? [];
        }

        $this->ensureVideoMime($actualMime);
        $stored = $this->optimizer->store($file, 'videos');
        $old = $video->video_path;

        try {
            $video->video_path = $stored['path'];
            $video->video_mime = $actualMime;
            if (array_key_exists('duration', $metadata) && $metadata['duration'] !== null) {
                $video->duration = (int) $metadata['duration'];
            }
            $video->save();
        } catch (Throwable $exception) {
            MediaStorage::disk()->delete($stored['path']);
            throw $exception;
        }

        if ($old && $old !== $stored['path']) {
            MediaStorage::disk()->delete($old);
        }

        return $this->directAsset('video', 'video', $stored['path'], $actualMime) ?? [];
    }

    private function storeProductMedia(
        Product $product,
        array $metadata,
        UploadedFile $file,
        string $actualMime,
    ): array {
        if (! in_array($actualMime, [...self::IMAGE_MIMES, ...self::VIDEO_MIMES], true)) {
            throw new RuntimeException('Product media must be a supported image or video.');
        }

        $kind = str_starts_with($actualMime, 'video/') ? 'video' : 'image';
        if ($kind === 'image' && $file->getSize() > 8 * 1024 * 1024) {
            throw new RuntimeException('Product images must be 8 MB or smaller.');
        }

        $stored = $this->optimizer->store($file, "products/{$product->id}");
        $sortOrder = $metadata['sort_order'] ?? ((int) $product->media()->max('sort_order') + 1);
        $isPrimary = $kind === 'image' && ! $product->media()->where('type', 'image')->exists();

        try {
            $media = $product->media()->create([
                'type' => $kind,
                'path' => $stored['path'],
                'alt' => filled($metadata['alt'] ?? null) ? trim((string) $metadata['alt']) : $product->title,
                'sort_order' => (int) $sortOrder,
                'is_primary' => $isPrimary,
            ]);
        } catch (Throwable $exception) {
            MediaStorage::disk()->delete($stored['path']);
            throw $exception;
        }

        return [
            'id' => $media->id,
            'slot' => 'media',
            'kind' => $kind,
            'path' => $media->path,
            'url' => MediaStorage::url($media->path),
            'mime' => $actualMime,
            'alt' => $media->alt,
            'sort_order' => (int) $media->sort_order,
            'is_primary' => (bool) $media->is_primary,
        ];
    }

    private function storeAttachment(
        array $metadata,
        UploadedFile $file,
        string $actualMime,
        string $sha256,
    ): array {
        $resource = (string) $metadata['resource'];
        $extension = $this->safeExtensionForMime($actualMime);
        $path = 'content-assets/'.$resource.'/'.Str::uuid().'.'.$extension;
        $stream = fopen($file->getRealPath(), 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Could not read the uploaded attachment.');
        }

        try {
            $stored = MediaStorage::disk()->put($path, $stream);
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException('Could not store the uploaded attachment.');
        }

        try {
            $asset = ContentAsset::query()->create([
                'resource' => $resource,
                'resource_id' => (int) $metadata['id'],
                'slot' => 'attachment',
                'kind' => $this->kindFromMime($actualMime),
                'path' => $path,
                'original_name' => basename((string) $metadata['name']),
                'mime' => $actualMime,
                'size' => (int) $metadata['size'],
                'alt' => filled($metadata['alt'] ?? null) ? trim((string) $metadata['alt']) : null,
                'sort_order' => (int) ($metadata['sort_order'] ?? 0),
            ]);
        } catch (Throwable $exception) {
            MediaStorage::disk()->delete($path);
            throw $exception;
        }

        return [
            ...$this->serializeAttachment($asset),
            'sha256' => $sha256,
        ];
    }

    private function serializeAttachment(ContentAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'slot' => $asset->slot,
            'kind' => $asset->kind,
            'path' => $asset->path,
            'url' => MediaStorage::url($asset->path),
            'original_name' => $asset->original_name,
            'mime' => $asset->mime,
            'size' => (int) $asset->size,
            'alt' => $asset->alt,
            'sort_order' => (int) $asset->sort_order,
            'storage_exists' => filled($asset->path) && MediaStorage::disk()->exists($asset->path),
        ];
    }

    private function directAsset(string $slot, ?string $kind, ?string $path, ?string $mime = null): ?array
    {
        if (! $path) {
            return null;
        }

        return [
            'id' => null,
            'slot' => $slot,
            'kind' => $kind ?: 'file',
            'path' => $path,
            'url' => MediaStorage::url($path),
            'mime' => $mime,
            'storage_exists' => MediaStorage::disk()->exists($path),
        ];
    }

    private function removeDirectField(Model $target, string $slot, array $allowedFields): ?string
    {
        if (! in_array($slot, $allowedFields, true)) {
            throw new RuntimeException('Invalid media slot for this resource.');
        }

        $old = $target->{$slot};
        $target->{$slot} = null;
        $target->save();

        return $old ?: null;
    }

    private function removeStorySlot(SocialContent $story, string $slot): ?string
    {
        if ($slot === 'thumbnail') {
            $old = $story->thumbnail;
            $story->thumbnail = null;
            $story->save();

            return $old && $old !== $story->video_path ? $old : null;
        }

        if ($slot !== 'media') {
            throw new RuntimeException('Invalid story media slot.');
        }

        $old = $story->video_path;
        if ($story->thumbnail === $old) {
            $story->thumbnail = null;
        }
        $story->video_path = null;
        $story->video_mime = null;
        $story->media_type = null;
        $story->duration = null;
        $story->save();

        return $old ?: null;
    }

    private function removeVideoSlot(SocialContent $video, string $slot): ?string
    {
        if ($slot === 'thumbnail') {
            $old = $video->thumbnail;
            $video->thumbnail = null;
            $video->save();

            return $old ?: null;
        }

        if ($slot !== 'video') {
            throw new RuntimeException('Invalid video media slot.');
        }

        $old = $video->video_path;
        $video->video_path = null;
        $video->video_mime = null;
        $video->duration = null;
        $video->save();

        return $old ?: null;
    }

    private function resolveTarget(string $resource, int $id): Model
    {
        return match ($resource) {
            'game' => Game::query()->findOrFail($id),
            'studio' => Studio::query()->findOrFail($id),
            'platform' => Platform::query()->findOrFail($id),
            'collection' => VideoPlaylist::query()->findOrFail($id),
            'feed' => SocialContent::query()->where('type', 'post')->findOrFail($id),
            'story' => SocialContent::query()->where('type', 'short')->findOrFail($id),
            'video' => SocialContent::query()->where('type', 'video')->findOrFail($id),
            'product' => Product::query()->findOrFail($id),
        };
    }

    private function ensureValidSlot(string $resource, string $slot): void
    {
        $allowed = match ($resource) {
            'game' => ['cover', 'background', 'attachment'],
            'studio' => ['logo', 'background', 'attachment'],
            'platform' => ['icon', 'attachment'],
            'collection' => ['logo', 'attachment'],
            'feed' => ['media', 'attachment'],
            'story' => ['media', 'thumbnail', 'attachment'],
            'video' => ['video', 'thumbnail', 'attachment'],
            'product' => ['media', 'attachment'],
        };

        if (! in_array($slot, $allowed, true)) {
            throw new RuntimeException("Invalid {$slot} slot for {$resource}.");
        }
    }

    private function ensureMimeAllowedForSlot(string $resource, string $slot, string $mime): void
    {
        if ($slot === 'attachment') {
            return;
        }

        if (in_array($slot, ['cover', 'background', 'logo', 'icon', 'thumbnail'], true)) {
            $this->ensureImageMime($mime);

            return;
        }

        if ($resource === 'video' && $slot === 'video') {
            $this->ensureVideoMime($mime);

            return;
        }

        if (in_array($resource, ['feed', 'product'], true) && $slot === 'media') {
            if (! in_array($mime, [...self::IMAGE_MIMES, ...self::VIDEO_MIMES], true)) {
                throw new RuntimeException('List media must be a supported image or video.');
            }

            return;
        }

        if ($resource === 'story' && $slot === 'media') {
            if (! in_array($mime, [...self::IMAGE_MIMES, ...self::VIDEO_MIMES], true)) {
                throw new RuntimeException('Story media must be a supported image or video.');
            }
        }
    }

    private function ensureImageMime(string $mime): void
    {
        if (! in_array($mime, self::IMAGE_MIMES, true)) {
            throw new RuntimeException('This slot accepts JPEG, PNG, WebP or GIF images only.');
        }
    }

    private function ensureVideoMime(string $mime): void
    {
        if (! in_array($mime, self::VIDEO_MIMES, true)) {
            throw new RuntimeException('This slot accepts MP4, WebM or QuickTime video only.');
        }
    }

    private function kindFromMime(?string $mime): string
    {
        if (is_string($mime) && str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (is_string($mime) && str_starts_with($mime, 'video/')) {
            return 'video';
        }

        return 'file';
    }

    private function safeExtensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            'application/zip', 'application/x-zip-compressed' => 'zip',
            'application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed' => 'rar',
            'application/x-7z-compressed' => '7z',
            'text/plain' => 'txt',
            'application/json' => 'json',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => 'bin',
        };
    }

    private function metadata(string $uploadId): array
    {
        $path = $this->uploadDirectory($uploadId).'/metadata.json';
        if (! File::isFile($path)) {
            throw new RuntimeException('Upload session was not found or has expired.');
        }

        try {
            $metadata = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('Upload session metadata is corrupted.');
        }

        if (! is_array($metadata)) {
            throw new RuntimeException('Upload session metadata is invalid.');
        }

        return $metadata;
    }

    private function uploadDirectory(string $uploadId): string
    {
        return storage_path('app/private/content-agent-uploads/'.$uploadId);
    }

    private function purgeExpiredUploads(): void
    {
        $root = storage_path('app/private/content-agent-uploads');
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

    private function maxUploadSize(): int
    {
        return max(1, (int) config('content_agent.uploads.max_size', 104857600));
    }

    private function maxChunkSize(): int
    {
        return max(1, (int) config('content_agent.uploads.max_chunk_size', 2097152));
    }

    private function uploadTtl(): int
    {
        return max(300, (int) config('content_agent.uploads.ttl_seconds', 86400));
    }

    private function ensureUploadsAllowed(): void
    {
        if (! (bool) config('content_agent.allow_uploads', false)) {
            throw new RuntimeException('Content-agent media/file uploads are disabled by server configuration.');
        }
    }

    private function ensureDestructiveAllowed(): void
    {
        if (! (bool) config('content_agent.allow_destructive', false)) {
            throw new RuntimeException('Destructive content-agent operations are disabled by server configuration.');
        }
    }

    private function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }
}
