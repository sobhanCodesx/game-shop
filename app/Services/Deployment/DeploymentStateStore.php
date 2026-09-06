<?php

namespace App\Services\Deployment;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

final class DeploymentStateStore
{
    public function __construct(private DeploymentPaths $paths) {}

    public function create(int $userId, array $extra = []): array
    {
        $id = (string) Str::uuid();
        $now = now()->toIso8601String();
        $state = array_merge([
            'id' => $id, 'user_id' => $userId, 'status' => 'uploading',
            'stage' => 'uploaded', 'progress' => 0, 'created_at' => $now,
            'updated_at' => $now, 'expires_at' => now()->addSeconds((int) config('deployment.token_ttl'))->toIso8601String(),
            'logs' => [], 'warnings' => [], 'error' => null,
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

    public function cleanup(): int
    {
        $removed=0;
        foreach (glob($this->paths->root().'/*/state.json') ?: [] as $file) {
            try { $state=$this->paths->readJson($file); if (in_array($state['status'], ['failed','uploading'], true) && strtotime($state['updated_at']) < now()->subDay()->timestamp) { File::deleteDirectory(dirname($file)); $removed++; } } catch (\Throwable) { /* keep unreadable state for manual inspection */ }
        }
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
                    $completed[] = ['file' => $file, 'updated_at' => strtotime((string) ($state['updated_at'] ?? '')) ?: 0];
                }
            } catch (\Throwable) {
                // Keep unreadable state for manual inspection.
            }
        }

        usort($completed, fn (array $a, array $b) => $b['updated_at'] <=> $a['updated_at']);
        $removed = 0;
        foreach (array_slice($completed, $keep) as $deployment) {
            if (File::deleteDirectory(dirname($deployment['file']))) $removed++;
        }

        return $removed;
    }
}
