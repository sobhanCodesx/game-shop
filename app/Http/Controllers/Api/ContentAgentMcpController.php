<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentAgentService;
use App\Services\FeedService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ContentAgentMcpController extends Controller
{
    private const MODERN_PROTOCOL = '2026-07-28';
    private const LEGACY_PROTOCOL = '2025-11-25';

    public function __invoke(Request $request, ContentAgentService $contentAgent): Response
    {
        $payload = $request->json()->all();

        if ($payload === [] || array_is_list($payload)) {
            return $this->rpcError(null, -32600, 'Invalid JSON-RPC request.', 400);
        }

        $id = $payload['id'] ?? null;
        $method = (string) ($payload['method'] ?? '');
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        if (($payload['jsonrpc'] ?? null) !== '2.0' || $method === '') {
            return $this->rpcError($id, -32600, 'Invalid JSON-RPC request.', 400);
        }

        if ($method === 'notifications/initialized') {
            return response('', 202);
        }

        try {
            return match ($method) {
                'initialize' => $this->rpcResult($id, $this->initializeResult($params)),
                'server/discover' => $this->rpcResult($id, $this->discoverResult()),
                'tools/list' => $this->rpcResult($id, $this->toolsListResult()),
                'tools/call' => $this->rpcResult($id, $this->callTool($params, $contentAgent)),
                'ping' => $this->rpcResult($id, new \stdClass()),
                default => $this->rpcError($id, -32601, 'Method not found.'),
            };
        } catch (ValidationException $exception) {
            return $this->rpcResult($id, $this->toolError('Validation failed.', [
                'errors' => $exception->errors(),
            ]));
        } catch (ModelNotFoundException) {
            return $this->rpcResult($id, $this->toolError('Requested PlayNexus record was not found.'));
        } catch (RuntimeException $exception) {
            return $this->rpcResult($id, $this->toolError($exception->getMessage()));
        } catch (Throwable $exception) {
            report($exception);

            return $this->rpcResult($id, $this->toolError('The PlayNexus content agent could not complete the request.'));
        }
    }

    private function initializeResult(array $params): array
    {
        $requested = (string) ($params['protocolVersion'] ?? self::LEGACY_PROTOCOL);
        $protocolVersion = $requested === self::LEGACY_PROTOCOL ? $requested : self::LEGACY_PROTOCOL;

        return [
            'protocolVersion' => $protocolVersion,
            'capabilities' => ['tools' => new \stdClass()],
            'serverInfo' => $this->serverInfo(),
            'instructions' => $this->instructions(),
        ];
    }

    private function discoverResult(): array
    {
        return [
            'supportedVersions' => [self::MODERN_PROTOCOL],
            'capabilities' => ['tools' => new \stdClass()],
            'instructions' => $this->instructions(),
            'ttlMs' => 3600000,
            'cacheScope' => 'private',
            '_meta' => $this->resultMeta(),
        ];
    }

    private function toolsListResult(): array
    {
        return [
            'tools' => $this->tools(),
            'ttlMs' => 3600000,
            'cacheScope' => 'private',
            '_meta' => $this->resultMeta(),
        ];
    }

    private function callTool(array $params, ContentAgentService $contentAgent): array
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $result = match ($name) {
            'search_games' => $contentAgent->searchGames($arguments),
            'search_studios' => $contentAgent->searchStudios($arguments),
            'get_feed' => $contentAgent->getFeed($arguments),
            'create_feed' => $contentAgent->createFeed($arguments),
            'update_feed' => $contentAgent->updateFeed($arguments),
            'publish_feed' => $contentAgent->publishFeed($arguments),
            default => throw new RuntimeException("Unknown PlayNexus tool: {$name}"),
        };

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ]],
            'structuredContent' => ['result' => $result],
            'isError' => false,
            '_meta' => $this->resultMeta(),
        ];
    }

    private function toolError(string $message, array $details = []): array
    {
        $payload = ['error' => $message, ...$details];

        return [
            'content' => [[
                'type' => 'text',
                'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ]],
            'structuredContent' => $payload,
            'isError' => true,
            '_meta' => $this->resultMeta(),
        ];
    }

    private function tools(): array
    {
        $feedProperties = [
            'title' => ['type' => 'string', 'maxLength' => 160, 'description' => 'Persian SEO-friendly feed title.'],
            'body' => ['type' => ['string', 'null'], 'maxLength' => 100000, 'description' => 'Feed body. Safe HTML is supported; scripts and unsafe markup are stripped.'],
            'feed_type' => ['type' => 'string', 'enum' => FeedService::TYPES],
            'feed_badge' => ['type' => ['string', 'null'], 'enum' => ['breaking', 'news', 'trailer', 'gameplay', 'update', 'rumor', 'review', 'patch_notes', null]],
            'game_id' => ['type' => ['integer', 'null']],
            'related_product_id' => ['type' => ['integer', 'null']],
            'related_content_id' => ['type' => ['integer', 'null'], 'description' => 'Related PlayNexus video content id.'],
            'allow_comments' => ['type' => 'boolean'],
            'notify_followers' => ['type' => 'boolean'],
            'seo_title' => ['type' => ['string', 'null'], 'maxLength' => 60],
            'seo_description' => ['type' => ['string', 'null'], 'maxLength' => 160],
        ];

        return [
            [
                'name' => 'search_games',
                'description' => 'Search existing PlayNexus games before linking a feed to a game.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'maxLength' => 120],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'search_studios',
                'description' => 'Search existing PlayNexus game studios.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'maxLength' => 120],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10],
                    ],
                    'required' => ['query'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'get_feed',
                'description' => 'Read one PlayNexus feed post by id, including draft content.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_feed',
                'description' => 'Create a PlayNexus feed post as DRAFT. This tool never publishes content.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $feedProperties,
                    'required' => ['title'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'update_feed',
                'description' => 'Edit an existing PlayNexus feed post. Publishing is not performed by this tool.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1], ...$feedProperties],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'publish_feed',
                'description' => 'Publish an existing PlayNexus feed post. Use only when the user explicitly asks to publish. Server-side publishing must also be enabled.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => true],
            ],
        ];
    }

    private function instructions(): string
    {
        return 'PlayNexus content tools. Create content as a draft first. Publish only after an explicit user request and only when server-side publishing is enabled.';
    }

    private function serverInfo(): array
    {
        return [
            'name' => 'playnexus-content-agent',
            'title' => 'PlayNexus Content Agent',
            'version' => '1.0.0',
        ];
    }

    private function resultMeta(): array
    {
        return ['io.modelcontextprotocol/serverInfo' => $this->serverInfo()];
    }

    private function rpcResult(mixed $id, array|\stdClass $result): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }

    private function rpcError(mixed $id, int $code, string $message, int $status = 200): Response
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }
}
