<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\HomeSettingsController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Game;
use App\Models\HomeSection;
use App\Models\HomeSlide;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Services\FeedService;
use App\Services\FollowedGameWatchService;
use App\Services\GameEventService;
use App\Services\GameRadarService;
use App\Services\HomeExperienceService;
use App\Services\HomePublicCacheService;
use App\Services\MediaStorage;
use App\Services\ProductPriceService;
use App\Services\StorefrontDataService;
use App\Services\UserGamingRelevanceService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, ProductPriceService $prices, StorefrontDataService $storefront, FeedService $feed, GameRadarService $radar, UserGamingRelevanceService $relevance, GameEventService $gameEvents, FollowedGameWatchService $watch, HomeExperienceService $homeExperience, HomePublicCacheService $homePublic): Response
    {
        $settings = [...HomeSettingsController::DEFAULTS, ...$homeExperience->settings()];
        $homeExperienceState = $homeExperience->resolve($settings, $request->user());

        $previewTemplate = $request->user()?->is_admin
            ? $request->string('preview_home_template')->toString()
            : '';
        $templateConfig = config("home-experience.templates.{$previewTemplate}");
        $isAdminPreviewRequest = $request->boolean('admin_template_preview');
        $isAdminTemplatePreview = false;
        if (
            $previewTemplate !== ''
            && is_array($templateConfig)
            && (
                (bool) ($templateConfig['available'] ?? false)
                || ($isAdminPreviewRequest && (bool) ($templateConfig['previewable'] ?? false))
            )
        ) {
            $homeExperienceState = [
                ...$homeExperienceState,
                'effective_template' => $previewTemplate,
                'focus' => $templateConfig['focus'] ?? 'balanced',
                'source' => 'preview',
            ];
            $isAdminTemplatePreview = $isAdminPreviewRequest;
        }

        $limit = (int) $settings['products_limit'];
        if ($homeExperienceState['focus'] === 'products') {
            $limit = max(12, $limit);
        }
        $freshCutoff = now()->subDays(14);
        $cardRelations = ['category:id,name', 'type:id,title', 'game:id,name,developer,publisher', 'platforms:id,name', 'attributeValues.attribute:id,name,slug', 'coverMedia', 'variants:id,product_id,status'];
        $productMap = fn (Product $product) => $storefront->product($product, $request->user());
        $loadPublicPreviewData = ! $isAdminTemplatePreview || $previewTemplate === 'nexus_focus';
        $radarItems = $loadPublicPreviewData
            ? collect($radar->linkedSnapshot()['items'] ?? [])
            : collect();
        $personalizedHome = $isAdminTemplatePreview
            ? null
            : $this->personalizedHome($request, $feed, $relevance, $gameEvents, $watch, $radarItems);

        $latestStudios = $loadPublicPreviewData
            ? $homePublic->latestStudios()
            : collect();
        $latestGames = $loadPublicPreviewData
            ? $homePublic->latestGames()
            : collect();

        $slides = $homePublic->slides();

        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('home');
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $socialImage = url((string) ($slides->first()['desktop_image_url'] ?? $logo));
        $socialImageAlt = (string) ($slides->first()['alt'] ?? $slides->first()['title'] ?? "لوگوی {$siteName}");
        $usesEagerProducts = in_array(
            (string) ($homeExperienceState['effective_template'] ?? 'default'),
            ['dual_spotlight', 'storefront', 'nexus_focus'],
            true,
        );

        $previewProducts = $homePublic->previewProducts();
        $previewChannels = $homePublic->previewChannels();
        $previewVideos = $homePublic->previewVideos();

        $previewRadar = (function () use ($radarItems) {
            $ps5 = $radarItems
                ->filter(fn (array $item) => ($item['psn']['available'] ?? false) === true)
                ->take(2);
            $xbox = $radarItems
                ->filter(fn (array $item) => ($item['xbox']['available'] ?? false) === true)
                ->take(2);

            return $ps5->concat($xbox)->unique('id')->take(3)->values();
        })();

        $previewFeedLimit = (string) ($homeExperienceState['effective_template'] ?? 'default') === 'nexus_focus'
            ? 10
            : 3;
        $previewLatestFeed = $loadPublicPreviewData
            ? collect($feed->latestImportantPreview($request, $previewFeedLimit))
            : collect();
        $previewLatestArrivalsFeed =
            (string) ($homeExperienceState['effective_template'] ?? 'default') === 'nexus_focus'
                ? collect($feed->latestPreview(12))
                : collect();

        $homePreview = [
            'latestArrivalsFeed' => $previewLatestArrivalsFeed,
            'latestStudios' => $latestStudios->take(3)->values(),
            'latestGames' => $latestGames,
            'gameRadar' => $previewRadar,
            'channels' => $previewChannels,
            'freshContent' => $previewVideos,
            'latestProducts' => $previewProducts,
        ];

        $heroFeaturedProducts = $usesEagerProducts
            ? Product::query()->with($cardRelations)->publiclyVisible()->where('featured', true)->latest()->limit($limit)->get()->map($productMap)
            : collect();
        $heroLatestProducts = $usesEagerProducts
            ? Product::query()->with($cardRelations)->publiclyVisible()->latest()->limit($limit)->get()->map($productMap)
            : collect();


        $seoTitle = trim((string) ($settings['seo_title'] ?? '')) ?: "فروشگاه بازی و تجهیزات گیمینگ | {$siteName}";
        $seoDescription = trim((string) ($settings['seo_description'] ?? ''));
        if ($seoDescription === '') {
            $seoDescription = "اخبار، ویدیوها، بازی‌ها، استودیوها و تازه‌های دنیای گیمینگ را در {$siteName} دنبال کنید؛ همراه با فروشگاه تخصصی بازی و تجهیزات گیمینگ.";
        }
        $seoDescription = mb_substr($seoDescription, 0, 155);
        $seo = Seo::page([
            'title' => $seoTitle,
            'description' => $seoDescription,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'type' => 'website',
            'siteName' => $siteName,
            'locale' => $locale,
            'image' => $socialImage,
            'imageAlt' => $socialImageAlt,
            'heading' => "فروشگاه و پلتفرم گیمینگ {$siteName}",
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $canonical.'#organization',
                        'name' => $siteName,
                        'url' => $canonical,
                        'logo' => [
                            '@type' => 'ImageObject',
                            'url' => $logo,
                        ],
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => $canonical.'#website',
                        'url' => $canonical,
                        'name' => $siteName,
                        'description' => $seoDescription,
                        'inLanguage' => $locale,
                        'publisher' => ['@id' => $canonical.'#organization'],
                        'potentialAction' => [
                            '@type' => 'SearchAction',
                            'target' => [
                                '@type' => 'EntryPoint',
                                'urlTemplate' => route('search').'?q={search_term_string}',
                            ],
                            'query-input' => 'required name=search_term_string',
                        ],
                    ],
                ],
            ],
        ]);

        return Inertia::render('Home', [
            ...$seo,
            'personalizedHome' => $personalizedHome,
            'homeExperience' => $homeExperienceState,
            'homePreview' => $homePreview,
            'heroFeaturedProducts' => $heroFeaturedProducts,
            'heroLatestProducts' => $heroLatestProducts,
            'latestFeed' => $previewLatestFeed,
            'latestFeedFull' => Inertia::optional(fn () => $isAdminTemplatePreview
                ? collect()
                : $feed->latestImportantPreview($request, 8)),
            'latestStudios' => Inertia::optional(fn () => $latestStudios),
            'gameRadar' => Inertia::optional(fn () => (function () use ($radarItems) {
                $items = $radarItems;

                $ps5 = $items
                    ->filter(fn (array $item) => ($item['psn']['available'] ?? false) === true)
                    ->take(8);
                $xbox = $items
                    ->filter(fn (array $item) => ($item['xbox']['available'] ?? false) === true)
                    ->take(8);

                return $ps5
                    ->concat($xbox)
                    ->unique('id')
                    ->values();
            })()),
            'settings' => $settings,
            'slides' => $slides,
            'featuredProducts' => $usesEagerProducts
                ? $heroFeaturedProducts
                : Inertia::optional(fn () => Product::query()->with($cardRelations)->publiclyVisible()->where('featured', true)->latest()->limit($limit)->get()->map($productMap)),
            'latestProducts' => $usesEagerProducts
                ? $heroLatestProducts
                : Inertia::optional(fn () => Product::query()->with($cardRelations)->publiclyVisible()->latest()->limit($limit)->get()->map($productMap)),
            'contentSections' => Inertia::optional(fn () => HomeSection::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (HomeSection $section) use ($request, $prices, $storefront, $cardRelations) {
                if ($section->content_type === 'products') {
                    $query = Product::query()->with($cardRelations)->publiclyVisible()
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('sold_stock'))
                        ->when($section->query_type === 'category', fn ($query) => $query->where('category_id', $section->category_id))
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []));

                    if (! in_array($section->query_type, ['popular'], true)) {
                        $query->latest();
                    }

                    $items = $query->limit($section->items_limit)->get()->map(fn (Product $product) => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'url' => route('products.show', $product->slug, false),
                        'eyebrow' => $product->category?->name,
                        'badge' => $product->badge,
                        'image_url' => MediaStorage::url($product->coverMedia?->path),
                        'pricing' => $prices->forUser($product, $request->user()),
                        'meta_badges' => $storefront->product($product, $request->user())['meta_badges'],
                    ]);
                } elseif (in_array($section->content_type, ['categories', 'games', 'brands', 'platforms'], true)) {
                    $model = match ($section->content_type) {
                        'categories' => Category::class,
                        'games' => Game::class,
                        'brands' => Brand::class,
                        'platforms' => Platform::class,
                    };
                    $imageField = match ($section->content_type) {
                        'categories' => 'image',
                        'games' => 'cover',
                        'brands' => 'logo',
                        'platforms' => 'icon',
                    };
                    $query = $model::query()->whereIn('status', ['active', 'published'])
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []))
                        ->when($section->content_type === 'games' || $section->query_type !== 'manual', fn ($query) => $query->latest());

                    $items = $query->limit($section->items_limit)->get()->map(fn ($item) => [
                        'id' => $item->id,
                        'title' => $item->name,
                        'url' => $section->content_type === 'categories'
                            ? "/categories/{$item->slug}"
                            : '/search?q='.urlencode($item->name),
                        'eyebrow' => match ($section->content_type) {
                            'categories' => 'دسته‌بندی',
                            'games' => 'بازی',
                            'brands' => 'برند',
                            'platforms' => 'پلتفرم',
                        },
                        'excerpt' => $item->description ?? $item->manufacturer ?? null,
                        'image_url' => MediaStorage::url($item->{$imageField}),
                    ]);
                } else {
                    $query = SocialContent::query()
                        ->published()
                        ->where('type', rtrim($section->content_type, 's'))
                        ->with(['media', 'relatedContent:id,thumbnail', 'relatedProduct:id', 'relatedProduct.coverMedia'])
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('views'))
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []));

                    if ($section->query_type !== 'popular') {
                        $query->latest('published_at');
                    }

                    $items = $query->limit($section->items_limit)->get()->map(function (SocialContent $content) use ($section) {
                        $imagePath = $content->thumbnail;

                        if (! $imagePath) {
                            $imageMedia = $content->media->first(fn ($media) => $media->type === 'image' && filled($media->path));
                            $videoMedia = $content->media->first(fn ($media) => $media->type === 'video' && filled($media->thumbnail));
                            $imagePath = $imageMedia?->path
                                ?: $videoMedia?->thumbnail
                                ?: $content->relatedContent?->thumbnail
                                ?: $content->relatedProduct?->coverMedia?->path;
                        }

                        return [
                            'id' => $content->id,
                            'title' => $content->title,
                            'url' => "/{$section->content_type}/{$content->slug}",
                            'eyebrow' => match ($content->type) {
                                'video' => 'ویدیو', 'short' => 'ویدیوی کوتاه', default => 'پست'
                            },
                            'excerpt' => $content->excerpt,
                            'image_url' => MediaStorage::url($imagePath),
                            'duration' => $content->duration,
                            'views' => $content->views,
                        ];
                    });
                }

                return [
                    ...$section->only(['id', 'title', 'subtitle', 'content_type', 'layout']),
                    'items' => $items,
                ];
            })->filter(fn (array $section) => $section['items']->isNotEmpty())->values()->pipe(function ($sections) {
                $feedIndex = $sections->search(fn (array $section) =>
                    $section['content_type'] === 'posts'
                    || str_contains($section['title'], 'دنیای گیمینگ')
                );
                $discoverIndex = $sections->search(fn (array $section) =>
                    str_contains($section['title'], 'دنیای بازی را کشف کن')
                );

                if ($discoverIndex === false) {
                    $discoverIndex = $sections->search(fn (array $section) => $section['content_type'] === 'games');
                }

                if ($feedIndex === false || $discoverIndex === false || $feedIndex === $discoverIndex - 1) {
                    return $sections;
                }

                $feedSection = $sections->get($feedIndex);
                $remaining = $sections
                    ->reject(fn (array $_, int $index) => $index === $feedIndex)
                    ->values();
                $discoverIndex = $remaining->search(fn (array $section) =>
                    str_contains($section['title'], 'دنیای بازی را کشف کن')
                );

                if ($discoverIndex === false) {
                    $discoverIndex = $remaining->search(fn (array $section) => $section['content_type'] === 'games');
                }

                if ($discoverIndex === false) {
                    return $remaining->push($feedSection);
                }

                return $remaining
                    ->take($discoverIndex)
                    ->concat([$feedSection])
                    ->concat($remaining->slice($discoverIndex))
                    ->values();
            })),
            'freshContent' => Inertia::optional(fn () => Product::query()
                ->with(['category:id,name', 'coverMedia'])
                ->publiclyVisible()
                ->where(fn ($query) => $query->where('published_at', '>=', $freshCutoff)->orWhere(fn ($query) => $query->whereNull('published_at')->where('created_at', '>=', $freshCutoff)))
                ->orderByRaw('COALESCE(published_at, created_at) DESC')
                ->limit(8)
                ->get()
                ->map(fn (Product $product) => [
                    'key' => 'product-'.$product->id, 'type' => 'product', 'title' => $product->title,
                    'url' => route('products.show', $product->slug, false),
                    'image_url' => MediaStorage::url($product->coverMedia?->path),
                    'eyebrow' => $product->category?->name ?? 'محصول گیمینگ',
                    'published_at' => ($product->published_at ?? $product->created_at)->toISOString(),
                    'pricing' => $prices->forUser($product, $request->user()),
                ])
                ->concat(SocialContent::query()->published()->where('type', 'video')->where('published_at', '>=', $freshCutoff)
                    ->with('media')
                    ->latest('published_at')->limit(8)->get()->map(function (SocialContent $video) {
                        $primaryVideoMedia = $video->media->first(
                            fn ($media) => $media->type === 'video' && filled($media->path),
                        );
                        $primaryImageMedia = $video->media->first(
                            fn ($media) => $media->type === 'image' && filled($media->path),
                        );
                        $thumbnailPath = $video->thumbnail
                            ?: $primaryVideoMedia?->thumbnail
                            ?: $primaryImageMedia?->path;

                        return [
                            'key' => 'video-'.$video->id, 'type' => 'video', 'title' => $video->title,
                            'url' => route('content.show', ['type' => 'videos', 'content' => $video->slug], false),
                            'image_url' => MediaStorage::url($thumbnailPath), 'eyebrow' => 'ویدیوی بلند',
                            'published_at' => $video->published_at->toISOString(), 'duration' => $video->duration, 'views' => $video->views,
                        ];
                    }))
                ->sortByDesc('published_at')->take(10)->values()),
            'channels' => Inertia::optional(fn () => $homePublic->channels()),
        ]);
    }

    private function personalizedHome(Request $request, FeedService $feed, UserGamingRelevanceService $relevance, GameEventService $gameEvents, FollowedGameWatchService $watch, $radarItems): ?array
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $followedGames = $user->subscribedGames()
            ->whereIn('games.status', ['active', 'published'])
            ->with([
                'playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
            ])
            ->orderByDesc('game_subscriptions.created_at')
            ->limit(12)
            ->get(['games.id', 'games.name', 'games.slug', 'games.cover']);

        $profile = $relevance->profile($request, $followedGames);
        $gameScores = $profile['game_scores'] ?? [];

        $followedGames = $followedGames
            ->sortByDesc(fn (Game $game) => (float) ($gameScores[$game->id] ?? 0))
            ->values();

        $relevantGameIds = collect(array_keys($gameScores))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->take(16)
            ->values();
        $gameIdLookup = $relevantGameIds->flip();

        $matchedRadar = $relevantGameIds->isEmpty()
            ? collect()
            : collect($radarItems)
                ->filter(fn (array $item) => $gameIdLookup->has((int) ($item['playnexus_game_id'] ?? 0)))
                ->take(6)
                ->values();

        $mediaCloudRadar = $matchedRadar
            ->concat(
                collect($radarItems)
                    ->filter(fn (array $item) => filled($item['banner_url'] ?? null) || filled($item['cover_url'] ?? null))
            )
            ->unique(fn (array $item) => mb_strtolower(trim((string) ($item['title'] ?? $item['id'] ?? ''))))
            ->take(8)
            ->map(fn (array $item) => [
                'id' => (string) ($item['id'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'banner_url' => $item['banner_url'] ?? null,
                'cover_url' => $item['cover_url'] ?? null,
                'playnexus_url' => $item['playnexus_url'] ?? null,
            ])
            ->values();

        $focusGame = null;
        if ($profile['focus_game_id']) {
            $focusGame = Game::query()
                ->whereKey($profile['focus_game_id'])
                ->whereIn('status', ['active', 'published'])
                ->with([
                    'playlists' => fn ($query) => $query
                        ->publiclyVisible()
                        ->whereNotNull('logo')
                        ->select(['id', 'game_id', 'logo', 'sort_order']),
                ])
                ->first(['id', 'name', 'slug', 'cover']);
        }

        return [
            'followed_games' => $followedGames->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'url' => route('channels.show', $game->slug, false),
                'image_url' => MediaStorage::url($game->cover ?: $game->playlists->first()?->logo),
            ])->values(),
            'events' => $gameEvents->forProfile($profile, 8),
            'videos' => $feed->smartVideosForProfile($request, $profile, 4),
            'feed' => $feed->smartEditorialForProfile($request, $profile, 8),
            'radar' => $matchedRadar,
            'media_cloud_radar' => $mediaCloudRadar,
            'watch' => $watch->summaryForGames($followedGames->pluck('id')),
            'intelligence' => [
                'confidence' => $profile['confidence'],
                'top_signals' => $profile['top_signals'],
                'focus_reason' => $profile['focus_reason'],
                'focus_game' => $focusGame ? [
                    'id' => $focusGame->id,
                    'name' => $focusGame->name,
                    'url' => route('channels.show', $focusGame->slug, false),
                    'image_url' => MediaStorage::url($focusGame->cover ?: $focusGame->playlists->first()?->logo),
                ] : null,
            ],
            'updated_at' => now()->toISOString(),
        ];
    }

    private function navigationCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image_url' => MediaStorage::url($category->image),
            'products_count' => $category->products_count ?? 0,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn (Category $child) => $this->navigationCategory($child))->values()
                : [],
        ];
    }
}
