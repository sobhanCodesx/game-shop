<?php

namespace App\Services;

use App\Models\ContentAsset;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\User;
use App\Models\VideoPlaylist;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class ContentAgentService
{
    public function __construct(
        private readonly GameEventService $gameEvents,
    ) {}

    private const FEED_BADGES = [
        'breaking', 'news', 'trailer', 'gameplay', 'update', 'rumor', 'review', 'patch_notes',
    ];

    private const RESOURCES = [
        'game', 'studio', 'platform', 'collection', 'feed', 'story', 'video', 'product',
    ];

    private const MUTABLE_RESOURCES = [
        'game', 'studio', 'collection', 'feed', 'story', 'video',
    ];

    public function listGameEvents(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'type' => ['sometimes', 'nullable', Rule::in(GameEventImportanceService::TYPES)],
            'status' => ['sometimes', 'nullable', Rule::in(['candidate', 'active', 'dismissed'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:10000'],
        ]);

        $query = GameEvent::query()
            ->with('game:id,name,slug')
            ->when(! empty($data['game_id']), fn ($query) => $query->where('game_id', (int) $data['game_id']))
            ->when(! empty($data['type']), fn ($query) => $query->where('type', $data['type']))
            ->when(! empty($data['status']), fn ($query) => $query->where('status', $data['status']));

        $total = (clone $query)->count();
        $limit = (int) ($data['limit'] ?? 20);
        $offset = (int) ($data['offset'] ?? 0);
        $items = $query->latest('detected_at')->latest('id')->offset($offset)->limit($limit)->get()
            ->map(fn (GameEvent $event) => $this->gameEvents->serializeForAgent($event))
            ->values()
            ->all();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $offset + count($items) < $total,
            ],
        ];
    }

    public function upsertGameEvent(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'id' => ['sometimes', 'integer', 'min:1', Rule::exists('game_events', 'id')],
            'game_id' => ['required_without:id', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'type' => ['required_without:id', Rule::in(GameEventImportanceService::TYPES)],
            'title' => ['required_without:id', 'string', 'max:200'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'source_type' => ['sometimes', 'string', 'max:32'],
            'source_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'source_url' => ['sometimes', 'nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && ! str_starts_with($value, '/') && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('Event source_url must start with / or be a valid absolute URL.');
                }
            }],
            'external_id' => ['sometimes', 'nullable', 'string', 'max:190'],
            'dedupe_key' => ['sometimes', 'nullable', 'string', 'max:190'],
            'importance_score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'confidence' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'old_value' => ['sometimes', 'nullable', 'array'],
            'new_value' => ['sometimes', 'nullable', 'array'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'detected_at' => ['sometimes', 'date'],
            'effective_at' => ['sometimes', 'nullable', 'date'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if (! empty($data['id'])) {
            $event = GameEvent::query()->findOrFail((int) $data['id']);
            unset($data['id']);

            foreach ($data as $field => $value) {
                $event->{$field} = $value;
            }
            $event->save();

            return $this->gameEvents->serializeForAgent($event->fresh());
        }

        unset($data['id']);
        $data['status'] = 'candidate';
        $event = $this->gameEvents->upsert($data);

        return $this->gameEvents->serializeForAgent($event);
    }

    public function setGameEventState(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1', Rule::exists('game_events', 'id')],
            'state' => ['required', Rule::in(['candidate', 'active', 'dismissed'])],
        ]);

        if ($data['state'] === 'active') {
            $this->ensurePublishingAllowed();
        }

        $event = GameEvent::query()->findOrFail((int) $data['id']);
        $event->status = $data['state'];
        $event->save();

        return $this->gameEvents->serializeForAgent($event->fresh());
    }

    public function searchGames(array $arguments): array
    {
        return $this->selectContent([
            'resource' => 'game',
            'query' => $arguments['query'] ?? '',
            'limit' => $arguments['limit'] ?? 10,
        ])['items'];
    }

    public function searchStudios(array $arguments): array
    {
        return $this->selectContent([
            'resource' => 'studio',
            'query' => $arguments['query'] ?? '',
            'limit' => $arguments['limit'] ?? 10,
        ])['items'];
    }

    public function searchPlatforms(array $arguments): array
    {
        return $this->selectContent([
            'resource' => 'platform',
            'query' => $arguments['query'] ?? null,
            'status' => 'active',
            'limit' => $arguments['limit'] ?? 20,
        ])['items'];
    }

    public function searchCollections(array $arguments): array
    {
        return $this->selectContent([
            'resource' => 'collection',
            'query' => $arguments['query'] ?? '',
            'limit' => $arguments['limit'] ?? 10,
        ])['items'];
    }

    public function selectContent(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::RESOURCES)],
            'query' => ['sometimes', 'nullable', 'string', 'max:160'],
            'ids' => ['sometimes', 'array', 'max:100'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
            'status' => ['sometimes', 'nullable', 'string', 'max:40'],
            'game_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'studio_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'include_deleted' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'order_by' => ['sometimes', Rule::in([
                'id', 'name', 'title', 'created_at', 'updated_at', 'published_at',
                'release_date', 'sort_order', 'status',
            ])],
            'order_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);

        $resource = $data['resource'];
        $builder = $this->resourceQuery($resource, (bool) ($data['include_deleted'] ?? false));

        if (! empty($data['ids'])) {
            $builder->whereIn($this->resourceTable($resource).'.id', $data['ids']);
        }

        $query = trim((string) ($data['query'] ?? ''));
        if ($query !== '') {
            $this->applyTextSearch($builder, $resource, $query);
        }

        if (array_key_exists('status', $data) && $data['status'] !== null && $data['status'] !== '') {
            $column = $resource === 'collection' ? 'visibility' : 'status';
            $builder->where($this->resourceTable($resource).'.'.$column, $data['status']);
        }

        if (! empty($data['game_id'])) {
            $this->applyGameFilter($builder, $resource, (int) $data['game_id']);
        }

        if (! empty($data['studio_id'])) {
            $this->applyStudioFilter($builder, $resource, (int) $data['studio_id']);
        }

        $total = (clone $builder)->count();
        $limit = (int) ($data['limit'] ?? 20);
        $offset = (int) ($data['offset'] ?? 0);
        $orderBy = $this->safeOrderBy($resource, (string) ($data['order_by'] ?? 'id'));
        $direction = (string) ($data['order_dir'] ?? 'desc');

        $items = $builder
            ->orderBy($orderBy, $direction)
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($model) => $this->serializeResource($resource, $model))
            ->values()
            ->all();

        return [
            'resource' => $resource,
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => $offset + count($items) < $total,
            ],
        ];
    }

    public function getContent(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
            'include_deleted' => ['sometimes', 'boolean'],
        ]);

        $model = $this->resourceQuery($data['resource'], (bool) ($data['include_deleted'] ?? false))
            ->where($this->resourceTable($data['resource']).'.id', (int) $data['id'])
            ->firstOrFail();

        return $this->serializeResource($data['resource'], $model);
    }

    public function createGame(array $arguments): array
    {
        $data = $this->validateGame($arguments, creating: true);

        $game = Game::query()->create([
            'studio_id' => $data['studio_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->uniqueSlugFor(Game::class, $data['name'], 'game'),
            'developer' => $data['developer'] ?? null,
            'publisher' => $data['publisher'] ?? null,
            'release_date' => $data['release_date'] ?? null,
            'age_rating' => $data['age_rating'] ?? null,
            'description' => RichText::sanitize($data['description'] ?? null),
            'status' => 'inactive',
        ]);

        if (array_key_exists('platform_ids', $data)) {
            $game->platforms()->sync($data['platform_ids']);
        }

        return $this->serializeGame($game->fresh());
    }

    public function createStudio(array $arguments): array
    {
        $data = $this->validateStudio($arguments, creating: true);

        $studio = Studio::query()->create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlugFor(Studio::class, $data['name'], 'studio'),
            'description' => RichText::sanitize($data['description'] ?? null),
            'website' => $data['website'] ?? null,
            'status' => 'inactive',
        ]);

        return $this->serializeStudio($studio);
    }

    public function createCollection(array $arguments): array
    {
        $data = $this->validateCollection($arguments, creating: true);

        $collection = VideoPlaylist::query()->create([
            'game_id' => $data['game_id'] ?? null,
            'studio_id' => $data['studio_id'] ?? null,
            'title' => $data['title'],
            'slug' => $this->uniqueSlugFor(VideoPlaylist::class, $data['title'], 'collection'),
            'description' => RichText::sanitize($data['description'] ?? null),
            'visibility' => 'private',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        return $this->serializeCollection($collection);
    }

    public function createStory(array $arguments): array
    {
        $data = $this->validateStory($arguments, creating: true);

        $story = SocialContent::query()->create([
            'user_id' => $this->authorUserId(),
            'game_id' => $data['game_id'] ?? null,
            'type' => 'short',
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title'], 'story'),
            'excerpt' => $data['excerpt'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'link_label' => $data['link_label'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'status' => 'draft',
            'published_at' => null,
        ]);

        return $this->serializeStory($story);
    }

    public function createVideo(array $arguments): array
    {
        $data = $this->validateVideo($arguments, creating: true);

        $video = SocialContent::query()->create([
            'user_id' => $this->authorUserId(),
            'game_id' => $data['game_id'] ?? null,
            'type' => 'video',
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title'], 'video'),
            'excerpt' => RichText::plainText($data['excerpt'] ?? null),
            'body' => RichText::sanitize($data['body'] ?? null),
            'seo_title' => filled($data['seo_title'] ?? null) ? trim((string) $data['seo_title']) : null,
            'seo_description' => filled($data['seo_description'] ?? null) ? trim((string) $data['seo_description']) : null,
            'featured' => (bool) ($data['featured'] ?? false),
            'allow_comments' => (bool) ($data['allow_comments'] ?? true),
            'status' => 'draft',
            'published_at' => null,
        ]);

        if (array_key_exists('playlist_ids', $data)) {
            $video->playlists()->sync($this->playlistSync($data['playlist_ids']));
        }

        return $this->serializeVideo($video->fresh());
    }

    public function getFeed(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->serializeFeed($this->findFeed((int) $data['id']));
    }

    public function createFeed(array $arguments): array
    {
        $data = $this->validateFeed($arguments, creating: true);
        $body = RichText::sanitize($data['body'] ?? null);

        $feed = SocialContent::query()->create([
            'user_id' => $this->authorUserId(),
            'game_id' => $data['game_id'] ?? null,
            'related_product_id' => $data['related_product_id'] ?? null,
            'related_content_id' => $data['related_content_id'] ?? null,
            'type' => 'post',
            'feed_type' => $data['feed_type'] ?? 'post',
            'feed_badge' => $data['feed_badge'] ?? null,
            'title' => $data['title'],
            'slug' => $this->uniqueSlug($data['title']),
            'excerpt' => Str::limit((string) RichText::plainText($body), 500, '…') ?: null,
            'body' => $body,
            'seo_title' => $data['seo_title'] ?? null,
            'seo_description' => $data['seo_description'] ?? null,
            'allow_comments' => $data['allow_comments'] ?? true,
            'notify_followers' => $data['notify_followers'] ?? false,
            'status' => 'draft',
            'published_at' => null,
        ]);

        return $this->serializeFeed($feed);
    }

    public function updateContent(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::MUTABLE_RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
            'data' => ['required', 'array'],
        ]);

        $payload = ['id' => (int) $data['id'], ...$data['data']];

        return match ($data['resource']) {
            'game' => $this->updateGame($payload),
            'studio' => $this->updateStudio($payload),
            'collection' => $this->updateCollection($payload),
            'feed' => $this->updateFeed($payload),
            'story' => $this->updateStory($payload),
            'video' => $this->updateVideo($payload),
        };
    }

    public function updateGame(array $arguments): array
    {
        $game = Game::query()->findOrFail((int) ($arguments['id'] ?? 0));
        $data = $this->validateGame($arguments, creating: false);

        foreach (['studio_id', 'name', 'developer', 'publisher', 'release_date', 'age_rating'] as $field) {
            if (array_key_exists($field, $data)) {
                $game->{$field} = $data[$field];
            }
        }

        if (array_key_exists('description', $data)) {
            $game->description = RichText::sanitize($data['description']);
        }

        $game->save();

        if (array_key_exists('platform_ids', $data)) {
            $game->platforms()->sync($data['platform_ids']);
        }

        return $this->serializeGame($game->fresh());
    }

    public function updateStudio(array $arguments): array
    {
        $studio = Studio::query()->findOrFail((int) ($arguments['id'] ?? 0));
        $data = $this->validateStudio($arguments, creating: false);

        foreach (['name', 'website'] as $field) {
            if (array_key_exists($field, $data)) {
                $studio->{$field} = $data[$field];
            }
        }

        if (array_key_exists('description', $data)) {
            $studio->description = RichText::sanitize($data['description']);
        }

        $studio->save();

        return $this->serializeStudio($studio->fresh());
    }

    public function updateCollection(array $arguments): array
    {
        $collection = VideoPlaylist::query()->findOrFail((int) ($arguments['id'] ?? 0));
        $data = $this->validateCollection($arguments, creating: false);

        foreach (['game_id', 'studio_id', 'title', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $collection->{$field} = $data[$field];
            }
        }

        if (array_key_exists('description', $data)) {
            $collection->description = RichText::sanitize($data['description']);
        }

        $collection->save();

        return $this->serializeCollection($collection->fresh());
    }

    public function updateStory(array $arguments): array
    {
        $story = $this->findSocialContent((int) ($arguments['id'] ?? 0), 'short');
        $data = $this->validateStory($arguments, creating: false);

        foreach (['title', 'excerpt', 'game_id', 'link_url', 'link_label', 'sort_order'] as $field) {
            if (array_key_exists($field, $data)) {
                $story->{$field} = $data[$field];
            }
        }

        $story->save();

        return $this->serializeStory($story->fresh());
    }

    public function updateVideo(array $arguments): array
    {
        $video = $this->findSocialContent((int) ($arguments['id'] ?? 0), 'video');
        $data = $this->validateVideo($arguments, creating: false);

        foreach (['title', 'game_id', 'featured', 'allow_comments'] as $field) {
            if (array_key_exists($field, $data)) {
                $video->{$field} = $data[$field];
            }
        }

        if (array_key_exists('excerpt', $data)) {
            $video->excerpt = RichText::plainText($data['excerpt']);
        }
        if (array_key_exists('body', $data)) {
            $video->body = RichText::sanitize($data['body']);
        }
        if (array_key_exists('seo_title', $data)) {
            $video->seo_title = filled($data['seo_title']) ? trim((string) $data['seo_title']) : null;
        }
        if (array_key_exists('seo_description', $data)) {
            $video->seo_description = filled($data['seo_description']) ? trim((string) $data['seo_description']) : null;
        }

        $video->save();

        if (array_key_exists('playlist_ids', $data)) {
            $video->playlists()->sync($this->playlistSync($data['playlist_ids']));
        }

        return $this->serializeVideo($video->fresh());
    }

    public function updateFeed(array $arguments): array
    {
        $id = (int) ($arguments['id'] ?? 0);
        $feed = $this->findFeed($id);
        $data = $this->validateFeed($arguments, creating: false);

        foreach ([
            'title', 'feed_type', 'feed_badge', 'game_id', 'related_product_id', 'related_content_id',
            'seo_title', 'seo_description', 'allow_comments', 'notify_followers',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $feed->{$field} = $data[$field];
            }
        }

        if (array_key_exists('body', $data)) {
            $body = RichText::sanitize($data['body']);
            $feed->body = $body;
            $feed->excerpt = Str::limit((string) RichText::plainText($body), 500, '…') ?: null;
        }

        $feed->save();

        return $this->serializeFeed($feed->fresh());
    }

    public function syncCollectionVideos(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'collection_id' => ['required', 'integer', Rule::exists('video_playlists', 'id')],
            'video_ids' => ['required', 'array', 'max:500'],
            'video_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('social_contents', 'id')->where('type', 'video'),
            ],
        ]);

        $collection = VideoPlaylist::query()->findOrFail((int) $data['collection_id']);
        $collection->videos()->sync($this->playlistSync($data['video_ids']));

        return $this->serializeCollection($collection->fresh());
    }

    public function setContentState(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(['game', 'studio', 'collection', 'feed', 'story', 'video'])],
            'id' => ['required', 'integer', 'min:1'],
            'state' => ['required', 'string', 'max:30'],
        ]);

        $resource = $data['resource'];
        $state = $data['state'];

        if ($resource === 'feed') {
            if ($state === 'published') {
                throw new RuntimeException('Use publish_feed to publish feeds.');
            }
            if ($state !== 'draft') {
                throw new RuntimeException('Feed state must be draft, or use publish_feed.');
            }

            return $this->unpublishFeed(['id' => $data['id']]);
        }

        return match ($resource) {
            'game' => $this->setGameStatus((int) $data['id'], $state),
            'studio' => $this->setStudioStatus((int) $data['id'], $state),
            'collection' => $this->setCollectionVisibility((int) $data['id'], $state),
            'story' => $this->setSocialContentStatus((int) $data['id'], 'short', $state),
            'video' => $this->setSocialContentStatus((int) $data['id'], 'video', $state),
        };
    }

    public function publishFeed(array $arguments): array
    {
        $this->ensurePublishingAllowed();

        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $feed = $this->findFeed((int) $data['id']);
        $feed->status = 'published';
        $feed->published_at ??= now();
        $feed->save();

        return $this->serializeFeed($feed->fresh());
    }

    public function unpublishFeed(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $feed = $this->findFeed((int) $data['id']);
        $feed->status = 'draft';
        $feed->published_at = null;
        $feed->save();

        return $this->serializeFeed($feed->fresh());
    }

    public function deleteContent(array $arguments): array
    {
        $this->ensureDestructiveAllowed();

        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(self::MUTABLE_RESOURCES)],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $resource = $data['resource'];
        $id = (int) $data['id'];

        if ($resource === 'game') {
            $model = Game::query()->findOrFail($id);
            $model->delete();

            return ['resource' => $resource, 'id' => $id, 'deleted' => true, 'soft_deleted' => true];
        }

        if ($resource === 'studio') {
            $model = Studio::query()->findOrFail($id);
            $model->delete();

            return ['resource' => $resource, 'id' => $id, 'deleted' => true, 'soft_deleted' => true];
        }

        if ($resource === 'collection') {
            $model = VideoPlaylist::query()->findOrFail($id);
            $logo = $model->logo;
            $this->deleteContentAttachments($resource, $id);
            $model->videos()->detach();
            $model->delete();
            if ($logo) {
                MediaStorage::disk()->delete($logo);
            }

            return ['resource' => $resource, 'id' => $id, 'deleted' => true, 'soft_deleted' => false];
        }

        $type = match ($resource) {
            'feed' => 'post',
            'story' => 'short',
            'video' => 'video',
        };
        $model = $this->findSocialContent($id, $type);
        $this->deleteContentAttachments($resource, $id);
        $this->deleteSocialContentFiles($model);
        $model->playlists()->detach();
        $model->delete();

        return ['resource' => $resource, 'id' => $id, 'deleted' => true, 'soft_deleted' => false];
    }

    public function restoreContent(array $arguments): array
    {
        $this->ensureDestructiveAllowed();

        $data = $this->validate($arguments, [
            'resource' => ['required', Rule::in(['game', 'studio'])],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        if ($data['resource'] === 'game') {
            $model = Game::withTrashed()->findOrFail((int) $data['id']);
            $model->restore();

            return $this->serializeGame($model->fresh());
        }

        $model = Studio::withTrashed()->findOrFail((int) $data['id']);
        $model->restore();

        return $this->serializeStudio($model->fresh());
    }

    private function setGameStatus(int $id, string $state): array
    {
        if (! in_array($state, ['active', 'inactive'], true)) {
            throw new RuntimeException('Game state must be active or inactive.');
        }
        if ($state === 'active') {
            $this->ensurePublishingAllowed();
        }

        $game = Game::query()->findOrFail($id);
        $game->status = $state;
        $game->save();

        return $this->serializeGame($game->fresh());
    }

    private function setStudioStatus(int $id, string $state): array
    {
        if (! in_array($state, ['active', 'inactive'], true)) {
            throw new RuntimeException('Studio state must be active or inactive.');
        }
        if ($state === 'active') {
            $this->ensurePublishingAllowed();
        }

        $studio = Studio::query()->findOrFail($id);
        $studio->status = $state;
        $studio->save();

        return $this->serializeStudio($studio->fresh());
    }

    private function setCollectionVisibility(int $id, string $state): array
    {
        if (! in_array($state, ['public', 'private'], true)) {
            throw new RuntimeException('Collection state must be public or private.');
        }
        if ($state === 'public') {
            $this->ensurePublishingAllowed();
        }

        $collection = VideoPlaylist::query()->findOrFail($id);
        $collection->visibility = $state;
        $collection->save();

        return $this->serializeCollection($collection->fresh());
    }

    private function setSocialContentStatus(int $id, string $type, string $state): array
    {
        if (! in_array($state, ['draft', 'published'], true)) {
            throw new RuntimeException('Content state must be draft or published.');
        }
        if ($state === 'published') {
            $this->ensurePublishingAllowed();
        }

        $content = $this->findSocialContent($id, $type);
        $content->status = $state;
        $content->published_at = $state === 'published' ? ($content->published_at ?? now()) : null;
        $content->save();

        return $type === 'short'
            ? $this->serializeStory($content->fresh())
            : $this->serializeVideo($content->fresh());
    }

    private function validateGame(array $arguments, bool $creating): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'studio_id' => ['sometimes', 'nullable', 'integer', Rule::exists('studios', 'id')->whereNull('deleted_at')],
            'developer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'publisher' => ['sometimes', 'nullable', 'string', 'max:255'],
            'release_date' => ['sometimes', 'nullable', 'date'],
            'age_rating' => ['sometimes', 'nullable', 'string', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'platform_ids' => ['sometimes', 'array', 'max:30'],
            'platform_ids.*' => ['integer', 'distinct', Rule::exists('platforms', 'id')->whereNull('deleted_at')],
        ];
        if (! $creating) {
            $rules['id'] = ['required', 'integer', 'min:1'];
        }

        return $this->validate($arguments, $rules);
    }

    private function validateStudio(array $arguments, bool $creating): array
    {
        $rules = [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
        if (! $creating) {
            $rules['id'] = ['required', 'integer', 'min:1'];
        }

        return $this->validate($arguments, $rules);
    }

    private function validateCollection(array $arguments, bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'studio_id' => ['sometimes', 'nullable', 'integer', Rule::exists('studios', 'id')->whereNull('deleted_at')],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
        if (! $creating) {
            $rules['id'] = ['required', 'integer', 'min:1'];
        }

        return $this->validate($arguments, $rules);
    }

    private function validateStory(array $arguments, bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:240'],
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'link_url' => ['sometimes', 'nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && ! str_starts_with($value, '/') && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('Story link must start with / or be a valid absolute URL.');
                }
            }],
            'link_label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ];
        if (! $creating) {
            $rules['id'] = ['required', 'integer', 'min:1'];
        }

        return $this->validate($arguments, $rules);
    }

    private function validateVideo(array $arguments, bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:160'],
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'playlist_ids' => ['sometimes', 'array', 'max:100'],
            'playlist_ids.*' => ['integer', 'distinct', Rule::exists('video_playlists', 'id')],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:500'],
            'body' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'featured' => ['sometimes', 'boolean'],
            'allow_comments' => ['sometimes', 'boolean'],
        ];
        if (! $creating) {
            $rules['id'] = ['required', 'integer', 'min:1'];
        }

        return $this->validate($arguments, $rules);
    }

    private function validateFeed(array $arguments, bool $creating): array
    {
        $titleRule = [$creating ? 'required' : 'sometimes', 'string', 'max:160'];

        $rules = [
            'title' => $titleRule,
            'body' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'feed_type' => ['sometimes', Rule::in(FeedService::TYPES)],
            'feed_badge' => ['sometimes', 'nullable', Rule::in(self::FEED_BADGES)],
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'related_product_id' => ['sometimes', 'nullable', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'related_content_id' => ['sometimes', 'nullable', 'integer', Rule::exists('social_contents', 'id')->where('type', 'video')],
            'allow_comments' => ['sometimes', 'boolean'],
            'notify_followers' => ['sometimes', 'boolean'],
            'seo_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'seo_description' => ['sometimes', 'nullable', 'string', 'max:160'],
        ];

        if (! $creating) {
            $rules['id'] = ['required', 'integer', 'min:1'];
        }

        return $this->validate($arguments, $rules);
    }

    private function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }

    private function resourceQuery(string $resource, bool $includeDeleted = false): Builder
    {
        $query = match ($resource) {
            'game' => Game::query()->with(['studio:id,name,slug', 'platforms:id,name,slug']),
            'studio' => Studio::query()->withCount('games'),
            'platform' => Platform::query(),
            'collection' => VideoPlaylist::query()->with(['game:id,name,slug', 'studio:id,name,slug'])->withCount('videos'),
            'feed' => SocialContent::query()->where('type', 'post')->with(['game:id,name,slug', 'media']),
            'story' => SocialContent::query()->where('type', 'short')->with('game:id,name,slug'),
            'video' => SocialContent::query()->where('type', 'video')->with(['game:id,name,slug', 'playlists:id,game_id,studio_id,title,slug']),
            'product' => Product::query()->with('game:id,name,slug'),
        };

        if ($includeDeleted && in_array($resource, ['game', 'studio', 'platform', 'product'], true)) {
            $query->withTrashed();
        }

        return $query;
    }

    private function resourceTable(string $resource): string
    {
        return match ($resource) {
            'game' => 'games',
            'studio' => 'studios',
            'platform' => 'platforms',
            'collection' => 'video_playlists',
            'feed', 'story', 'video' => 'social_contents',
            'product' => 'products',
        };
    }

    private function applyTextSearch(Builder $builder, string $resource, string $query): void
    {
        match ($resource) {
            'game' => $builder->where(function ($nested) use ($query): void {
                $nested->where('games.name', 'like', "%{$query}%")
                    ->orWhere('games.developer', 'like', "%{$query}%")
                    ->orWhere('games.publisher', 'like', "%{$query}%")
                    ->orWhere('games.slug', 'like', "%{$query}%");
            }),
            'studio' => $builder->where(function ($nested) use ($query): void {
                $nested->where('studios.name', 'like', "%{$query}%")
                    ->orWhere('studios.slug', 'like', "%{$query}%");
            }),
            'platform' => $builder->where(function ($nested) use ($query): void {
                $nested->where('platforms.name', 'like', "%{$query}%")
                    ->orWhere('platforms.manufacturer', 'like', "%{$query}%")
                    ->orWhere('platforms.slug', 'like', "%{$query}%");
            }),
            'collection' => $builder->where(function ($nested) use ($query): void {
                $nested->where('video_playlists.title', 'like', "%{$query}%")
                    ->orWhere('video_playlists.slug', 'like', "%{$query}%");
            }),
            'feed', 'story', 'video' => $builder->where(function ($nested) use ($query): void {
                $nested->where('social_contents.title', 'like', "%{$query}%")
                    ->orWhere('social_contents.slug', 'like', "%{$query}%")
                    ->orWhere('social_contents.excerpt', 'like', "%{$query}%");
            }),
            'product' => $builder->where(function ($nested) use ($query): void {
                $nested->where('products.title', 'like', "%{$query}%")
                    ->orWhere('products.sku', 'like', "%{$query}%")
                    ->orWhere('products.slug', 'like', "%{$query}%");
            }),
        };
    }

    private function applyGameFilter(Builder $builder, string $resource, int $gameId): void
    {
        match ($resource) {
            'game' => $builder->where('games.id', $gameId),
            'collection' => $builder->where('video_playlists.game_id', $gameId),
            'feed', 'story', 'video' => $builder->where('social_contents.game_id', $gameId),
            'product' => $builder->where('products.game_id', $gameId),
            default => null,
        };
    }

    private function applyStudioFilter(Builder $builder, string $resource, int $studioId): void
    {
        match ($resource) {
            'studio' => $builder->where('studios.id', $studioId),
            'game' => $builder->where('games.studio_id', $studioId),
            'collection' => $builder->where(function ($nested) use ($studioId): void {
                $nested->where('video_playlists.studio_id', $studioId)
                    ->orWhereHas('game', fn ($game) => $game->where('studio_id', $studioId));
            }),
            'feed', 'story', 'video' => $builder->whereHas('game', fn ($game) => $game->where('studio_id', $studioId)),
            'product' => $builder->whereHas('game', fn ($game) => $game->where('studio_id', $studioId)),
            default => null,
        };
    }

    private function safeOrderBy(string $resource, string $requested): string
    {
        $allowed = match ($resource) {
            'game' => ['id', 'name', 'created_at', 'updated_at', 'release_date', 'status'],
            'studio' => ['id', 'name', 'created_at', 'updated_at', 'status'],
            'platform' => ['id', 'name', 'created_at', 'updated_at', 'sort_order', 'status'],
            'collection' => ['id', 'title', 'created_at', 'updated_at', 'sort_order'],
            'feed', 'story', 'video' => ['id', 'title', 'created_at', 'updated_at', 'published_at', 'sort_order', 'status'],
            'product' => ['id', 'title', 'created_at', 'updated_at', 'published_at', 'release_date', 'status'],
        };

        $column = in_array($requested, $allowed, true) ? $requested : 'id';

        return $this->resourceTable($resource).'.'.$column;
    }

    private function serializeResource(string $resource, mixed $model): array
    {
        return match ($resource) {
            'game' => $this->serializeGame($model),
            'studio' => $this->serializeStudio($model),
            'platform' => $this->serializePlatform($model),
            'collection' => $this->serializeCollection($model),
            'feed' => $this->serializeFeed($model),
            'story' => $this->serializeStory($model),
            'video' => $this->serializeVideo($model),
            'product' => $this->serializeProduct($model),
        };
    }

    private function findFeed(int $id): SocialContent
    {
        return $this->findSocialContent($id, 'post');
    }

    private function findSocialContent(int $id, string $type): SocialContent
    {
        return SocialContent::query()
            ->whereKey($id)
            ->where('type', $type)
            ->firstOrFail();
    }

    private function authorUserId(): int
    {
        $id = (int) config('content_agent.author_user_id');
        if ($id <= 0) {
            throw new RuntimeException('PLAYNEXUS_CONTENT_AGENT_AUTHOR_USER_ID is not configured.');
        }

        $exists = User::query()->whereKey($id)->where('is_admin', true)->exists();
        if (! $exists) {
            throw new RuntimeException('The configured content-agent author must be an existing admin user.');
        }

        return $id;
    }

    private function ensurePublishingAllowed(): void
    {
        if (! (bool) config('content_agent.allow_publish')) {
            throw new RuntimeException('Publishing is disabled. Set PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH=true on the server to enable it.');
        }
    }

    private function ensureDestructiveAllowed(): void
    {
        if (! (bool) config('content_agent.allow_destructive')) {
            throw new RuntimeException('Destructive operations are disabled. Set PLAYNEXUS_CONTENT_AGENT_ALLOW_DESTRUCTIVE=true on the server to enable delete/restore tools.');
        }
    }

    private function deleteContentAttachments(string $resource, int $id): void
    {
        $assets = ContentAsset::query()
            ->where('resource', $resource)
            ->where('resource_id', $id)
            ->get();

        $paths = $assets->pluck('path')->filter()->values()->all();
        ContentAsset::query()
            ->where('resource', $resource)
            ->where('resource_id', $id)
            ->delete();

        if ($paths !== []) {
            MediaStorage::disk()->delete($paths);
        }
    }

    private function deleteSocialContentFiles(SocialContent $content): void
    {
        $content->loadMissing('media');
        $paths = $content->media
            ->flatMap(fn ($media) => [$media->path, $media->thumbnail])
            ->push($content->video_path)
            ->push($content->thumbnail)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $content->media()->delete();

        if ($paths !== []) {
            MediaStorage::disk()->delete($paths);
        }
    }

    private function uniqueSlug(string $title, string $fallback = 'feed-post'): string
    {
        $base = Str::slug($title) ?: $fallback;
        $slug = $base;

        for ($counter = 2; SocialContent::query()->where('slug', $slug)->exists(); $counter++) {
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }

    private function uniqueSlugFor(string $modelClass, string $title, string $fallback): string
    {
        $base = Str::slug($title) ?: $fallback;
        $slug = $base;

        for ($counter = 2; $this->slugExists($modelClass, $slug); $counter++) {
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }

    private function slugExists(string $modelClass, string $slug): bool
    {
        $query = $modelClass::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query->where('slug', $slug)->exists();
    }

    private function playlistSync(array $ids): array
    {
        return collect($ids)
            ->values()
            ->mapWithKeys(fn ($id, $position) => [(int) $id => ['position' => $position]])
            ->all();
    }

    private function serializeGame(Game $game): array
    {
        $game->loadMissing(['studio:id,name,slug', 'platforms:id,name,slug']);

        return [
            'id' => $game->id,
            'name' => $game->name,
            'slug' => $game->slug,
            'studio' => $game->studio?->only(['id', 'name', 'slug']),
            'developer' => $game->developer,
            'publisher' => $game->publisher,
            'release_date' => $game->release_date?->toDateString(),
            'age_rating' => $game->age_rating,
            'description' => $game->description,
            'platforms' => $game->platforms->map->only(['id', 'name', 'slug'])->values()->all(),
            'status' => $game->status,
            'deleted_at' => $game->deleted_at?->toISOString(),
            'cover_url' => MediaStorage::url($game->cover),
            'background_url' => MediaStorage::url($game->background),
            'needs_media' => ! $game->cover,
            'url' => in_array($game->status, ['active', 'published'], true) ? route('channels.show', $game->slug, false) : null,
        ];
    }

    private function serializeStudio(Studio $studio): array
    {
        return [
            'id' => $studio->id,
            'name' => $studio->name,
            'slug' => $studio->slug,
            'description' => $studio->description,
            'website' => $studio->website,
            'status' => $studio->status,
            'games_count' => isset($studio->games_count) ? (int) $studio->games_count : null,
            'deleted_at' => $studio->deleted_at?->toISOString(),
            'logo_url' => MediaStorage::url($studio->logo),
            'background_url' => MediaStorage::url($studio->background),
            'needs_media' => ! $studio->logo || ! $studio->background,
            'url' => $studio->status === 'active' ? route('studios.show', $studio->slug, false) : null,
        ];
    }

    private function serializePlatform(Platform $platform): array
    {
        return [
            'id' => $platform->id,
            'name' => $platform->name,
            'slug' => $platform->slug,
            'manufacturer' => $platform->manufacturer,
            'status' => $platform->status,
            'sort_order' => (int) $platform->sort_order,
            'deleted_at' => $platform->deleted_at?->toISOString(),
            'icon_url' => MediaStorage::url($platform->icon),
        ];
    }

    private function serializeCollection(VideoPlaylist $collection): array
    {
        $collection->loadMissing(['game:id,name,slug', 'studio:id,name,slug']);

        return [
            'id' => $collection->id,
            'title' => $collection->title,
            'slug' => $collection->slug,
            'description' => $collection->description,
            'visibility' => $collection->visibility,
            'sort_order' => (int) $collection->sort_order,
            'game' => $collection->game?->only(['id', 'name', 'slug']),
            'studio' => $collection->studio?->only(['id', 'name', 'slug']),
            'videos_count' => isset($collection->videos_count) ? (int) $collection->videos_count : $collection->videos()->count(),
            'logo_url' => MediaStorage::url($collection->logo),
            'needs_media' => ! $collection->logo,
            'url' => $collection->visibility === 'public' ? route('collections.show', $collection->slug, false) : null,
        ];
    }

    private function serializeStory(SocialContent $story): array
    {
        $story->loadMissing('game:id,name,slug');

        return [
            'id' => $story->id,
            'title' => $story->title,
            'slug' => $story->slug,
            'excerpt' => $story->excerpt,
            'link_url' => $story->link_url,
            'link_label' => $story->link_label,
            'sort_order' => (int) $story->sort_order,
            'game' => $story->game?->only(['id', 'name', 'slug']),
            'status' => $story->status,
            'media_type' => $story->media_type,
            'media_url' => MediaStorage::url($story->video_path),
            'thumbnail_url' => MediaStorage::url($story->thumbnail),
            'needs_media' => ! $story->video_path,
            'published_at' => $story->published_at?->toISOString(),
        ];
    }

    private function serializeVideo(SocialContent $video): array
    {
        $video->loadMissing(['game:id,name,slug', 'playlists:id,game_id,studio_id,title,slug']);

        return [
            'id' => $video->id,
            'title' => $video->title,
            'slug' => $video->slug,
            'excerpt' => $video->excerpt,
            'body' => $video->body,
            'seo_title' => $video->seo_title,
            'seo_description' => $video->seo_description,
            'game' => $video->game?->only(['id', 'name', 'slug']),
            'playlists' => $video->playlists->map->only(['id', 'title', 'slug'])->values()->all(),
            'status' => $video->status,
            'featured' => (bool) $video->featured,
            'allow_comments' => (bool) $video->allow_comments,
            'duration' => $video->duration,
            'views' => (int) $video->views,
            'video_url' => MediaStorage::url($video->video_path),
            'thumbnail_url' => MediaStorage::url($video->thumbnail),
            'needs_media' => ! $video->video_path,
            'published_at' => $video->published_at?->toISOString(),
            'url' => $video->status === 'published' ? route('content.show', ['type' => 'videos', 'content' => $video->slug], false) : null,
        ];
    }

    private function serializeFeed(SocialContent $feed): array
    {
        $feed->loadMissing(['game:id,name,slug', 'media']);

        return [
            'id' => $feed->id,
            'title' => $feed->title,
            'slug' => $feed->slug,
            'body' => $feed->body,
            'excerpt' => $feed->excerpt,
            'feed_type' => $feed->feed_type,
            'feed_badge' => $feed->feed_badge,
            'game' => $feed->game?->only(['id', 'name', 'slug']),
            'related_product_id' => $feed->related_product_id,
            'related_content_id' => $feed->related_content_id,
            'allow_comments' => (bool) $feed->allow_comments,
            'notify_followers' => (bool) $feed->notify_followers,
            'seo_title' => $feed->seo_title,
            'seo_description' => $feed->seo_description,
            'status' => $feed->status,
            'media' => $feed->media->map(fn ($media) => [
                'id' => $media->id,
                'type' => $media->type,
                'url' => MediaStorage::url($media->path),
                'thumbnail_url' => MediaStorage::url($media->thumbnail),
                'alt' => $media->alt,
                'sort_order' => (int) $media->sort_order,
            ])->values()->all(),
            'published_at' => $feed->published_at?->toISOString(),
            'url' => $feed->status === 'published' ? route('posts.show', $feed->slug, false) : null,
        ];
    }

    private function serializeProduct(Product $product): array
    {
        $product->loadMissing('game:id,name,slug');

        return [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'game' => $product->game?->only(['id', 'name', 'slug']),
            'price' => $product->price,
            'discount_price' => $product->discount_price,
            'stock' => $product->stock,
            'status' => $product->status,
            'visibility' => $product->visibility,
            'featured' => (bool) $product->featured,
            'deleted_at' => $product->deleted_at?->toISOString(),
            'published_at' => $product->published_at?->toISOString(),
            'url' => $product->status === 'published' && $product->visibility === 'public'
                ? route('products.show', $product->slug, false)
                : null,
        ];
    }
}
