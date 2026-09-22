<?php

namespace App\Services;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\User;
use App\Models\VideoPlaylist;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ChannelPageDataService
{
    public function __construct(
        private readonly StorefrontDataService $data,
        private readonly FeedService $feed,
        private readonly StorefrontPageCache $cache,
    ) {}

    public function get(Game $game, Request $request): array
    {
        $page = max(1, $request->integer('page', 1));

        $payload = $this->cache->remember(
            'channel',
            "game:{$game->id}:page:{$page}",
            fn () => $this->build($game, $page),
        );

        return $this->withLiveState($payload, $request->user());
    }

    private function build(Game $game, int $page): array
    {
        $game->loadMissing(['platforms:id,name', 'studio:id,name,slug,logo,status']);

        $videos = SocialContent::query()
            ->published()
            ->where('type', 'video')
            ->whereBelongsTo($game)
            ->with(['game:id,name,slug,cover'])
            ->latest('published_at')
            ->paginate(18, ['*'], 'page', $page)
            ->through(fn (SocialContent $video) => $this->staticContent($this->data->content($video)))
            ->toArray();

        $playlists = VideoPlaylist::query()
            ->publiclyVisible()
            ->whereBelongsTo($game)
            ->with(['videos' => fn ($query) => $query->published()->where('type', 'video')->limit(4)])
            ->withCount(['videos' => fn ($query) => $query->published()->where('type', 'video')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (VideoPlaylist $playlist) => [
                'id' => $playlist->id,
                'title' => $playlist->title,
                'slug' => $playlist->slug,
                'url' => route('channels.playlists.show', ['game' => $game->slug, 'playlist' => $playlist->slug], false),
                'cover_url' => MediaStorage::url($playlist->logo),
                'videos_count' => (int) ($playlist->videos_count ?? $playlist->videos->count()),
            ])
            ->values()
            ->all();

        $anonymous = Request::create('/', 'GET');
        $feed = collect($this->feed->channel($anonymous, $game))
            ->map(fn (array $item) => $this->staticFeed($item))
            ->values()
            ->all();

        $logo = $game->cover ?: $game->playlists()->publiclyVisible()->whereNotNull('logo')->value('logo');

        return [
            'channel' => [
                ...$game->only(['id', 'name', 'slug', 'developer', 'publisher', 'age_rating']),
                'release_date' => $game->release_date?->toDateString(),
                'description' => RichText::plainText($game->description),
                'description_html' => RichText::sanitize($game->description),
                'cover_url' => MediaStorage::url($logo),
                'background_url' => MediaStorage::url($game->background),
                'platforms' => $game->platforms->pluck('name')->values()->all(),
                'subscribers_count' => 0,
                'videos_count' => 0,
                'is_subscribed' => false,
                'studio' => $game->studio?->status === 'active' ? [
                    'name' => $game->studio->name,
                    'url' => route('studios.show', $game->studio->slug, false),
                    'logo_url' => MediaStorage::url($game->studio->logo),
                ] : null,
            ],
            'videos' => $videos,
            'playlists' => $playlists,
            'feed' => $feed,
        ];
    }

    private function withLiveState(array $payload, ?User $user): array
    {
        $gameId = (int) data_get($payload, 'channel.id', 0);
        $game = Game::query()->find($gameId);

        if ($game) {
            $payload['channel']['subscribers_count'] = $game->subscribers()->count();
            $payload['channel']['videos_count'] = $game->videos()->published()->count();
            $payload['channel']['is_subscribed'] = (bool) ($user
                ? $game->subscribers()->whereKey($user->id)->exists()
                : false);
        }

        $videoIds = collect(data_get($payload, 'videos.data', []))
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($videoIds->isNotEmpty()) {
            $views = SocialContent::query()->whereKey($videoIds)->pluck('views', 'id');

            $payload['videos']['data'] = collect($payload['videos']['data'])
                ->map(function (array $video) use ($views): array {
                    $id = (int) ($video['id'] ?? 0);

                    return [
                        ...$video,
                        'views' => (int) ($views[$id] ?? 0),
                    ];
                })
                ->values()
                ->all();
        }

        $feedIds = collect($payload['feed'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($feedIds->isNotEmpty()) {
            $metrics = SocialContent::query()
                ->whereKey($feedIds)
                ->withCount([
                    'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                    'comments as comments_count' => fn (Builder $query) => $query->published(),
                ])
                ->get(['id'])
                ->keyBy('id');

            $liked = collect();
            $saved = collect();

            if ($user) {
                $liked = DB::table('social_content_reactions')
                    ->where('user_id', $user->id)
                    ->where('type', 'like')
                    ->whereIn('social_content_id', $feedIds)
                    ->pluck('social_content_id')
                    ->map(fn ($id) => (int) $id);

                $saved = DB::table('social_content_saves')
                    ->where('user_id', $user->id)
                    ->whereIn('social_content_id', $feedIds)
                    ->pluck('social_content_id')
                    ->map(fn ($id) => (int) $id);
            }

            $payload['feed'] = collect($payload['feed'])
                ->map(function (array $item) use ($metrics, $liked, $saved): array {
                    $id = (int) ($item['id'] ?? 0);
                    $metric = $metrics->get($id);

                    return [
                        ...$item,
                        'likes_count' => (int) ($metric?->likes_count ?? 0),
                        'comments_count' => (int) ($metric?->comments_count ?? 0),
                        'is_liked' => $liked->contains($id),
                        'is_saved' => $saved->contains($id),
                    ];
                })
                ->values()
                ->all();
        }

        return $payload;
    }

    private function staticContent(array $content): array
    {
        return [
            ...$content,
            'views' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
            'is_liked' => false,
        ];
    }

    private function staticFeed(array $item): array
    {
        return [
            ...$item,
            'likes_count' => 0,
            'comments_count' => 0,
            'is_liked' => false,
            'is_saved' => false,
        ];
    }
}
