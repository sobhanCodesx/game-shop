<?php

namespace Tests\Feature;

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

    public function test_mcp_endpoint_lists_advanced_content_admin_tools_with_valid_token(): void
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
            ->assertJsonFragment(['version' => '2.0.0']);
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
            ->assertJsonFragment(['version' => '2.0.0']);
    }
}
