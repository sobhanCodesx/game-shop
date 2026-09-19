<?php

namespace App\Services\GraphQL;

use GraphQL\Error\DebugFlag;
use GraphQL\Error\SyntaxError;
use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PlayNexusGraphService
{
    private ?Schema $schema = null;

    public function __construct(
        private readonly PlayNexusGraphSchemaFactory $factory,
        private readonly PlayNexusGraphGuard $guard,
    ) {}

    public function execute(string $query, array $variables = [], ?string $operationName = null): array
    {
        $this->ensureEnabled();

        $variablesBytes = strlen(json_encode(
            $variables,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '');

        $maxVariablesBytes = (int) config('content_agent.graphql.max_variables_bytes', 96000);
        if ($variablesBytes > $maxVariablesBytes) {
            throw new RuntimeException("GraphQL variables are too large. Maximum is {$maxVariablesBytes} bytes.");
        }

        try {
            $metrics = $this->guard->analyze($query, $variables);
        } catch (SyntaxError $exception) {
            throw new RuntimeException('Invalid GraphQL syntax: '.$exception->getMessage(), 0, $exception);
        }

        $startedAt = hrtime(true);

        $execution = GraphQL::executeQuery(
            $this->schema(),
            $query,
            null,
            ['playnexus_internal_agent' => true],
            $variables,
            $operationName,
        );

        $result = $execution->toArray(DebugFlag::NONE);
        $elapsedMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);

        $maxResponseBytes = (int) config('content_agent.graphql.max_response_bytes', 4194304);
        $encodedResult = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encodedResult) && strlen($encodedResult) > $maxResponseBytes) {
            $result = [
                'data' => null,
                'errors' => [[
                    'message' => "GraphQL response exceeded the {$maxResponseBytes}-byte safety limit. Request fewer records or fields.",
                ]],
            ];
        }

        $result['extensions'] = [
            'playnexus' => [
                'schemaVersion' => '1.1.0',
                'readOnly' => true,
                'queryHash' => $metrics['hash'],
                'depth' => $metrics['depth'],
                'complexity' => $metrics['complexity'],
                'fieldCount' => $metrics['fields'],
                'introspection' => $metrics['introspection'],
                'executionMs' => $elapsedMs,
            ],
        ];

        Log::info('PlayNexus GraphQL agent query', [
            'hash' => $metrics['hash'],
            'depth' => $metrics['depth'],
            'complexity' => $metrics['complexity'],
            'fields' => $metrics['fields'],
            'introspection' => $metrics['introspection'],
            'execution_ms' => $elapsedMs,
            'has_errors' => ! empty($result['errors']),
        ]);

        return $result;
    }

    public function describe(): array
    {
        $this->ensureEnabled();

        return [
            'name' => 'PlayNexus Intelligence Graph',
            'version' => '1.1.0',
            'read_only' => true,
            'endpoint' => '/api/graphql',
            'authentication' => 'Uses the same Bearer token as the existing PlayNexus MCP endpoint.',
            'limits' => [
                'max_query_bytes' => (int) config('content_agent.graphql.max_query_bytes', 48000),
                'max_variables_bytes' => (int) config('content_agent.graphql.max_variables_bytes', 96000),
                'max_response_bytes' => (int) config('content_agent.graphql.max_response_bytes', 4194304),
                'max_depth' => (int) config('content_agent.graphql.max_depth', 14),
                'max_introspection_depth' => (int) config('content_agent.graphql.max_introspection_depth', 20),
                'max_complexity' => (int) config('content_agent.graphql.max_complexity', 1500),
                'max_fields' => (int) config('content_agent.graphql.max_fields', 600),
                'max_page_size' => (int) config('content_agent.graphql.max_page_size', 100),
                'max_offset' => (int) config('content_agent.graphql.max_offset', 50000),
            ],
            'guidance' => [
                'Use GraphQL for discovery, joins, filtering, context gathering and deciding what action is needed.',
                'Use existing MCP action tools for creation, edits, publishing, media and destructive operations.',
                'Prefer one purposeful graph query over many tiny search/get tool calls.',
                'Always request pageInfo when traversing a connection so you know whether more results exist.',
                'Use introspection when you need exact field or argument names.',
            ],
            'examples' => [
                [
                    'name' => 'Understand one game deeply',
                    'query' => 'query($slug: String!) { game(slug: $slug) { id name releaseDate studio { id name } platforms { id name } products(first: 5) { nodes { id title price discountPrice status } pageInfo { total hasMore } } content(first: 8, orderBy: "published_at") { nodes { id type title feedBadge status publishedAt } pageInfo { total hasMore } } } }',
                    'variables' => ['slug' => 'example-game'],
                ],
                [
                    'name' => 'Find content opportunities',
                    'query' => 'query($q: String!) { search(query: $q, first: 6) { games { id name status releaseDate } studios { id name } content { id type title status publishedAt } products { id title status price } } }',
                    'variables' => ['q' => 'game or studio name'],
                ],
                [
                    'name' => 'Inspect cached Radar context',
                    'query' => '{ radar(first: 10, status: "coming") { nodes { title releaseDate gameId gameUrl xbox { available price currency } playstation { available price currency } } pageInfo { total hasMore nextOffset } } }',
                    'variables' => new \stdClass(),
                ],
            ],
            'sdl' => SchemaPrinter::doPrint($this->schema()),
        ];
    }

    public function schema(): Schema
    {
        return $this->schema ??= $this->factory->make();
    }

    private function ensureEnabled(): void
    {
        if (! (bool) config('content_agent.graphql.enabled', true)) {
            throw new RuntimeException('PlayNexus Intelligence Graph is disabled.');
        }
    }
}
