<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Services\FeedService;
use App\Services\FollowedGameWatchService;
use App\Services\GameRadarService;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileChannelsController extends Controller
{
    public function channels(Request $request): JsonResponse
    {
        $query = Game::query()
            ->whereIn('status', ['active', 'published'])
            ->with([
                'studio:id,name,slug,logo,status',
                'playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
            ])
            ->withCount([
                'videos' => fn ($query) => $query->published(),
                'subscribers',
            ])
            ->when(
                $request->user(),
                fn ($query) => $query->withExists([
                    'subscribers as is_subscribed' => fn ($subscribers) => $subscribers
                        ->whereKey($request->user()->id),
                ]),
            )
            ->when(
                $request->filled('q'),
                fn ($query) => $query->where(
                    fn ($search) => $search
                        ->where('name', 'like', '%'.$request->string('q')->toString().'%')
                        ->orWhere('developer', 'like', '%'.$request->string('q')->toString().'%')
                        ->orWhere('publisher', 'like', '%'.$request->string('q')->toString().'%'),
                ),
            )
            ->when(
                $request->filled('studio'),
                fn ($query) => $query->whereHas(
                    'studio',
                    fn ($studio) => $studio->where('slug', $request->string('studio')->toString()),
                ),
            )
            ->when(
                $request->string('sort')->toString() === 'latest',
                fn ($query) => $query->latest()->latest('id'),
                fn ($query) => $query->orderByDesc('subscribers_count')->latest('id'),
            );

        $channels = $query
            ->paginate(max(1, min(50, $request->integer('per_page', 24))))
            ->withQueryString()
            ->through(function (Game $game) use ($request) {
                $logo = $game->cover ?: $game->playlists->first()?->logo;

                return [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'developer' => $game->developer,
                    'publisher' => $game->publisher,
                    'cover_url' => MediaStorage::url($logo),
                    'background_url' => MediaStorage::url($game->background),
                    'videos_count' => (int) $game->videos_count,
                    'subscribers_count' => (int) $game->subscribers_count,
                    'is_subscribed' => (bool) ($game->is_subscribed ?? false),
                    'studio' => $game->studio?->status === 'active' ? [
                        'id' => $game->studio->id,
                        'name' => $game->studio->name,
                        'slug' => $game->studio->slug,
                        'logo_url' => MediaStorage::url($game->studio->logo),
                    ] : null,
                ];
            });

        return response()->json($channels);
    }

    public function studios(Request $request): JsonResponse
    {
        $studios = Studio::query()
            ->where('status', 'active')
            ->withCount([
                'games' => fn ($query) => $query->whereIn('status', ['active', 'published']),
            ])
            ->addSelect([
                'collections_count' => VideoPlaylist::query()
                    ->selectRaw('count(*)')
                    ->publiclyVisible()
                    ->where(fn ($query) => $query
                        ->whereColumn('video_playlists.studio_id', 'studios.id')
                        ->orWhereHas(
                            'game',
                            fn ($gameQuery) => $gameQuery->whereColumn('games.studio_id', 'studios.id'),
                        )),
            ])
            ->orderByDesc('games_count')
            ->latest('id')
            ->paginate(max(1, min(50, $request->integer('per_page', 24))))
            ->withQueryString()
            ->through(fn (Studio $studio) => $this->studioData($studio));

        return response()->json($studios);
    }

    public function studio(Request $request, Studio $studio, StorefrontDataService $data): JsonResponse
    {
        abort_unless($studio->status === 'active', 404);

        $channels = Game::query()
            ->whereBelongsTo($studio)
            ->whereIn('status', ['active', 'published'])
            ->withCount([
                'videos' => fn ($query) => $query->published(),
                'subscribers',
            ])
            ->when(
                $request->user(),
                fn ($query) => $query->withExists([
                    'subscribers as is_subscribed' => fn ($subscribers) => $subscribers
                        ->whereKey($request->user()->id),
                ]),
            )
            ->orderByDesc('subscribers_count')
            ->latest('id')
            ->paginate(
                max(1, min(50, $request->integer('channels_per_page', 18))),
                ['*'],
                'channels_page',
            )
            ->withQueryString()
            ->through(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'cover_url' => MediaStorage::url($game->cover),
                'background_url' => MediaStorage::url($game->background),
                'videos_count' => (int) $game->videos_count,
                'followers_count' => (int) $game->subscribers_count,
                'is_subscribed' => (bool) ($game->is_subscribed ?? false),
            ]);

        $collections = VideoPlaylist::query()
            ->where(fn ($query) => $query
                ->where('studio_id', $studio->id)
                ->orWhereHas('game', fn ($gameQuery) => $gameQuery->where('studio_id', $studio->id)))
            ->publiclyVisible()
            ->with('game:id,name,slug,cover')
            ->withCount('videos')
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(
                max(1, min(50, $request->integer('collections_per_page', 12))),
                ['*'],
                'collections_page',
            )
            ->withQueryString()
            ->through(fn (VideoPlaylist $playlist) => [
                'id' => $playlist->id,
                'title' => $playlist->title,
                'slug' => $playlist->slug,
                'description' => RichText::plainText($playlist->description),
                'cover_url' => MediaStorage::url($playlist->logo ?: $playlist->game?->cover),
                'channel_name' => $playlist->game?->name ?? $studio->name,
                'videos_count' => (int) $playlist->videos_count,
            ]);

        $latestVideos = SocialContent::query()
            ->published()
            ->where('type', 'video')
            ->whereHas('game', fn ($query) => $query->where('studio_id', $studio->id))
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
                'media',
            ])
            ->latest('published_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (SocialContent $video) => $data->content($video))
            ->values();

        return response()->json([
            'studio' => [
                ...$this->studioData($studio),
                'description_html' => RichText::sanitize($studio->description),
                'website' => $studio->website,
            ],
            'channels' => $channels,
            'collections' => $collections,
            'latest_videos' => $latestVideos,
        ]);
    }

    public function channel(
        Request $request,
        Game $game,
        StorefrontDataService $data,
        FeedService $feed,
        GameRadarService $radar,
        FollowedGameWatchService $watch,
    ): JsonResponse {
        $this->ensureVisible($game);

        $videos = SocialContent::query()
            ->published()
            ->where('type', 'video')
            ->whereBelongsTo($game)
            ->with([
                    'game:id,name,slug,cover',
                    'game.playlists' => fn ($query) => $query
                        ->publiclyVisible()
                        ->whereNotNull('logo')
                        ->select(['id', 'game_id', 'logo', 'sort_order']),
                    'user:id,name,avatar',
                    'media',
                ])
            ->latest('published_at')
            ->paginate(max(1, min(50, $request->integer('per_page', 18))))
            ->withQueryString()
            ->through(fn (SocialContent $video) => $data->content($video));

        $playlists = VideoPlaylist::query()
            ->publiclyVisible()
            ->whereBelongsTo($game)
            ->with([
                'videos' => fn ($query) => $query
                    ->published()
                    ->where('type', 'video')
                    ->limit(4),
            ])
            ->withCount([
                'videos' => fn ($query) => $query->published()->where('type', 'video'),
            ])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (VideoPlaylist $playlist) => $this->playlistData($game, $playlist))
            ->values();

        $channel = $this->channelData($request, $game);
        $channel['watch'] = $watch->status($game, $channel['is_subscribed']);

        return response()->json([
            'channel' => $channel,
            'videos' => $videos,
            'playlists' => $playlists,
            'feed' => $feed->channel($request, $game),
            'store_info' => $radar->storeDataForGame($game),
        ]);
    }

    public function playlist(
        Request $request,
        Game $game,
        VideoPlaylist $playlist,
        StorefrontDataService $data,
    ): JsonResponse {
        $this->ensureVisible($game);
        abort_unless(
            $playlist->game_id === $game->id
                && in_array($playlist->visibility, ['public', 'unlisted'], true),
            404,
        );

        $playlist->load([
            'videos' => fn ($query) => $query
                ->published()
                ->where('type', 'video')
                ->with([
                    'game:id,name,slug,cover',
                    'game.playlists' => fn ($query) => $query
                        ->publiclyVisible()
                        ->whereNotNull('logo')
                        ->select(['id', 'game_id', 'logo', 'sort_order']),
                    'user:id,name,avatar',
                    'media',
                ]),
        ]);

        return response()->json([
            'channel' => $this->channelData($request, $game),
            'playlist' => [
                ...$this->playlistData($game, $playlist),
                'description' => RichText::plainText($playlist->description),
                'description_html' => RichText::sanitize($playlist->description),
                'videos' => $playlist->videos
                    ->map(fn (SocialContent $video) => $data->content($video))
                    ->values(),
            ],
        ]);
    }

    public function collection(
        VideoPlaylist $playlist,
        StorefrontDataService $data,
    ): JsonResponse {
        abort_unless(in_array($playlist->visibility, ['public', 'unlisted'], true), 404);

        $playlist->load([
            'game:id,name,slug,cover,status',
            'studio:id,name,slug,logo,status',
            'videos' => fn ($query) => $query
                ->published()
                ->where('type', 'video')
                ->with([
                    'game:id,name,slug,cover',
                    'game.playlists' => fn ($query) => $query
                        ->publiclyVisible()
                        ->whereNotNull('logo')
                        ->select(['id', 'game_id', 'logo', 'sort_order']),
                    'user:id,name,avatar',
                    'media',
                ]),
        ]);

        return response()->json([
            'channel' => $playlist->game && in_array($playlist->game->status, ['active', 'published'], true)
                ? [
                    'id' => $playlist->game->id,
                    'name' => $playlist->game->name,
                    'slug' => $playlist->game->slug,
                    'cover_url' => MediaStorage::url($playlist->game->cover),
                ]
                : null,
            'studio' => $playlist->studio?->status === 'active'
                ? [
                    'id' => $playlist->studio->id,
                    'name' => $playlist->studio->name,
                    'slug' => $playlist->studio->slug,
                    'logo_url' => MediaStorage::url($playlist->studio->logo),
                ]
                : null,
            'playlist' => [
                ...$playlist->only(['id', 'title', 'slug']),
                'cover_url' => MediaStorage::url($playlist->logo ?: $playlist->game?->cover),
                'description' => RichText::plainText($playlist->description),
                'description_html' => RichText::sanitize($playlist->description),
                'videos_count' => $playlist->videos->count(),
                'videos' => $playlist->videos
                    ->map(fn (SocialContent $video) => $data->content($video))
                    ->values(),
            ],
        ]);
    }

    private function ensureVisible(Game $game): void
    {
        abort_unless(in_array($game->status, ['active', 'published'], true), 404);
    }

    private function channelData(Request $request, Game $game): array
    {
        $game->loadMissing(['platforms:id,name', 'studio:id,name,slug,logo,status']);
        $subscribersCount = $game->subscribers()->count();
        $logo = $game->cover
            ?: $game->playlists()->publiclyVisible()->whereNotNull('logo')->value('logo');

        return [
            ...$game->only(['id', 'name', 'slug', 'developer', 'publisher', 'age_rating']),
            'release_date' => $game->release_date?->toDateString(),
            'description' => RichText::plainText($game->description),
            'description_html' => RichText::sanitize($game->description),
            'cover_url' => MediaStorage::url($logo),
            'background_url' => MediaStorage::url($game->background),
            'platforms' => $game->platforms->pluck('name')->values(),
            'subscribers_count' => $subscribersCount,
            'videos_count' => $game->videos()->published()->count(),
            'is_subscribed' => $request->user()
                ? $game->subscribers()->whereKey($request->user()->id)->exists()
                : false,
            'studio' => $game->studio?->status === 'active' ? [
                'id' => $game->studio->id,
                'name' => $game->studio->name,
                'slug' => $game->studio->slug,
                'logo_url' => MediaStorage::url($game->studio->logo),
            ] : null,
        ];
    }

    private function playlistData(Game $game, VideoPlaylist $playlist): array
    {
        return [
            ...$playlist->only(['id', 'title', 'slug']),
            'cover_url' => MediaStorage::url($playlist->logo),
            'videos_count' => (int) ($playlist->videos_count ?? $playlist->videos->count()),
            'game' => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
            ],
        ];
    }

    private function studioData(Studio $studio): array
    {
        return [
            'id' => $studio->id,
            'name' => $studio->name,
            'slug' => $studio->slug,
            'logo_url' => MediaStorage::url($studio->logo),
            'background_url' => MediaStorage::url($studio->background),
            'description' => RichText::plainText($studio->description),
            'channels_count' => (int) (
                $studio->games_count
                ?? $studio->games()->whereIn('status', ['active', 'published'])->count()
            ),
            'collections_count' => (int) (
                $studio->collections_count
                ?? VideoPlaylist::query()
                    ->where(fn ($query) => $query
                        ->where('studio_id', $studio->id)
                        ->orWhereHas(
                            'game',
                            fn ($gameQuery) => $gameQuery->where('studio_id', $studio->id),
                        ))
                    ->publiclyVisible()
                    ->count()
            ),
        ];
    }
}
