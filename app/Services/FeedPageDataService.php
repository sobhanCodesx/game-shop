<?php

namespace App\Services;

use App\Models\SocialContent;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FeedPageDataService
{
    public function __construct(
        private readonly FeedService $feed,
        private readonly StorefrontDataService $storefront,
        private readonly FeedPageCache $cache,
    ) {}

    public function get(SocialContent $content): array
    {
        return $this->cache->remember(
            (int) $content->id,
            fn () => $this->build($content),
        );
    }

    public function withLiveState(array $pageData, ?User $user): array
    {
        $feedIds = collect([$pageData['item']['id'] ?? null])
            ->merge(collect($pageData['latestFeed'] ?? [])->pluck('id'))
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

            $likedIds = collect();
            $savedIds = collect();

            if ($user) {
                $likedIds = DB::table('social_content_reactions')
                    ->where('user_id', $user->id)
                    ->where('type', 'like')
                    ->whereIn('social_content_id', $feedIds)
                    ->pluck('social_content_id')
                    ->map(fn ($id) => (int) $id);

                $savedIds = DB::table('social_content_saves')
                    ->where('user_id', $user->id)
                    ->whereIn('social_content_id', $feedIds)
                    ->pluck('social_content_id')
                    ->map(fn ($id) => (int) $id);
            }

            $apply = static function (array $item) use ($metrics, $likedIds, $savedIds): array {
                $id = (int) ($item['id'] ?? 0);
                $metric = $metrics->get($id);

                return [
                    ...$item,
                    'likes_count' => (int) ($metric?->likes_count ?? 0),
                    'comments_count' => (int) ($metric?->comments_count ?? 0),
                    'is_liked' => $likedIds->contains($id),
                    'is_saved' => $savedIds->contains($id),
                ];
            };

            $pageData['item'] = $apply($pageData['item']);
            $pageData['latestFeed'] = collect($pageData['latestFeed'] ?? [])
                ->map($apply)
                ->values()
                ->all();
        }

        $videoIds = collect($pageData['latestVideos'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($videoIds->isNotEmpty()) {
            $views = SocialContent::query()
                ->whereKey($videoIds)
                ->pluck('views', 'id');

            $pageData['latestVideos'] = collect($pageData['latestVideos'])
                ->map(function (array $item) use ($views): array {
                    $id = (int) ($item['id'] ?? 0);

                    return [
                        ...$item,
                        'views' => (int) ($views[$id] ?? 0),
                    ];
                })
                ->values()
                ->all();
        }

        return $pageData;
    }

    public function seo(array $seoInput): array
    {
        return Seo::page($seoInput);
    }

    private function build(SocialContent $content): array
    {
        $item = $this->staticFeedItem($this->feed->single($content, null));
        $breadcrumbs = [
            ['name' => 'خانه', 'url' => route('home', absolute: false), 'current' => false],
            ['name' => 'فید گیمینگ', 'url' => route('feed.index', absolute: false), 'current' => false],
            ['name' => $content->title, 'url' => route('posts.show', $content->slug, false), 'current' => true],
        ];

        $anonymousRequest = Request::create('/', 'GET');
        $latestFeed = collect($this->feed->latestPostsExcept($anonymousRequest, $content->id))
            ->map(fn (array $latest) => $this->staticFeedItem($latest))
            ->values()
            ->all();

        $latestVideos = SocialContent::query()
            ->published()
            ->where('type', 'video')
            ->with('game:id,name,slug,cover')
            ->latest('published_at')
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (SocialContent $video) => $this->storefront->content($video))
            ->map(fn (array $video) => [
                ...$video,
                'views' => 0,
            ])
            ->values()
            ->all();

        return [
            'item' => $item,
            'breadcrumbs' => $breadcrumbs,
            'latestFeed' => $latestFeed,
            'latestVideos' => $latestVideos,
            'seo_input' => $this->seoInput($content, $item, $breadcrumbs),
        ];
    }

    private function staticFeedItem(array $item): array
    {
        unset($item['likes_count'], $item['comments_count'], $item['is_liked'], $item['is_saved']);

        $item['media'] = collect($item['media'] ?? [])->values()->all();

        return $item;
    }

    private function seoInput(SocialContent $content, array $item, array $breadcrumbs): array
    {
        $canonical = route('posts.show', $content->slug);
        $description = Str::limit((string) ($item['body'] ?: $content->title), 160, '…');
        $metaDescription = $content->seo_description ?: $description;
        $primaryMedia = collect($item['media'])->firstWhere('type', 'image')
            ?? collect($item['media'])->first();
        $image = data_get($primaryMedia, 'type') === 'image'
            ? data_get($primaryMedia, 'url')
            : data_get($primaryMedia, 'thumbnail');
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $organizationId = route('home').'#organization';
        $authorUrl = data_get($item, 'author.url');

        return [
            'title' => $content->seo_title ?: $content->title,
            'description' => $metaDescription,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'type' => 'article',
            'siteName' => $siteName,
            'locale' => $locale,
            'image' => $image ? url($image) : $logo,
            'imageAlt' => data_get($primaryMedia, 'alt') ?: $content->title,
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
                        '@type' => $content->feed_type === 'news' ? 'NewsArticle' : 'Article',
                        '@id' => $canonical.'#post',
                        'headline' => $content->title,
                        'description' => $metaDescription,
                        'url' => $canonical,
                        'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
                        'isPartOf' => ['@type' => 'CollectionPage', '@id' => route('feed.index').'#webpage'],
                        'inLanguage' => $locale,
                        'datePublished' => $content->published_at?->toISOString(),
                        'dateModified' => $content->updated_at?->toISOString(),
                        'author' => ['@id' => $organizationId],
                        'publisher' => ['@id' => $organizationId],
                        ...($image ? ['image' => [[
                            '@type' => 'ImageObject',
                            'url' => url($image),
                            ...(data_get($primaryMedia, 'width') ? ['width' => (int) data_get($primaryMedia, 'width')] : []),
                            ...(data_get($primaryMedia, 'height') ? ['height' => (int) data_get($primaryMedia, 'height')] : []),
                        ]]] : []),
                        ...($content->game && $authorUrl ? ['about' => [
                            '@type' => 'VideoGame',
                            'name' => $content->game->name,
                            'url' => url($authorUrl),
                        ]] : []),
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
}
