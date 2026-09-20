<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeploymentAgentApiTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/deployment-agent-'.bin2hex(random_bytes(4)));

        config([
            'content_agent.token' => 'deployment-agent-test-token',
            'content_agent.author_user_id' => 42,
            'deployment.import_enabled' => true,
            'deployment.directory' => $this->directory,
            'deployment.chunk_size' => 2 * 1024 * 1024,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_deployment_agent_requires_the_existing_content_agent_token(): void
    {
        $this->postJson('/api/deployment-agent/upload/complete', [
            'operation_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertUnauthorized();
    }

    public function test_deployment_agent_rejects_non_main_source_refs_before_upload(): void
    {
        $this->withToken('deployment-agent-test-token')
            ->post('/api/deployment-agent/upload/chunk', $this->chunkPayload('refs/heads/chatgpt_dev'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_ref']);
    }

    public function test_deployment_agent_accepts_main_chunk_and_records_source_identity(): void
    {
        $sha = str_repeat('a', 40);

        $response = $this->withToken('deployment-agent-test-token')
            ->post('/api/deployment-agent/upload/chunk', $this->chunkPayload('refs/heads/main', $sha));

        $response->assertOk()
            ->assertJsonPath('user_id', 42)
            ->assertJsonPath('source_ref', 'refs/heads/main')
            ->assertJsonPath('source_sha', $sha)
            ->assertJsonPath('run_id', '123456');

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9-]{36}$/',
            (string) $response->json('id'),
        );
    }

    private function chunkPayload(string $ref, ?string $sha = null): array
    {
        return [
            'operation_id' => '',
            'chunk_index' => 0,
            'total_chunks' => 1,
            'size' => 1024,
            'name' => 'deployment.zip',
            'source_sha' => $sha ?? str_repeat('b', 40),
            'source_ref' => $ref,
            'run_id' => '123456',
            'chunk' => UploadedFile::fake()->create('deployment.part', 1, 'application/octet-stream'),
        ];
    }
}
