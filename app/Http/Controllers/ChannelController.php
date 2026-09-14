<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $channel = $this->channelData($request, $game);
        $canonical = route('channels.show', $game->slug);
        $description = Str::limit(
            RichText::plainText($game->description) ?: "ویدیوها، کالکشن‌ها و تازه‌ترین محتوای {$game->name} در PlayNexus.",
            160,
            '…',
        );
        $image = url($channel['background_url'] ?: $channel['cover_url'] ?: (string) config('seo.default_image', '/logo.png'));

        return Inertia::render('Channels/Show', [
            ...Seo::page([
                'title' => "کانال {$game->name}",
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                'type' => 'profile',
                'image' => $image,
                'imageAlt' => "کانال {$game->name}",
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'VideoGame',
                            '@id' => $canonical.'#game',
                            'name' => $game->name,
                            'url' => $canonical,
                            'description' => $description,
                            'image' => $image,
                            'mainEntityOfPage' => $canonical,
                            ...($channel['platforms'] ? ['gamePlatform' => $channel['platforms']] : []),
                            ...($game->developer ? ['author' => ['@type' => 'Organization', 'name' => $game->developer]] : []),
                            ...($game->publisher ? ['publisher' => ['@type' => 'Organization', 'name' => $game->publisher]] : []),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'ویدیوها', 'item' => route('videos.index')],
                                ['@type' => 'ListItem', 'position' => 3, 'name' => $game->name, 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'channel' => $channel,
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
            ...$this->playlistSeo($playlist, $game),
            'channel' => $this->channelData($request, $game),
            'playlist' => [
                ...$this->playlistData($game, $playlist),
                'description' => RichText::plainText($playlist->description),
                'description_html' => RichText::sanitize($playlist->description),
                'videos' => $playlist->videos->map(fn (SocialContent $video) => $data->content($video))->values(),
            ],
        ]);
    }

    public function collection(VideoPlaylist $playlist, StorefrontDataService $data): Response
    {
        abort_unless(in_array($playlist->visibility, ['public', 'unlisted'], true), 404);
        $playlist->load([
            'game:id,name,slug,cover,status',
            'videos' => fn ($query) => $query->published()->where('type', 'video')->with(['game:id,name,slug,cover', 'user:id,name,avatar']),
        ]);

        return Inertia::render('Channels/Playlist', [
            ...$this->playlistSeo($playlist, $playlist->game),
            'channel' => null,
            'playlist' => [
                ...$playlist->only(['id', 'title', 'slug']),
                'cover_url' => MediaStorage::url($playlist->logo),
                'description' => RichText::plainText($playlist->description),
                'description_html' => RichText::sanitize($playlist->description),
                'videos_count' => $playlist->videos->count(),
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
            ...$game->only(['id', 'name', 'slug', 'developer', 'publisher']),
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

    private function playlistSeo(VideoPlaylist $playlist, ?Game $game): array
    {
        $canonical = route('collections.show', $playlist->slug);
        $description = Str::limit(
            RichText::plainText($playlist->description) ?: "تماشای ویدیوهای کالکشن {$playlist->title} در PlayNexus.",
            160,
            '…',
        );
        $imagePath = MediaStorage::url($playlist->logo ?: $playlist->videos->first()?->thumbnail ?: $game?->cover);
        $image = url($imagePath ?: (string) config('seo.default_image', '/logo.png'));
        $itemListId = $canonical.'#videos';

        return Seo::page([
            'title' => $game ? "{$playlist->title} - {$game->name}" : $playlist->title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $playlist->visibility === 'public'
                ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
                : 'noindex, follow',
            'type' => 'website',
            'image' => $image,
            'imageAlt' => "کالکشن {$playlist->title}",
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'CollectionPage',
                        '@id' => $canonical.'#collection',
                        'name' => $playlist->title,
                        'url' => $canonical,
                        'description' => $description,
                        'image' => $image,
                        'mainEntity' => ['@id' => $itemListId],
                    ],
                    [
                        '@type' => 'ItemList',
                        '@id' => $itemListId,
                        'name' => "ویدیوهای {$playlist->title}",
                        'numberOfItems' => $playlist->videos->count(),
                        'itemListElement' => $playlist->videos->values()->map(fn (SocialContent $video, int $index) => [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $video->title,
                            'url' => route('content.show', ['type' => 'videos', 'content' => $video->slug]),
                        ])->all(),
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        '@id' => $canonical.'#breadcrumb',
                        'itemListElement' => array_values(array_filter([
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                            $game ? ['@type' => 'ListItem', 'position' => 2, 'name' => $game->name, 'item' => route('channels.show', $game->slug)] : null,
                            ['@type' => 'ListItem', 'position' => $game ? 3 : 2, 'name' => $playlist->title, 'item' => $canonical],
                        ])),
                    ],
                ],
            ],
        ]);
    }
}
