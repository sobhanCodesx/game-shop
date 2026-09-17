<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\HomeSettingsController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Game;
use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\ProductPriceService;
use App\Services\StorefrontDataService;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, ProductPriceService $prices, StorefrontDataService $storefront, FeedService $feed): Response
    {
        $settings = [...HomeSettingsController::DEFAULTS, ...(HomeSetting::query()->first()?->content ?? [])];
        $limit = (int) $settings['products_limit'];
        $freshCutoff = now()->subDays(14);
        $cardRelations = ['category:id,name', 'type:id,title', 'game:id,name,developer,publisher', 'platforms:id,name', 'attributeValues.attribute:id,name,slug', 'coverMedia', 'variants:id,product_id,status'];
        $productMap = fn (Product $product) => $storefront->product($product, $request->user());
        $latestStudios = collect();

        if (Schema::hasTable('studios') && Schema::hasTable('games') && Schema::hasColumn('games', 'studio_id')) {
            $latestStudios = Studio::query()->where('status', 'active')
                ->withCount(['games' => fn ($query) => $query->whereIn('status', ['active', 'published'])])
                ->latest()->latest('id')->limit(10)->get()
                ->map(fn (Studio $studio) => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                    'url' => route('studios.show', $studio->slug, false),
                    'logo_url' => MediaStorage::url($studio->logo),
                    'background_url' => MediaStorage::url($studio->background),
                    'channels_count' => $studio->games_count,
                    'created_at' => $studio->created_at?->toISOString(),
                ]);
        }

        $slides = HomeSlide::query()->visible()->orderBy('sort_order')->get()->map(fn (HomeSlide $slide) => [
            ...$slide->only(['id', 'title', 'alt', 'link_type', 'product_id', 'button_url']),
            'desktop_image_url' => MediaStorage::url($slide->desktop_image),
            'mobile_image_url' => MediaStorage::url($slide->mobile_image),
            'target_url' => $slide->button_url ?: '#',
        ]);

        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('home');
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $socialImage = url((string) ($slides->first()['desktop_image_url'] ?? $logo));
        $socialImageAlt = (string) ($slides->first()['alt'] ?? $slides->first()['title'] ?? "لوگوی {$siteName}");
        $seoTitle = trim((string) ($settings['seo_title'] ?? '')) ?: "فروشگاه بازی و تجهیزات گیمینگ | {$siteName}";
        $seoDescription = trim((string) ($settings['seo_description'] ?? '')) ?: "خرید بازی، کنسول و تجهیزات گیمینگ با تضمین اصالت و پشتیبانی تخصصی از {$siteName}.";
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
            'latestFeed' => $feed->latestImportant($request, 8),
            'latestStudios' => $latestStudios,
            'settings' => $settings,
            'slides' => $slides,
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->withCount('products')
                ->with([
                    'children' => fn ($query) => $query
                        ->where('status', 'active')
                        ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')])
                        ->orderBy('sort_order'),
                ])
                ->orderBy('sort_order')
                ->limit(8)
                ->get(['id', 'parent_id', 'name', 'slug', 'image'])
                ->map(fn (Category $category) => $this->navigationCategory($category)),
            'featuredProducts' => Product::query()->with($cardRelations)->publiclyVisible()->where('featured', true)->latest()->limit($limit)->get()->map($productMap),
            'latestProducts' => Product::query()->with($cardRelations)->publiclyVisible()->latest()->limit($limit)->get()->map($productMap),
            'contentSections' => HomeSection::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (HomeSection $section) use ($request, $prices, $storefront, $cardRelations) {
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
            }),
            'freshContent' => Product::query()
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
                    ->latest('published_at')->limit(8)->get()->map(fn (SocialContent $video) => [
                        'key' => 'video-'.$video->id, 'type' => 'video', 'title' => $video->title,
                        'url' => route('content.show', ['type' => 'videos', 'content' => $video->slug], false),
                        'image_url' => MediaStorage::url($video->thumbnail), 'eyebrow' => 'ویدیوی بلند',
                        'published_at' => $video->published_at->toISOString(), 'duration' => $video->duration, 'views' => $video->views,
                    ]))
                ->sortByDesc('published_at')->take(10)->values(),
            'channels' => Game::query()
                ->whereIn('status', ['active', 'published'])
                ->with(['playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order'])])
                ->withCount([
                    'videos' => fn ($query) => $query->published(),
                    'subscribers',
                ])
                ->latest()
                ->latest('id')
                ->limit(16)
                ->get(['id', 'name', 'slug', 'cover'])
                ->map(fn (Game $game) => [
                    ...$game->only(['id', 'name', 'slug']),
                    'url' => route('channels.show', $game->slug, false),
                    'image_url' => MediaStorage::url($game->cover ?: $game->playlists->first()?->logo),
                    'videos_count' => $game->videos_count,
                    'subscribers_count' => $game->subscribers_count,
                ]),
        ]);
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
