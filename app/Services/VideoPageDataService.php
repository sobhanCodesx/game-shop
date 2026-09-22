<?php

namespace App\Services;

use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class VideoPageDataService
{
    public function __construct(
        private readonly StorefrontDataService $data,
        private readonly StorefrontPageCache $cache,
    ) {}

    public function get(SocialContent $content, string $playlistSlug = ''): array
    {
        return $this->cache->remember(
            'video',
            $content->id.':'.($playlistSlug !== '' ? $playlistSlug : 'default'),
            fn () => $this->build($content, $playlistSlug),
        );
    }

    public function withLiveCardMetrics(array $pageData): array
    {
        $relatedIds = collect($pageData['related'] ?? [])->pluck('id');
        $playlistIds = collect(data_get($pageData, 'playlist.items', []))->pluck('id');
        $ids = $relatedIds->merge($playlistIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return $pageData;
        }

        $metrics = SocialContent::query()
            ->whereKey($ids)
            ->withCount([
                'reactions as likes_count' => fn ($query) => $query->where('type', 'like'),
                'comments as comments_count' => fn ($query) => $query->published(),
            ])
            ->get(['id', 'views'])
            ->keyBy('id');

        $apply = static function (array $item) use ($metrics): array {
            $metric = $metrics->get((int) ($item['id'] ?? 0));

            return [
                ...$item,
                'views' => (int) ($metric?->views ?? 0),
                'likes_count' => (int) ($metric?->likes_count ?? 0),
                'comments_count' => (int) ($metric?->comments_count ?? 0),
                'is_liked' => false,
            ];
        };

        $pageData['related'] = collect($pageData['related'] ?? [])->map($apply)->values()->all();

        if (is_array($pageData['playlist'] ?? null)) {
            $pageData['playlist']['items'] = collect($pageData['playlist']['items'] ?? [])
                ->map($apply)
                ->values()
                ->all();
        }

        return $pageData;
    }

    public function seo(array $seoInput, int $views): array
    {
        $graph = data_get($seoInput, 'structuredData.@graph', []);

        foreach ($graph as $index => $entity) {
            if (($entity['@type'] ?? null) === 'VideoObject') {
                $graph[$index]['interactionStatistic']['userInteractionCount'] = $views;
                break;
            }
        }

        data_set($seoInput, 'structuredData.@graph', $graph);

        return Seo::page($seoInput);
    }

    private function build(SocialContent $content, string $playlistSlug): array
    {
        $content->load([
            'game:id,name,slug,cover,background,developer,publisher',
            'game.playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order']),
            'media',
        ]);

        $related = SocialContent::query()
            ->published()
            ->where('type', 'video')
            ->whereKeyNot($content->id)
            ->with(['game:id,name,slug,cover', 'media'])
            ->when(
                $content->game_id,
                fn (Builder $query) => $query->orderByRaw('CASE WHEN game_id = ? THEN 0 ELSE 1 END', [$content->game_id]),
            )
            ->latest('published_at')
            ->limit(12)
            ->get()
            ->map(fn (SocialContent $item) => $this->staticCard($this->data->content($item)))
            ->values()
            ->all();

        $playlist = $this->playlistContext($playlistSlug, $content);
        $breadcrumbs = $this->breadcrumbs($content, $playlist);
        $contentData = [
            ...$this->staticCard($this->data->content($content)),
            'excerpt' => RichText::plainText($content->excerpt),
            'body' => RichText::sanitize($content->body),
            'video_mime' => $content->video_mime,
            'allow_comments' => (bool) $content->allow_comments,
        ];

        return [
            'content' => $contentData,
            'channel' => $content->game ? [
                'id' => $content->game->id,
                'name' => $content->game->name,
                'slug' => $content->game->slug,
                'url' => route('channels.show', $content->game->slug, false),
                'avatar_url' => MediaStorage::url($content->game->cover ?: $content->game->playlists->first()?->logo),
            ] : null,
            'related' => $related,
            'playlist' => $playlist,
            'breadcrumbs' => $breadcrumbs,
            'seo_input' => $this->seoInput($content, $breadcrumbs),
        ];
    }

    private function staticCard(array $item): array
    {
        unset($item['views'], $item['likes_count'], $item['comments_count'], $item['is_liked']);

        return $item;
    }

    private function playlistContext(string $slug, SocialContent $content): ?array
    {
        $playlist = VideoPlaylist::query()
            ->when(
                $slug !== '',
                fn ($query) => $query->where('slug', $slug)->whereIn('visibility', ['public', 'unlisted']),
                fn ($query) => $query->publiclyVisible(),
            )
            ->whereHas('videos', fn ($query) => $query->whereKey($content->id))
            ->with([
                'game:id,name,slug',
                'studio:id,name,slug',
                'videos' => fn ($query) => $query
                    ->published()
                    ->where('type', 'video')
                    ->with('game:id,name,slug,cover'),
            ])
            ->orderBy('sort_order')
            ->first();

        if (! $playlist) {
            return null;
        }

        return [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'slug' => $playlist->slug,
            'channel_name' => $playlist->game?->name ?? $playlist->studio?->name ?? 'PlayNexus',
            'url' => $playlist->game
                ? route('channels.playlists.show', ['game' => $playlist->game->slug, 'playlist' => $playlist->slug], false)
                : route('collections.show', $playlist->slug, false),
            'is_public' => $playlist->visibility === 'public',
            'items' => $playlist->videos
                ->map(fn (SocialContent $video) => $this->staticCard($this->data->content($video)))
                ->values()
                ->all(),
            'current_id' => $content->id,
        ];
    }

    private function breadcrumbs(SocialContent $content, ?array $playlist): array
    {
        $items = [[
            'name' => 'صفحه اصلی',
            'url' => route('home', absolute: false),
            'current' => false,
        ]];

        if ($content->game) {
            $items[] = [
                'name' => $content->game->name,
                'url' => route('channels.show', $content->game->slug, false),
                'current' => false,
            ];
        }

        if (($playlist['is_public'] ?? false)) {
            $items[] = [
                'name' => $playlist['title'],
                'url' => $playlist['url'],
                'current' => false,
            ];
        }

        $items[] = [
            'name' => $content->title,
            'url' => route('content.show', ['type' => 'videos', 'content' => $content->slug], false),
            'current' => true,
        ];

        return $items;
    }

    private function seoInput(SocialContent $content, array $breadcrumbs): array
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('content.show', ['type' => 'videos', 'content' => $content->slug]);
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $primaryVideoMedia = $content->media->first(fn ($media) => $media->type === 'video');
        $primaryImageMedia = $content->media->first(fn ($media) => $media->type === 'image');
        $thumbnailPath = $content->thumbnail ?: $primaryVideoMedia?->thumbnail ?: $primaryImageMedia?->path;
        $videoPath = $content->video_path ?: $primaryVideoMedia?->path;
        $thumbnail = MediaStorage::url($thumbnailPath);
        $thumbnail = $thumbnail ? url($thumbnail) : null;
        $videoUrl = MediaStorage::url($videoPath);
        $videoUrl = $videoUrl ? url($videoUrl) : null;
        $summary = RichText::plainText($content->seo_description ?: $content->excerpt ?: $content->body);
        $description = $summary
            ? Str::limit($summary, 160, '…')
            : Str::limit("تماشای {$content->title}، ویدیوها و محتوای تازه دنیای گیمینگ در {$siteName}.", 160, '…');
        $title = filled($content->seo_title)
            ? trim($content->seo_title)
            : Str::limit("{$content->title} | {$siteName}", 60, '…');
        $organizationId = route('home').'#organization';

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'type' => 'video.other',
            'siteName' => $siteName,
            'locale' => $locale,
            'image' => $thumbnail ?: $logo,
            'imageAlt' => $thumbnail ? "تصویر بندانگشتی {$content->title}" : "لوگوی {$siteName}",
            ...($videoUrl ? ['video' => [
                'url' => $videoUrl,
                'type' => $content->video_mime,
                'duration' => $content->duration,
            ]] : []),
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $organizationId,
                        'name' => $siteName,
                        'url' => route('home'),
                        'logo' => ['@type' => 'ImageObject', 'url' => $logo],
                    ],
                    [
                        '@type' => 'VideoObject',
                        '@id' => $canonical.'#video',
                        'name' => $content->title,
                        'description' => $description,
                        'url' => $canonical,
                        'uploadDate' => $content->published_at?->toISOString(),
                        'inLanguage' => $locale,
                        'publisher' => ['@id' => $organizationId],
                        'interactionStatistic' => [
                            '@type' => 'InteractionCounter',
                            'interactionType' => ['@type' => 'WatchAction'],
                            'userInteractionCount' => 0,
                        ],
                        ...($thumbnail ? ['thumbnailUrl' => [$thumbnail]] : ['thumbnailUrl' => [$logo]]),
                        ...($videoUrl ? ['contentUrl' => $videoUrl] : []),
                        ...($content->duration ? ['duration' => $this->isoDuration((int) $content->duration)] : []),
                        ...($content->game ? ['about' => ['@type' => 'VideoGame', 'name' => $content->game->name]] : []),
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        '@id' => $canonical.'#breadcrumb',
                        'itemListElement' => collect($breadcrumbs)->map(fn (array $crumb, int $index) => [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $crumb['name'],
                            'item' => url($crumb['url']),
                        ])->all(),
                    ],
                ],
            ],
        ];
    }

    private function isoDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return 'PT'.($hours ? "{$hours}H" : '').($minutes ? "{$minutes}M" : '')."{$remainingSeconds}S";
    }
}
