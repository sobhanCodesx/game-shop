<?php

namespace App\Services;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlaylistPageDataService
{
    public function __construct(
        private readonly StorefrontDataService $data,
        private readonly StorefrontPageCache $cache,
    ) {}

    public function playlist(Game $game, VideoPlaylist $playlist, ?Request $request = null): array
    {
        $payload = $this->cache->remember(
            'playlist',
            "game:{$game->id}:playlist:{$playlist->id}",
            fn () => $this->buildPlaylist($game, $playlist, true),
        );

        return $this->withLiveState($payload, $request?->user());
    }

    public function collection(VideoPlaylist $playlist): array
    {
        return $this->withLiveState(
            $this->cache->remember(
                'playlist',
                "collection:{$playlist->id}",
                fn () => $this->buildPlaylist($playlist->game, $playlist, false),
            ),
            null,
        );
    }

    private function buildPlaylist(?Game $game, VideoPlaylist $playlist, bool $includeChannel): array
    {
        $playlist->load([
            'game:id,name,slug,cover,status',
            'videos' => fn ($query) => $query
                ->published()
                ->where('type', 'video')
                ->with(['game:id,name,slug,cover', 'media']),
        ]);

        if ($includeChannel && $game) {
            $game->loadMissing(['platforms:id,name', 'studio:id,name,slug,logo,status']);
        }

        $videos = $playlist->videos
            ->map(fn (SocialContent $video) => $this->staticContent($this->data->content($video)))
            ->values()
            ->all();

        $playlistData = [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'slug' => $playlist->slug,
            'cover_url' => MediaStorage::url($playlist->logo),
            'description' => RichText::plainText($playlist->description),
            'description_html' => RichText::sanitize($playlist->description),
            'videos_count' => count($videos),
            'videos' => $videos,
        ];

        $channel = null;
        if ($includeChannel && $game) {
            $logo = $game->cover ?: $game->playlists()->publiclyVisible()->whereNotNull('logo')->value('logo');
            $channel = [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'cover_url' => MediaStorage::url($logo),
                'subscribers_count' => 0,
                'is_subscribed' => false,
            ];
        }

        return [
            ...$this->seo($playlist, $playlist->game ?? $game),
            'channel' => $channel,
            'playlist' => $playlistData,
        ];
    }

    private function withLiveState(array $payload, mixed $user): array
    {
        $videoIds = collect(data_get($payload, 'playlist.videos', []))
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($videoIds->isNotEmpty()) {
            $views = SocialContent::query()
                ->whereKey($videoIds)
                ->pluck('views', 'id');

            $payload['playlist']['videos'] = collect($payload['playlist']['videos'])
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

        if (is_array($payload['channel'] ?? null)) {
            $gameId = (int) ($payload['channel']['id'] ?? 0);
            $game = Game::query()->find($gameId);

            if ($game) {
                $payload['channel']['subscribers_count'] = $game->subscribers()->count();
                $payload['channel']['is_subscribed'] = (bool) ($user
                    ? $game->subscribers()->whereKey($user->id)->exists()
                    : false);
            }
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

    private function seo(VideoPlaylist $playlist, ?Game $game): array
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
