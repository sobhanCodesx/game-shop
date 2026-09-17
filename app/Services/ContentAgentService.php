<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Platform;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\User;
use App\Models\VideoPlaylist;
use App\Support\RichText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class ContentAgentService
{
    private const FEED_BADGES = [
        'breaking', 'news', 'trailer', 'gameplay', 'update', 'rumor', 'review', 'patch_notes',
    ];

    public function searchGames(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'query' => ['required', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $query = trim($data['query']);
        $limit = (int) ($data['limit'] ?? 10);

        return Game::query()
            ->whereIn('status', ['active', 'published'])
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('developer', 'like', "%{$query}%")
                    ->orWhere('publisher', 'like', "%{$query}%");
            })
            ->with('studio:id,name')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'studio_id', 'name', 'slug', 'developer', 'publisher', 'status'])
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'developer' => $game->developer,
                'publisher' => $game->publisher,
                'status' => $game->status,
                'studio' => $game->studio?->only(['id', 'name']),
            ])
            ->values()
            ->all();
    }

    public function searchStudios(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'query' => ['required', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $query = trim($data['query']);
        $limit = (int) ($data['limit'] ?? 10);

        return Studio::query()
            ->where('status', 'active')
            ->where('name', 'like', "%{$query}%")
            ->withCount('games')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'website', 'status'])
            ->map(fn (Studio $studio) => [
                'id' => $studio->id,
                'name' => $studio->name,
                'slug' => $studio->slug,
                'website' => $studio->website,
                'status' => $studio->status,
                'games_count' => (int) $studio->games_count,
            ])
            ->values()
            ->all();
    }

    public function searchPlatforms(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ]);

        $query = trim((string) ($data['query'] ?? ''));
        $limit = (int) ($data['limit'] ?? 20);

        return Platform::query()
            ->where('status', 'active')
            ->when($query !== '', fn ($builder) => $builder->where(function ($nested) use ($query): void {
                $nested->where('name', 'like', "%{$query}%")
                    ->orWhere('manufacturer', 'like', "%{$query}%");
            }))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'manufacturer'])
            ->toArray();
    }

    public function searchCollections(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'query' => ['required', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $query = trim($data['query']);
        $limit = (int) ($data['limit'] ?? 10);

        return VideoPlaylist::query()
            ->where('visibility', 'public')
            ->where('title', 'like', "%{$query}%")
            ->with(['game:id,name,slug', 'studio:id,name,slug'])
            ->orderBy('title')
            ->limit($limit)
            ->get()
            ->map(fn (VideoPlaylist $collection) => $this->serializeCollection($collection))
            ->values()
            ->all();
    }

    public function createGame(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'studio_id' => ['sometimes', 'nullable', 'integer', Rule::exists('studios', 'id')->whereNull('deleted_at')],
            'developer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'publisher' => ['sometimes', 'nullable', 'string', 'max:255'],
            'release_date' => ['sometimes', 'nullable', 'date'],
            'age_rating' => ['sometimes', 'nullable', 'string', 'max:20'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'platform_ids' => ['sometimes', 'array', 'max:30'],
            'platform_ids.*' => ['integer', 'distinct', Rule::exists('platforms', 'id')->whereNull('deleted_at')],
        ]);

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
        $data = $this->validate($arguments, [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
        ]);

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
        $data = $this->validate($arguments, [
            'title' => ['required', 'string', 'max:160'],
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'studio_id' => ['sometimes', 'nullable', 'integer', Rule::exists('studios', 'id')->whereNull('deleted_at')],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

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
        $data = $this->validate($arguments, [
            'title' => ['required', 'string', 'max:100'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:240'],
            'game_id' => ['sometimes', 'nullable', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'link_url' => ['sometimes', 'nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && ! str_starts_with($value, '/') && ! filter_var($value, FILTER_VALIDATE_URL)) {
                    $fail('Story link must start with / or be a valid absolute URL.');
                }
            }],
            'link_label' => ['sometimes', 'nullable', 'string', 'max:60'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
        ]);

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

    public function publishFeed(array $arguments): array
    {
        if (! (bool) config('content_agent.allow_publish')) {
            throw new RuntimeException('Publishing is disabled. Set PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH=true on the server to enable it.');
        }

        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $feed = $this->findFeed((int) $data['id']);
        $feed->status = 'published';
        $feed->published_at ??= now();
        $feed->save();

        return $this->serializeFeed($feed->fresh());
    }

    private function validateFeed(array $arguments, bool $creating): array
    {
        $titleRule = $creating ? ['required', 'string', 'max:160'] : ['sometimes', 'string', 'max:160'];
        $feedTypeRule = ['sometimes', Rule::in(FeedService::TYPES)];

        $rules = [
            'title' => $titleRule,
            'body' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'feed_type' => $feedTypeRule,
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

    private function findFeed(int $id): SocialContent
    {
        return SocialContent::query()
            ->whereKey($id)
            ->where('type', 'post')
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

        for ($counter = 2; $modelClass::query()->withTrashed()->where('slug', $slug)->exists(); $counter++) {
            $slug = "{$base}-{$counter}";
        }

        return $slug;
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
            'needs_media' => ! $game->cover,
            'url' => $game->status === 'active' ? route('games.show', $game->slug, false) : null,
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
            'needs_media' => ! $studio->logo || ! $studio->background,
            'url' => $studio->status === 'active' ? route('studios.show', $studio->slug, false) : null,
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
            'needs_media' => ! $story->video_path,
            'published_at' => $story->published_at?->toISOString(),
        ];
    }

    private function serializeFeed(SocialContent $feed): array
    {
        $feed->loadMissing('game:id,name,slug');

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
            'published_at' => $feed->published_at?->toISOString(),
            'url' => $feed->status === 'published' ? route('posts.show', $feed->slug, false) : null,
        ];
    }
}
