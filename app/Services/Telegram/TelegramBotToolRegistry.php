<?php

namespace App\Services\Telegram;

use App\Services\ContentAgentMediaService;
use App\Services\ContentAgentService;
use App\Services\GraphQL\PlayNexusGraphService;
use RuntimeException;

final class TelegramBotToolRegistry
{
    public function __construct(
        private readonly ContentAgentService $content,
        private readonly ContentAgentMediaService $media,
        private readonly PlayNexusGraphService $graph,
    ) {}

    public function names(): array
    {
        return array_keys($this->definitions());
    }

    public function definition(string $name): array
    {
        $definition = $this->definitions()[$name] ?? null;
        if (! $definition) {
            throw new RuntimeException("Unknown Telegram PlayNexus tool: {$name}");
        }

        return $definition;
    }

    public function execute(string $name, array $arguments): array
    {
        $this->definition($name);

        return match ($name) {
            'describe_playnexus_graph' => $this->graph->describe(),
            'query_playnexus_graph' => $this->graph->execute(
                (string) ($arguments['query'] ?? ''),
                is_array($arguments['variables'] ?? null) ? $arguments['variables'] : [],
                isset($arguments['operation_name']) ? (string) $arguments['operation_name'] : null,
            ),
            'search_games' => $this->content->searchGames($arguments),
            'search_studios' => $this->content->searchStudios($arguments),
            'search_platforms' => $this->content->searchPlatforms($arguments),
            'search_collections' => $this->content->searchCollections($arguments),
            'list_game_events' => $this->content->listGameEvents($arguments),
            'upsert_game_event' => $this->content->upsertGameEvent($arguments),
            'set_game_event_state' => $this->content->setGameEventState($arguments),
            'select_content' => $this->content->selectContent($arguments),
            'get_content' => $this->content->getContent($arguments),
            'create_game' => $this->content->createGame($arguments),
            'create_studio' => $this->content->createStudio($arguments),
            'create_collection' => $this->content->createCollection($arguments),
            'create_story' => $this->content->createStory($arguments),
            'create_video' => $this->content->createVideo($arguments),
            'get_feed' => $this->content->getFeed($arguments),
            'create_feed' => $this->content->createFeed($arguments),
            'update_content' => $this->content->updateContent($arguments),
            'update_feed' => $this->content->updateFeed($arguments),
            'sync_collection_videos' => $this->content->syncCollectionVideos($arguments),
            'set_content_state' => $this->content->setContentState($arguments),
            'publish_feed' => $this->content->publishFeed($arguments),
            'unpublish_feed' => $this->content->unpublishFeed($arguments),
            'delete_content' => $this->content->deleteContent($arguments),
            'restore_content' => $this->content->restoreContent($arguments),
            'start_asset_upload' => $this->media->startUpload($arguments),
            'upload_asset_chunk' => $this->media->uploadChunk($arguments),
            'complete_asset_upload' => $this->media->completeUpload($arguments),
            'abort_asset_upload' => $this->media->abortUpload($arguments),
            'list_content_assets' => $this->media->listContentAssets($arguments),
            'remove_content_asset' => $this->media->removeContentAsset($arguments),
        };
    }

    private function definitions(): array
    {
        $read = [
            'describe_playnexus_graph',
            'query_playnexus_graph',
            'search_games',
            'search_studios',
            'search_platforms',
            'search_collections',
            'list_game_events',
            'select_content',
            'get_content',
            'get_feed',
            'list_content_assets',
        ];

        $publish = [
            'set_game_event_state',
            'set_content_state',
            'publish_feed',
        ];

        $destructive = [
            'delete_content',
            'remove_content_asset',
        ];

        $media = [
            'start_asset_upload',
            'upload_asset_chunk',
            'complete_asset_upload',
            'abort_asset_upload',
        ];

        $definitions = [];
        foreach ($read as $name) {
            $definitions[$name] = ['kind' => 'read', 'confirm' => false];
        }
        foreach ($publish as $name) {
            $definitions[$name] = ['kind' => 'publish', 'confirm' => true];
        }
        foreach ($destructive as $name) {
            $definitions[$name] = ['kind' => 'destructive', 'confirm' => true];
        }
        foreach ($media as $name) {
            $definitions[$name] = ['kind' => 'media', 'confirm' => true];
        }

        foreach ([
            'upsert_game_event',
            'create_game',
            'create_studio',
            'create_collection',
            'create_story',
            'create_video',
            'create_feed',
            'update_content',
            'update_feed',
            'sync_collection_videos',
            'unpublish_feed',
            'restore_content',
        ] as $name) {
            $definitions[$name] = ['kind' => 'write', 'confirm' => true];
        }

        return $definitions;
    }
}
