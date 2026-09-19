<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Platform;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\User;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentAgentGraphqlTest extends TestCase
{
    use RefreshDatabase;

    public function test_graphql_endpoint_requires_the_existing_content_agent_token(): void
    {
        config()->set('content_agent.token', 'graph-secret');

        $this->postJson('/api/graphql', [
            'query' => '{ graphInfo { name } }',
        ])->assertUnauthorized();
    }

    public function test_ai_can_traverse_game_relationships_in_one_graph_query(): void
    {
        config()->set('content_agent.token', 'graph-secret');

        $studio = Studio::query()->create([
            'name' => 'Nexus Studio',
            'slug' => 'nexus-studio',
            'status' => 'active',
        ]);
        $platform = Platform::query()->create([
            'name' => 'PlayStation 5',
            'slug' => 'playstation-5',
            'manufacturer' => 'Sony',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $game = Game::query()->create([
            'studio_id' => $studio->id,
            'name' => 'Nexus Game',
            'slug' => 'nexus-game',
            'status' => 'active',
            'release_date' => '2026-12-01',
        ]);
        $game->platforms()->attach($platform->id);

        $author = User::factory()->create();
        SocialContent::query()->create([
            'user_id' => $author->id,
            'game_id' => $game->id,
            'type' => 'post',
            'feed_type' => 'news',
            'feed_badge' => 'news',
            'title' => 'Nexus Game got a new update',
            'slug' => 'nexus-game-update',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'title' => 'Nexus Game Guides',
            'slug' => 'nexus-game-guides',
            'visibility' => 'public',
            'sort_order' => 0,
        ]);

        $response = $this->withToken('graph-secret')->postJson('/api/graphql', [
            'query' => <<<'GRAPHQL'
query GameContext($slug: String!) {
  game(slug: $slug) {
    id
    name
    releaseDate
    studio { id name }
    platforms { id name }
    content(first: 5) {
      nodes { id type title status }
      pageInfo { total hasMore }
    }
    collections(first: 5) {
      nodes { id title visibility }
      pageInfo { total }
    }
  }
}
GRAPHQL,
            'variables' => ['slug' => 'nexus-game'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.game.name', 'Nexus Game')
            ->assertJsonPath('data.game.studio.name', 'Nexus Studio')
            ->assertJsonPath('data.game.platforms.0.name', 'PlayStation 5')
            ->assertJsonPath('data.game.content.nodes.0.title', 'Nexus Game got a new update')
            ->assertJsonPath('data.game.content.pageInfo.total', 1)
            ->assertJsonPath('data.game.collections.nodes.0.title', 'Nexus Game Guides')
            ->assertJsonPath('extensions.playnexus.readOnly', true);

        $this->assertIsInt($response->json('extensions.playnexus.complexity'));
        $this->assertIsInt($response->json('extensions.playnexus.depth'));
    }

    public function test_graphql_supports_schema_introspection_for_ai_discovery(): void
    {
        config()->set('content_agent.token', 'graph-secret');
        config()->set('content_agent.graphql.allow_introspection', true);

        $this->withToken('graph-secret')->postJson('/api/graphql', [
            'query' => '{ __type(name: "Game") { name fields { name } } }',
        ])->assertOk()
            ->assertJsonPath('data.__type.name', 'Game')
            ->assertJsonFragment(['name' => 'products'])
            ->assertJsonFragment(['name' => 'content']);
    }

    public function test_graphql_rejects_mutations_even_with_a_valid_agent_token(): void
    {
        config()->set('content_agent.token', 'graph-secret');

        $this->withToken('graph-secret')->postJson('/api/graphql', [
            'query' => 'mutation { anything }',
        ])->assertStatus(400)
            ->assertJsonPath('errors.0.message', 'PlayNexus Graph is read-only. Mutations and subscriptions are not allowed.');
    }

    public function test_graphql_rejects_queries_beyond_the_configured_depth_budget(): void
    {
        config()->set('content_agent.token', 'graph-secret');
        config()->set('content_agent.graphql.max_depth', 2);

        $this->withToken('graph-secret')->postJson('/api/graphql', [
            'query' => '{ game(slug: "x") { studio { games(first: 1) { nodes { id } } } } }',
        ])->assertStatus(400);

        $this->assertStringContainsString(
            'too deep',
            strtolower((string) $this->withToken('graph-secret')->postJson('/api/graphql', [
                'query' => '{ game(slug: "x") { studio { games(first: 1) { nodes { id } } } } }',
            ])->json('errors.0.message')),
        );
    }

    public function test_mcp_can_describe_and_query_the_intelligence_graph(): void
    {
        config()->set('content_agent.token', 'graph-secret');

        $tools = $this->withToken('graph-secret')->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ]);

        $tools->assertOk()
            ->assertJsonFragment(['name' => 'describe_playnexus_graph'])
            ->assertJsonFragment(['name' => 'query_playnexus_graph'])
            ->assertJsonFragment(['version' => '3.0.0']);

        $result = $this->withToken('graph-secret')->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => [
                'name' => 'query_playnexus_graph',
                'arguments' => [
                    'query' => '{ graphInfo { name version readOnly } }',
                ],
            ],
        ]);

        $result->assertOk()
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.result.data.graphInfo.name', 'PlayNexus Intelligence Graph')
            ->assertJsonPath('result.structuredContent.result.data.graphInfo.readOnly', true);
    }
}
