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

    public function test_mcp_endpoint_lists_content_tools_with_valid_token(): void
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
            ->assertJsonFragment(['name' => 'create_feed'])
            ->assertJsonFragment(['name' => 'publish_feed']);
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
            ->assertJsonPath('result.supportedVersions.0', '2026-07-28');
    }
}
