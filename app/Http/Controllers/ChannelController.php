<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\ChannelPageDataService;
use App\Services\FeedService;
use App\Services\FollowedGameWatchService;
use App\Services\GameRadarService;
use App\Services\MediaStorage;
use App\Services\PlaylistPageDataService;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChannelController extends Controller
{
    public function show(
        Request $request,
        Game $game,
        ChannelPageDataService $page,
        GameRadarService $radar,
        FollowedGameWatchService $watch,
    ): Response {
        $this->ensureVisible($game);

        $payload = $page->get($game, $request);
        $channel = $payload['channel'];
        $channel['watch'] = $watch->status($game, (bool) $channel['is_subscribed']);

        $storeInfo = $radar->storeDataForGame($game);
        $canonical = route('channels.show', $game->slug);
        $description = Str::limit(
            $channel['description'] ?: "ویدیوها، کالکشن‌ها و تازه‌ترین محتوای {$game->name} در PlayNexus.",
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
                            ...($game->release_date ? ['datePublished' => $game->release_date->toDateString()] : []),
                            ...($game->developer ? ['author' => ['@type' => 'Organization', 'name' => $game->developer]] : []),
                            ...($game->publisher ? ['publisher' => ['@type' => 'Organization', 'name' => $game->publisher]] : []),
                            ...(collect([
                                data_get($storeInfo, 'psn.url'),
                                data_get($storeInfo, 'xbox.url'),
                            ])->filter()->isNotEmpty()
                                ? ['sameAs' => collect([
                                    data_get($storeInfo, 'psn.url'),
                                    data_get($storeInfo, 'xbox.url'),
                                ])->filter()->values()->all()]
                                : []),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                $storeInfo
                                    ? ['@type' => 'ListItem', 'position' => 2, 'name' => 'رادار بازی‌ها', 'item' => route('game-radar.index')]
                                    : ['@type' => 'ListItem', 'position' => 2, 'name' => 'ویدیوها', 'item' => route('videos.index')],
                                ['@type' => 'ListItem', 'position' => 3, 'name' => $game->name, 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'channel' => $channel,
            'videos' => $payload['videos'],
            'playlists' => $payload['playlists'],
            'feed' => $payload['feed'],
            'storeInfo' => $storeInfo,
        ]);
    }

    public function playlist(Request $request, Game $game, VideoPlaylist $playlist, PlaylistPageDataService $page): Response
    {
        $this->ensureVisible($game);
        abort_unless($playlist->game_id === $game->id && in_array($playlist->visibility, ['public', 'unlisted'], true), 404);

        return Inertia::render('Channels/Playlist', $page->playlist($game, $playlist, $request));
    }

    public function collection(VideoPlaylist $playlist, PlaylistPageDataService $page): Response
    {
        abort_unless(in_array($playlist->visibility, ['public', 'unlisted'], true), 404);

        return Inertia::render('Channels/Playlist', $page->collection($playlist));
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
