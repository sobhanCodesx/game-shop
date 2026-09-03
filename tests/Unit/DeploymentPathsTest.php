<?php

namespace Tests\Unit;

use App\Services\Deployment\DeploymentPaths;
use RuntimeException;
use Tests\TestCase;

class DeploymentPathsTest extends TestCase
{
    public function test_operation_id_cannot_escape_deployment_directory(): void
    {
        $this->expectException(RuntimeException::class);
        app(DeploymentPaths::class)->operation('../../.env');
    }
}
