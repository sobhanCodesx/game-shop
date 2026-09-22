<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StudioPageDataService
{
    public function __construct(
        private readonly StorefrontPageCache $cache,
    ) {}

    public function get(Studio $studio, Request $request): array
    {
        $channelsPage = max(1, $request->integer('channels_page', 1));
        $collectionsPage = max(1, $request->integer('collections_page', 1));

        $payload = ($channelsPage <= 50 && $collectionsPage <= 50)
            ? $this->cache->remember(
                'studio',
                "studio:{$studio->id}:channels:{$channelsPage}:collections:{$collectionsPage}",
                fn () => $this->build($studio, $channelsPage, $collectionsPage),
                21600,
            )
            : $this->build($studio, $channelsPage, $collectionsPage);

        return $this->withLiveFollowers($payload);
    }

    private function build(Studio $studio, int $channelsPage, int $collectionsPage): array
    {
        $channels = Game::query()
            ->whereBelongsTo($studio)
            ->whereIn('status', ['active', 'published'])
            ->withCount([
                'videos' => fn ($query) => $query->published(),
                'subscribers',
            ])
            ->orderByDesc('subscribers_count')
            ->latest('id')
            ->paginate(18, ['*'], 'channels_page', $channelsPage)
            ->appends($collectionsPage > 1 ? ['collections_page' => $collectionsPage] : [])
            ->through(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'url' => route('channels.show', $game->slug, false),
                'logo_url' => MediaStorage::url($game->cover),
                'background_url' => MediaStorage::url($game->background),
                'videos_count' => (int) $game->videos_count,
                'followers_count' => (int) $game->subscribers_count,
            ])
            ->toArray();

        $collections = VideoPlaylist::query()
            ->where(fn ($query) => $query
                ->where('studio_id', $studio->id)
                ->orWhereHas('game', fn ($gameQuery) => $gameQuery->where('studio_id', $studio->id)))
            ->publiclyVisible()
            ->with('game:id,name,slug,cover')
            ->withCount('videos')
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(12, ['*'], 'collections_page', $collectionsPage)
            ->appends($channelsPage > 1 ? ['channels_page' => $channelsPage] : [])
            ->through(fn (VideoPlaylist $playlist) => [
                'id' => $playlist->id,
                'title' => $playlist->title,
                'description' => RichText::plainText($playlist->description),
                'url' => route('collections.show', $playlist->slug, false),
                'logo_url' => MediaStorage::url($playlist->logo ?: $playlist->game?->cover),
                'channel_name' => $playlist->game?->name ?? $studio->name,
                'videos_count' => (int) $playlist->videos_count,
            ])
            ->toArray();

        $studioData = [
            'id' => $studio->id,
            'name' => $studio->name,
            'slug' => $studio->slug,
            'url' => route('studios.show', $studio->slug, false),
            'logo_url' => MediaStorage::url($studio->logo),
            'background_url' => MediaStorage::url($studio->background),
            'description' => RichText::plainText($studio->description),
            'description_html' => RichText::sanitize($studio->description),
            'website' => $studio->website,
            'channels_count' => Game::query()
                ->whereBelongsTo($studio)
                ->whereIn('status', ['active', 'published'])
                ->count(),
            'collections_count' => VideoPlaylist::query()
                ->where(fn ($query) => $query
                    ->where('studio_id', $studio->id)
                    ->orWhereHas('game', fn ($gameQuery) => $gameQuery->where('studio_id', $studio->id)))
                ->publiclyVisible()
                ->count(),
        ];

        return [
            ...$this->seo($studio),
            'studio' => $studioData,
            'channels' => $channels,
            'collections' => $collections,
        ];
    }

    private function withLiveFollowers(array $payload): array
    {
        $ids = collect(data_get($payload, 'channels.data', []))
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return $payload;
        }

        $counts = DB::table('game_subscriptions')
            ->whereIn('game_id', $ids)
            ->selectRaw('game_id, COUNT(*) as aggregate')
            ->groupBy('game_id')
            ->pluck('aggregate', 'game_id');

        $payload['channels']['data'] = collect($payload['channels']['data'])
            ->map(function (array $channel) use ($counts): array {
                $id = (int) ($channel['id'] ?? 0);

                return [
                    ...$channel,
                    'followers_count' => (int) ($counts[$id] ?? 0),
                ];
            })
            ->values()
            ->all();

        return $payload;
    }

    private function seo(Studio $studio): array
    {
        $canonical = route('studios.show', $studio->slug);
        $plainDescription = RichText::plainText($studio->description);
        $description = Str::limit(
            $plainDescription ?: "معرفی استودیو {$studio->name}، بازی‌های شاخص، تاریخچه و تازه‌ترین محتوای مرتبط در PlayNexus.",
            148,
            '…',
        );
        $seoTitle = "استودیو {$studio->name} | بازی‌ها، تاریخچه و اخبار";
        $image = url(MediaStorage::url($studio->background ?: $studio->logo) ?: (string) config('seo.default_image', '/logo.png'));

        return Seo::page([
            'title' => $seoTitle,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'image' => $image,
            'imageAlt' => "استودیو {$studio->name}",
            'type' => 'profile',
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $canonical.'#studio',
                        'name' => $studio->name,
                        'url' => $canonical,
                        'description' => $description,
                        'image' => $image,
                        ...($studio->website ? ['sameAs' => [$studio->website]] : []),
                        ...($studio->logo ? ['logo' => url(MediaStorage::url($studio->logo))] : []),
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        '@id' => $canonical.'#breadcrumb',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => 'استودیوهای بازی‌سازی', 'item' => route('studios.index')],
                            ['@type' => 'ListItem', 'position' => 3, 'name' => $studio->name, 'item' => $canonical],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
