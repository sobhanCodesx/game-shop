<?php

namespace Tests\Unit;

use App\Services\Deployment\DeploymentPaths;
use App\Services\Deployment\DeploymentStateStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentStateStoreTest extends TestCase
{
    public function test_it_keeps_only_the_latest_successful_deployment(): void
    {
        $root = storage_path('framework/testing/deployment-state-store-'.Str::uuid());
        config()->set('deployment.directory', $root);
        config()->set('deployment.retention', 1);

        $paths = new DeploymentPaths;
        $store = new DeploymentStateStore($paths);
        $oldest = (string) Str::uuid();
        $middle = (string) Str::uuid();
        $latest = (string) Str::uuid();
        $failed = (string) Str::uuid();

        try {
            foreach ([
                $oldest => ['status' => 'completed', 'updated_at' => '2026-01-01T00:00:00+00:00'],
                $middle => ['status' => 'completed', 'updated_at' => '2026-02-01T00:00:00+00:00'],
                $latest => ['status' => 'completed', 'updated_at' => '2026-03-01T00:00:00+00:00'],
                $failed => ['status' => 'failed', 'updated_at' => '2026-04-01T00:00:00+00:00'],
            ] as $id => $state) {
                $paths->atomicJson($paths->operation($id).'/state.json', ['id' => $id] + $state);
            }

            $this->assertSame(2, $store->pruneSuccessful());
            $this->assertDirectoryDoesNotExist($paths->operation($oldest));
            $this->assertDirectoryDoesNotExist($paths->operation($middle));
            $this->assertDirectoryExists($paths->operation($latest));
            $this->assertDirectoryExists($paths->operation($failed));
        } finally {
            File::deleteDirectory($root);
        }
    }
}
