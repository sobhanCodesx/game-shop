<?php

namespace Tests\Feature;

use App\Services\ContentAgentMediaService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentAgentMcpTest extends TestCase
{
    public function test_mcp_endpoint_rejects_missing_bearer_token(): void
    {
        config()->set('content_agent.token', 'test-secret');

        $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertUnauthorized();
    }

    public function test_mcp_endpoint_lists_advanced_content_admin_and_asset_tools_with_valid_token(): void
    {
        config()->set('content_agent.token', 'test-secret');

        $response = $this->withToken('test-secret')->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $response->assertOk()
            ->assertJsonPath('jsonrpc', '2.0')
            ->assertJsonPath('id', 1)
            ->assertJsonFragment(['name' => 'search_games'])
            ->assertJsonFragment(['name' => 'search_studios'])
            ->assertJsonFragment(['name' => 'search_platforms'])
            ->assertJsonFragment(['name' => 'search_collections'])
            ->assertJsonFragment(['name' => 'select_content'])
            ->assertJsonFragment(['name' => 'get_content'])
            ->assertJsonFragment(['name' => 'create_game'])
            ->assertJsonFragment(['name' => 'create_studio'])
            ->assertJsonFragment(['name' => 'create_collection'])
            ->assertJsonFragment(['name' => 'create_story'])
            ->assertJsonFragment(['name' => 'create_video'])
            ->assertJsonFragment(['name' => 'create_feed'])
            ->assertJsonFragment(['name' => 'update_content'])
            ->assertJsonFragment(['name' => 'sync_collection_videos'])
            ->assertJsonFragment(['name' => 'set_content_state'])
            ->assertJsonFragment(['name' => 'publish_feed'])
            ->assertJsonFragment(['name' => 'unpublish_feed'])
            ->assertJsonFragment(['name' => 'delete_content'])
            ->assertJsonFragment(['name' => 'restore_content'])
            ->assertJsonFragment(['name' => 'start_asset_upload'])
            ->assertJsonFragment(['name' => 'upload_asset_chunk'])
            ->assertJsonFragment(['name' => 'complete_asset_upload'])
            ->assertJsonFragment(['name' => 'abort_asset_upload'])
            ->assertJsonFragment(['name' => 'list_content_assets'])
            ->assertJsonFragment(['name' => 'remove_content_asset'])
            ->assertJsonFragment(['version' => '2.1.0']);
    }

    public function test_mcp_endpoint_supports_modern_discovery(): void
    {
        config()->set('content_agent.token', 'test-secret');

        $this->withToken('test-secret')->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'server/discover',
            'params' => [
                '_meta' => [
                    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                    'io.modelcontextprotocol/clientCapabilities' => [],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('result.supportedVersions.0', '2026-07-28')
            ->assertJsonFragment(['version' => '2.1.0']);
    }

    public function test_media_service_accepts_only_manifest_sized_base64_chunks(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.uploads.max_chunk_size', 16);

        $uploadId = (string) Str::uuid();
        $directory = storage_path('app/private/content-agent-uploads/'.$uploadId);
        File::ensureDirectoryExists($directory.'/chunks');
        File::put($directory.'/metadata.json', json_encode([
            'upload_id' => $uploadId,
            'resource' => 'game',
            'id' => 1,
            'slot' => 'attachment',
            'name' => 'hello.txt',
            'mime' => 'text/plain',
            'size' => 5,
            'chunk_size' => 5,
            'total_chunks' => 1,
            'sha256' => hash('sha256', 'hello'),
        ], JSON_THROW_ON_ERROR));

        try {
            $result = app(ContentAgentMediaService::class)->uploadChunk([
                'upload_id' => $uploadId,
                'chunk_index' => 0,
                'data_base64' => base64_encode('hello'),
            ]);

            $this->assertSame(5, $result['received_bytes']);
            $this->assertSame('hello', File::get($directory.'/chunks/0'));

            app(ContentAgentMediaService::class)->abortUpload(['upload_id' => $uploadId]);
            $this->assertDirectoryDoesNotExist($directory);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
