<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function show(Request $request, Game $game, StorefrontDataService $data, FeedService $feed): Response
    {
        $this->ensureVisible($game);

        $videos = SocialContent::query()->published()->where('type', 'video')->whereBelongsTo($game)
            ->with(['game:id,name,slug,cover', 'user:id,name,avatar'])
            ->latest('published_at')->paginate(18)->withQueryString()
            ->through(fn (SocialContent $video) => $data->content($video));
        $playlists = VideoPlaylist::query()->publiclyVisible()->whereBelongsTo($game)
            ->with(['videos' => fn ($query) => $query->published()->where('type', 'video')->limit(4)])
            ->withCount(['videos' => fn ($query) => $query->published()->where('type', 'video')])
            ->orderBy('sort_order')->get()->map(fn (VideoPlaylist $playlist) => $this->playlistData($game, $playlist));

        return Inertia::render('Channels/Show', [
            'channel' => $this->channelData($request, $game),
            'videos' => $videos,
            'playlists' => $playlists,
            'feed' => $feed->channel($request, $game),
        ]);
    }

    public function playlist(Request $request, Game $game, VideoPlaylist $playlist, StorefrontDataService $data): Response
    {
        $this->ensureVisible($game);
        abort_unless($playlist->game_id === $game->id && in_array($playlist->visibility, ['public', 'unlisted'], true), 404);
        $playlist->load(['videos' => fn ($query) => $query->published()->where('type', 'video')->with(['game:id,name,slug,cover', 'user:id,name,avatar'])]);

        return Inertia::render('Channels/Playlist', [
            'channel' => $this->channelData($request, $game),
            'playlist' => [
                ...$this->playlistData($game, $playlist),
                'description' => RichText::plainText($playlist->description),
                'description_html' => RichText::sanitize($playlist->description),
                'videos' => $playlist->videos->map(fn (SocialContent $video) => $data->content($video))->values(),
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
        $logo = $game->cover ?: $game->playlists()->publiclyVisible()->whereNotNull('logo')->value('logo');

        return [
            ...$game->only(['id', 'name', 'slug', 'description', 'developer', 'publisher']),
            'cover_url' => MediaStorage::url($logo),
            'background_url' => MediaStorage::url($game->background),
            'platforms' => $game->platforms->pluck('name')->values(),
            'subscribers_count' => $subscribersCount,
            'videos_count' => $game->videos()->published()->count(),
            'is_subscribed' => $request->user()
                ? $game->subscribers()->whereKey($request->user()->id)->exists()
                : false,
            'studio' => $game->studio?->status === 'active' ? [
                'name' => $game->studio->name,
                'url' => route('studios.show', $game->studio->slug, false),
                'logo_url' => MediaStorage::url($game->studio->logo),
            ] : null,
        ];
    }

    private function playlistData(Game $game, VideoPlaylist $playlist): array
    {
        return [
            ...$playlist->only(['id', 'title', 'slug']),
            'url' => route('channels.playlists.show', ['game' => $game->slug, 'playlist' => $playlist->slug], false),
            'cover_url' => MediaStorage::url($playlist->logo),
            'videos_count' => $playlist->videos_count ?? $playlist->videos->count(),
        ];
    }
}
