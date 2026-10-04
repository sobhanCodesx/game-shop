<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class DeploymentStateStore
{
    public function __construct(private DeploymentPaths $paths) {}

    public function create(int $userId, array $extra = []): array
    {
        $id = (string) Str::uuid();
        $now = now()->toIso8601String();
        $state = array_merge([
            'id' => $id,
            'user_id' => $userId,
            'status' => 'uploading',
            'stage' => 'uploaded',
            'progress' => 0,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => now()->addSeconds((int) config('deployment.token_ttl'))->toIso8601String(),
            'logs' => [],
            'warnings' => [],
            'error' => null,
        ], $extra);
        $this->save($state);

        return $state;
    }

    public function get(string $id): array
    {
        return $this->paths->readJson($this->paths->operation($id).'/state.json');
    }

    public function save(array $state): array
    {
        $state['updated_at'] = now()->toIso8601String();
        $this->paths->atomicJson($this->paths->operation($state['id']).'/state.json', $state);

        return $state;
    }

    public function update(string $id, array $changes): array
    {
        return $this->save(array_replace($this->get($id), $changes));
    }

    public function recent(int $limit = 10): array
    {
        $files = glob($this->paths->root().'/*/state.json') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return array_map(fn ($file) => $this->paths->readJson($file), array_slice($files, 0, $limit));
    }

    /**
     * Reclaim abandoned deployment disk usage before a new package is accepted.
     * Active uploads are preserved; only stale, failed, orphaned and superseded
     * successful operations are deleted.
     */
    public function cleanup(): int
    {
        $removed = 0;
        $root = $this->paths->root();
        $uploadingCutoff = now()
            ->subMinutes(max(5, (int) config('deployment.stale_upload_minutes', 15)))
            ->timestamp;
        $failedCutoff = now()
            ->subMinutes(max(1, (int) config('deployment.failed_retention_minutes', 5)))
            ->timestamp;

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $stateFile = $directory.'/state.json';

            if (! is_file($stateFile)) {
                $modified = @filemtime($directory) ?: 0;
                if ($modified > 0 && $modified < $uploadingCutoff && File::deleteDirectory($directory)) {
                    $removed++;
                }

                continue;
            }

            try {
                $state = $this->paths->readJson($stateFile);
                $status = (string) ($state['status'] ?? '');
                $updatedAt = strtotime((string) ($state['updated_at'] ?? '')) ?: 0;
                $staleUpload = $status === 'uploading'
                    && $updatedAt > 0
                    && $updatedAt < $uploadingCutoff;
                $staleFailure = $status === 'failed'
                    && $updatedAt > 0
                    && $updatedAt < $failedCutoff;

                if (($staleUpload || $staleFailure) && File::deleteDirectory($directory)) {
                    $removed++;
                }
            } catch (\Throwable) {
                // Keep unreadable state for manual inspection instead of risking deletion.
            }
        }

        $this->trimLegacyLaravelLogUnderPressure();

        return $removed + $this->pruneSuccessful();
    }

    public function pruneSuccessful(?int $keep = null): int
    {
        $keep = max(1, $keep ?? (int) config('deployment.retention', 1));
        $completed = [];

        foreach (glob($this->paths->root().'/*/state.json') ?: [] as $file) {
            try {
                $state = $this->paths->readJson($file);
                if (($state['status'] ?? null) === 'completed') {
                    $completed[] = [
                        'file' => $file,
                        'updated_at' => strtotime((string) ($state['updated_at'] ?? '')) ?: 0,
                    ];
                }
            } catch (\Throwable) {
                // Keep unreadable state for manual inspection.
            }
        }

        usort($completed, fn (array $a, array $b) => $b['updated_at'] <=> $a['updated_at']);
        $removed = 0;

        foreach (array_slice($completed, $keep) as $deployment) {
            if (File::deleteDirectory(dirname($deployment['file']))) {
                $removed++;
            }
        }

        return $removed;
    }

    private function trimLegacyLaravelLogUnderPressure(): void
    {
        $path = storage_path('logs/laravel.log');
        if (! is_file($path) || ! is_writable($path)) {
            return;
        }

        clearstatcache(true, $path);
        $size = @filesize($path);
        if (! is_int($size) || $size < 1) {
            return;
        }

        $maxBytes = max(
            4 * 1024 * 1024,
            (int) config('deployment.legacy_log_max_bytes', 16 * 1024 * 1024),
        );
        $minimumFree = max(
            32 * 1024 * 1024,
            (int) config('deployment.min_free_disk_bytes', 128 * 1024 * 1024),
        );
        $free = function_exists('disk_free_space') ? @disk_free_space(dirname($path)) : false;
        $severelyOversized = $size >= ($maxBytes * 4);
        $underPressure = $free !== false && $free < $minimumFree;

        if ($size <= $maxBytes || (! $severelyOversized && ! $underPressure)) {
            return;
        }

        $keepBytes = min(512 * 1024, max(64 * 1024, intdiv($maxBytes, 4)));
        $handle = @fopen($path, 'c+b');
        if (! $handle || ! @flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                @fclose($handle);
            }

            return;
        }

        try {
            $offset = max(0, $size - $keepBytes);
            @fseek($handle, $offset, SEEK_SET);
            $tail = (string) stream_get_contents($handle);

            if (! @ftruncate($handle, 0)) {
                return;
            }

            @rewind($handle);
            if ($tail !== '') {
                @fwrite($handle, $tail);
            }
            @fflush($handle);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
    }
}
