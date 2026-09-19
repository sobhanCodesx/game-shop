<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentAgentMediaService;
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

    public function __invoke(Request $request, ContentAgentService $contentAgent, ContentAgentMediaService $contentMedia): Response
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
                'tools/call' => $this->rpcResult($id, $this->callTool($params, $contentAgent, $contentMedia)),
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

    private function callTool(array $params, ContentAgentService $contentAgent, ContentAgentMediaService $contentMedia): array
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        $result = match ($name) {
            'search_games' => $contentAgent->searchGames($arguments),
            'search_studios' => $contentAgent->searchStudios($arguments),
            'search_platforms' => $contentAgent->searchPlatforms($arguments),
            'search_collections' => $contentAgent->searchCollections($arguments),
            'list_game_events' => $contentAgent->listGameEvents($arguments),
            'upsert_game_event' => $contentAgent->upsertGameEvent($arguments),
            'set_game_event_state' => $contentAgent->setGameEventState($arguments),
            'select_content' => $contentAgent->selectContent($arguments),
            'get_content' => $contentAgent->getContent($arguments),
            'create_game' => $contentAgent->createGame($arguments),
            'create_studio' => $contentAgent->createStudio($arguments),
            'create_collection' => $contentAgent->createCollection($arguments),
            'create_story' => $contentAgent->createStory($arguments),
            'create_video' => $contentAgent->createVideo($arguments),
            'get_feed' => $contentAgent->getFeed($arguments),
            'create_feed' => $contentAgent->createFeed($arguments),
            'update_content' => $contentAgent->updateContent($arguments),
            'update_feed' => $contentAgent->updateFeed($arguments),
            'sync_collection_videos' => $contentAgent->syncCollectionVideos($arguments),
            'set_content_state' => $contentAgent->setContentState($arguments),
            'publish_feed' => $contentAgent->publishFeed($arguments),
            'unpublish_feed' => $contentAgent->unpublishFeed($arguments),
            'delete_content' => $contentAgent->deleteContent($arguments),
            'restore_content' => $contentAgent->restoreContent($arguments),
            'start_asset_upload' => $contentMedia->startUpload($arguments),
            'upload_asset_chunk' => $contentMedia->uploadChunk($arguments),
            'complete_asset_upload' => $contentMedia->completeUpload($arguments),
            'abort_asset_upload' => $contentMedia->abortUpload($arguments),
            'list_content_assets' => $contentMedia->listContentAssets($arguments),
            'remove_content_asset' => $contentMedia->removeContentAsset($arguments),
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
        $resourceEnum = ['game', 'studio', 'platform', 'collection', 'feed', 'story', 'video', 'product'];
        $mutableResourceEnum = ['game', 'studio', 'collection', 'feed', 'story', 'video'];
        $mediaResourceEnum = ['game', 'studio', 'platform', 'collection', 'feed', 'story', 'video', 'product'];
        $mediaSlotEnum = ['cover', 'background', 'logo', 'icon', 'media', 'video', 'thumbnail', 'attachment'];
        $maxUploadSize = max(1, (int) config('content_agent.uploads.max_size', 104857600));
        $maxChunkSize = max(1, (int) config('content_agent.uploads.max_chunk_size', 2097152));

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
            $this->searchTool('search_games', 'Search PlayNexus games before linking or creating content.'),
            $this->searchTool('search_studios', 'Search PlayNexus game studios before linking or creating content.'),
            [
                'name' => 'search_platforms',
                'description' => 'Search active platforms and retrieve ids for games.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => ['string', 'null'], 'maxLength' => 120],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 30, 'default' => 20],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            $this->searchTool('search_collections', 'Search PlayNexus collections.'),
            [
                'name' => 'list_game_events',
                'description' => 'Read structured PlayNexus Game Events for intelligence workflows. Filter by game, event type or state.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'game_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        'type' => ['type' => ['string', 'null'], 'enum' => [
                            'release_date_changed', 'released', 'major_patch', 'dlc_announced', 'dlc_released',
                            'subscription_added', 'subscription_leaving', 'price_drop', 'free_weekend',
                            'major_trailer', 'preload_available', 'server_issue', 'server_restored', 'major_news', null,
                        ]],
                        'status' => ['type' => ['string', 'null'], 'enum' => ['candidate', 'active', 'dismissed', null]],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000, 'default' => 0],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'upsert_game_event',
                'description' => 'Create a structured Game Event as CANDIDATE, or edit an existing Game Event by id. State is intentionally changed separately.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        'game_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        'type' => ['type' => ['string', 'null'], 'enum' => [
                            'release_date_changed', 'released', 'major_patch', 'dlc_announced', 'dlc_released',
                            'subscription_added', 'subscription_leaving', 'price_drop', 'free_weekend',
                            'major_trailer', 'preload_available', 'server_issue', 'server_restored', 'major_news', null,
                        ]],
                        'title' => ['type' => ['string', 'null'], 'maxLength' => 200],
                        'summary' => ['type' => ['string', 'null'], 'maxLength' => 3000],
                        'source_type' => ['type' => ['string', 'null'], 'maxLength' => 32],
                        'source_name' => ['type' => ['string', 'null'], 'maxLength' => 120],
                        'source_url' => ['type' => ['string', 'null'], 'maxLength' => 1000],
                        'external_id' => ['type' => ['string', 'null'], 'maxLength' => 190],
                        'dedupe_key' => ['type' => ['string', 'null'], 'maxLength' => 190],
                        'importance_score' => ['type' => ['integer', 'null'], 'minimum' => 0, 'maximum' => 100],
                        'confidence' => ['type' => ['number', 'null'], 'minimum' => 0, 'maximum' => 1],
                        'old_value' => ['type' => ['object', 'null'], 'additionalProperties' => true],
                        'new_value' => ['type' => ['object', 'null'], 'additionalProperties' => true],
                        'metadata' => ['type' => ['object', 'null'], 'additionalProperties' => true],
                        'detected_at' => ['type' => ['string', 'null']],
                        'effective_at' => ['type' => ['string', 'null']],
                        'expires_at' => ['type' => ['string', 'null']],
                    ],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => true],
            ],
            [
                'name' => 'set_game_event_state',
                'description' => 'Change a Game Event state. Activating an event requires PlayNexus publishing permission.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'state' => ['type' => 'string', 'enum' => ['candidate', 'active', 'dismissed']],
                    ],
                    'required' => ['id', 'state'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => true],
            ],
            [
                'name' => 'select_content',
                'description' => 'Advanced safe SELECT over PlayNexus content resources. Supports search, ids, state/status, game/studio filters, pagination, sorting, and optional soft-deleted records. Does not execute raw SQL.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $resourceEnum],
                        'query' => ['type' => ['string', 'null'], 'maxLength' => 160],
                        'ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'maxItems' => 100, 'uniqueItems' => true],
                        'status' => ['type' => ['string', 'null'], 'maxLength' => 40, 'description' => 'For collections this maps to visibility.'],
                        'game_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        'studio_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                        'include_deleted' => ['type' => 'boolean', 'default' => false],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                        'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000, 'default' => 0],
                        'order_by' => ['type' => 'string', 'enum' => ['id', 'name', 'title', 'created_at', 'updated_at', 'published_at', 'release_date', 'sort_order', 'status'], 'default' => 'id'],
                        'order_dir' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
                    ],
                    'required' => ['resource'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'get_content',
                'description' => 'Read one PlayNexus record by resource and id, including draft content and optionally soft-deleted records.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $resourceEnum],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'include_deleted' => ['type' => 'boolean', 'default' => false],
                    ],
                    'required' => ['resource', 'id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_game',
                'description' => 'Create a PlayNexus game as INACTIVE. Activation is a separate explicit state operation.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'studio_id' => ['type' => ['integer', 'null']],
                        'developer' => ['type' => ['string', 'null'], 'maxLength' => 255],
                        'publisher' => ['type' => ['string', 'null'], 'maxLength' => 255],
                        'release_date' => ['type' => ['string', 'null'], 'description' => 'Date such as YYYY-MM-DD.'],
                        'age_rating' => ['type' => ['string', 'null'], 'maxLength' => 20],
                        'description' => ['type' => ['string', 'null'], 'maxLength' => 100000],
                        'platform_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 30, 'uniqueItems' => true],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_studio',
                'description' => 'Create a PlayNexus studio as INACTIVE. Activation is a separate explicit state operation.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'maxLength' => 160],
                        'description' => ['type' => ['string', 'null'], 'maxLength' => 100000],
                        'website' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_collection',
                'description' => 'Create a PlayNexus collection as PRIVATE. Making it public is a separate explicit state operation.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => 160],
                        'game_id' => ['type' => ['integer', 'null']],
                        'studio_id' => ['type' => ['integer', 'null']],
                        'description' => ['type' => ['string', 'null'], 'maxLength' => 100000],
                        'sort_order' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 65535, 'default' => 0],
                    ],
                    'required' => ['title'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_story',
                'description' => 'Create PlayNexus story metadata as DRAFT. Media is attached separately.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => 100],
                        'excerpt' => ['type' => ['string', 'null'], 'maxLength' => 240],
                        'game_id' => ['type' => ['integer', 'null']],
                        'link_url' => ['type' => ['string', 'null'], 'maxLength' => 500],
                        'link_label' => ['type' => ['string', 'null'], 'maxLength' => 60],
                        'sort_order' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 9999, 'default' => 0],
                    ],
                    'required' => ['title'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_video',
                'description' => 'Create PlayNexus video metadata as DRAFT. The binary video/thumbnail can be attached later by the media tools.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'maxLength' => 160],
                        'game_id' => ['type' => ['integer', 'null']],
                        'playlist_ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'maxItems' => 100, 'uniqueItems' => true],
                        'excerpt' => ['type' => ['string', 'null'], 'maxLength' => 500],
                        'body' => ['type' => ['string', 'null'], 'maxLength' => 100000],
                        'seo_title' => ['type' => ['string', 'null'], 'maxLength' => 60],
                        'seo_description' => ['type' => ['string', 'null'], 'maxLength' => 160],
                        'featured' => ['type' => 'boolean'],
                        'allow_comments' => ['type' => 'boolean'],
                    ],
                    'required' => ['title'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
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
                'name' => 'update_content',
                'description' => 'Edit an existing game, studio, collection, feed, story, or video. Only allowlisted fields are accepted for each resource and publication state is never changed here.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $mutableResourceEnum],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'data' => ['type' => 'object', 'description' => 'Fields to update. Resource-specific server validation applies.', 'additionalProperties' => true],
                    ],
                    'required' => ['resource', 'id', 'data'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'get_feed',
                'description' => 'Compatibility helper to read one feed by id, including draft content.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'update_feed',
                'description' => 'Compatibility helper to edit an existing PlayNexus feed without changing publication state.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1], ...$feedProperties],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'start_asset_upload',
                'description' => 'Start a secure chunked binary upload for a PlayNexus record. Metadata only; this MCP never fetches a remote URL. Supports games, studios, platforms, collections, feeds, stories, videos and products.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $mediaResourceEnum],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'slot' => ['type' => 'string', 'enum' => $mediaSlotEnum],
                        'name' => ['type' => 'string', 'maxLength' => 255],
                        'mime' => ['type' => 'string', 'maxLength' => 120, 'description' => 'Client-declared MIME; the server independently detects the real MIME before attaching.'],
                        'size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $maxUploadSize],
                        'chunk_size' => ['type' => 'integer', 'minimum' => 1, 'maximum' => $maxChunkSize],
                        'total_chunks' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                        'sha256' => ['type' => ['string', 'null'], 'minLength' => 64, 'maxLength' => 64],
                        'alt' => ['type' => ['string', 'null'], 'maxLength' => 255],
                        'sort_order' => ['type' => ['integer', 'null'], 'minimum' => 0],
                        'duration' => ['type' => ['integer', 'null'], 'minimum' => 0],
                    ],
                    'required' => ['resource', 'id', 'slot', 'name', 'mime', 'size', 'chunk_size', 'total_chunks'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'upload_asset_chunk',
                'description' => 'Upload one Base64-encoded binary chunk into an existing PlayNexus upload session. URLs are not accepted.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'upload_id' => ['type' => 'string', 'format' => 'uuid'],
                        'chunk_index' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 999],
                        'data_base64' => ['type' => 'string', 'description' => 'Raw Base64 bytes without a data-URL prefix.'],
                    ],
                    'required' => ['upload_id', 'chunk_index', 'data_base64'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'complete_asset_upload',
                'description' => 'Verify size, SHA-256 and real MIME, assemble all chunks, store the binary, and attach it to the target record.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'upload_id' => ['type' => 'string', 'format' => 'uuid'],
                    ],
                    'required' => ['upload_id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'abort_asset_upload',
                'description' => 'Discard an incomplete temporary binary upload session.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'upload_id' => ['type' => 'string', 'format' => 'uuid'],
                    ],
                    'required' => ['upload_id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'list_content_assets',
                'description' => 'List current media slots and general file attachments for one PlayNexus content record.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $mediaResourceEnum],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['resource', 'id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'remove_content_asset',
                'description' => 'Remove a media slot, feed-media item, or general attachment. asset_id is required for feed media and attachments; destructive permission is required.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $mediaResourceEnum],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'slot' => ['type' => 'string', 'enum' => $mediaSlotEnum],
                        'asset_id' => ['type' => ['integer', 'null'], 'minimum' => 1],
                    ],
                    'required' => ['resource', 'id', 'slot'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'sync_collection_videos',
                'description' => 'Replace the ordered videos in a collection. video_ids order becomes playlist position.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_id' => ['type' => 'integer', 'minimum' => 1],
                        'video_ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1], 'maxItems' => 500, 'uniqueItems' => true],
                    ],
                    'required' => ['collection_id', 'video_ids'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'set_content_state',
                'description' => 'Explicitly change public/private or active/draft state for games, studios, collections, stories and videos. Feed publishing is intentionally excluded; use publish_feed. Public/active/published transitions require server-side publishing permission.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => ['game', 'studio', 'collection', 'feed', 'story', 'video']],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'state' => ['type' => 'string', 'maxLength' => 30, 'description' => 'game/studio: active|inactive; collection: public|private; story/video: published|draft; feed: draft only'],
                    ],
                    'required' => ['resource', 'id', 'state'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => true],
            ],
            [
                'name' => 'publish_feed',
                'description' => 'Publish an existing PlayNexus feed. Use only after an explicit user request. Server-side publishing must be enabled.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => true],
            ],
            [
                'name' => 'unpublish_feed',
                'description' => 'Return a published PlayNexus feed to draft and clear published_at.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'delete_content',
                'description' => 'Delete a mutable content record. Games/studios use soft delete; collections/feeds/stories/videos are removed and their owned media is cleaned up. Requires destructive operations to be enabled server-side.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => $mutableResourceEnum],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['resource', 'id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'restore_content',
                'description' => 'Restore a soft-deleted game or studio. Requires destructive operations to be enabled server-side.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => ['type' => 'string', 'enum' => ['game', 'studio']],
                        'id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['resource', 'id'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
        ];
    }

    private function searchTool(string $name, string $description): array
    {
        return [
            'name' => $name,
            'description' => $description,
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
        ];
    }

    private function instructions(): string
    {
        return 'PlayNexus Content Admin MCP v2.2. Structured Game Events are first-class intelligence records: create/update them as candidates, then use the dedicated state tool to activate or dismiss them. Search/select/get before mutating records. Creation defaults remain safe: feeds/stories/videos=draft, games/studios=inactive, collections=private. Editing never changes publication state. Binary media/files use the dedicated chunked asset tools; the MCP never fetches arbitrary remote URLs. Use dedicated state/publish tools only after an explicit user request. Raw SQL, shell execution, unrestricted filesystem access, secrets and arbitrary code execution are intentionally not exposed.';
    }

    private function serverInfo(): array
    {
        return [
            'name' => 'playnexus-content-agent',
            'title' => 'PlayNexus Content Admin Agent',
            'version' => '2.2.0',
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
