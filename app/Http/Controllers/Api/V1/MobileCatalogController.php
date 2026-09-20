<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
use App\Models\Ticket;
use App\Services\FeedService;
use App\Services\FollowedGameWatchService;
use App\Services\GameEventService;
use App\Services\GameRadarService;
use App\Services\MediaStorage;
use App\Services\ProductPriceService;
use App\Services\SmartSearchService;
use App\Services\StorefrontDataService;
use App\Services\UserGamingRelevanceService;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class MobileCatalogController extends Controller
{
    public function meta(): JsonResponse
    {
        return response()->json([
            'api_version' => 'v1',
            'app' => [
                'name' => (string) config('app.name', 'PlayNexus'),
                'locale' => (string) config('app.locale', 'fa'),
                'min_version' => (string) config('mobile-api.min_app_version', '1.0.0'),
                'current_version' => (string) config('mobile-api.current_app_version', '1.0.0'),
            ],
            'features' => [
                'feed' => true,
                'shorts' => true,
                'videos' => true,
                'discover' => true,
                'game_radar' => true,
                'store' => true,
                'cart' => true,
                'checkout' => true,
                'wallet' => true,
                'exchange' => true,
                'tickets' => true,
                'push' => true,
                'google_sign_in' => true,
            ],
        ]);
    }

    public function home(
        Request $request,
        StorefrontDataService $storefront,
        FeedService $feed,
        GameRadarService $radar,
        ProductPriceService $prices,
        UserGamingRelevanceService $relevance,
        GameEventService $gameEvents,
        FollowedGameWatchService $watch,
    ): JsonResponse {
        $settings = [...HomeSettingsController::DEFAULTS, ...(HomeSetting::query()->first()?->content ?? [])];
        $limit = max(1, min(30, (int) ($settings['products_limit'] ?? 12)));
        $relations = $this->productRelations();
        $productMap = fn (Product $product) => $storefront->product($product, $request->user());

        $slides = HomeSlide::query()
            ->visible()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (HomeSlide $slide) => [
                ...$slide->only([
                    'id', 'title', 'eyebrow', 'description', 'alt', 'link_type',
                    'product_id', 'button_label', 'button_url',
                    'secondary_button_label', 'secondary_button_url',
                ]),
                'desktop_image_url' => MediaStorage::url($slide->desktop_image),
                'mobile_image_url' => MediaStorage::url($slide->mobile_image),
            ])
            ->values();

        $studios = Studio::query()
            ->where('status', 'active')
            ->withCount([
                'games' => fn ($query) => $query->whereIn('status', ['active', 'published']),
            ])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Studio $studio) => [
                'id' => $studio->id,
                'name' => $studio->name,
                'slug' => $studio->slug,
                'logo_url' => MediaStorage::url($studio->logo),
                'background_url' => MediaStorage::url($studio->background),
                'channels_count' => (int) $studio->games_count,
            ])
            ->values();

        $radarItems = collect($radar->linkedSnapshot()['items'] ?? []);
        $radarPreview = $radarItems
            ->filter(fn (array $item) => ($item['psn']['available'] ?? false) || ($item['xbox']['available'] ?? false))
            ->take(12)
            ->values();
        $personalized = $this->personalizedHome(
            $request,
            $feed,
            $relevance,
            $gameEvents,
            $watch,
            $radarItems,
        );

        $latestVideos = SocialContent::query()
            ->published()
            ->where('type', 'video')
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
            ->limit(10)
            ->get()
            ->map(fn (SocialContent $video) => $storefront->content($video))
            ->values();

        $latestGames = Game::query()
            ->whereIn('status', ['active', 'published'])
            ->with('studio:id,name,slug,logo,status')
            ->latest()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'developer' => $game->developer,
                'publisher' => $game->publisher,
                'cover_url' => MediaStorage::url($game->cover),
                'background_url' => MediaStorage::url($game->background),
                'created_at' => $game->created_at?->toISOString(),
                'studio' => $game->studio?->status === 'active' ? [
                    'id' => $game->studio->id,
                    'name' => $game->studio->name,
                    'slug' => $game->studio->slug,
                    'logo_url' => MediaStorage::url($game->studio->logo),
                ] : null,
            ])
            ->values();

        $nexusLatest = $this->nexusLatest($request, $storefront);

        return response()->json([
            'settings' => $settings,
            'slides' => $slides,
            'categories' => $storefront->navigation(),
            'personalized_home' => $personalized,
            'featured_products' => Product::query()
                ->with($relations)
                ->publiclyVisible()
                ->where('featured', true)
                ->latest()
                ->limit($limit)
                ->get()
                ->map($productMap)
                ->values(),
            'latest_products' => Product::query()
                ->with($relations)
                ->publiclyVisible()
                ->latest()
                ->limit($limit)
                ->get()
                ->map($productMap)
                ->values(),
            'latest_feed' => $feed->latestImportantPreview($request, 10),
            'latest_videos' => $latestVideos,
            'latest_games' => $latestGames,
            'nexus_latest' => $nexusLatest,
            'latest_studios' => $studios,
            'game_radar' => $radarPreview,
            'content_sections' => $this->contentSections($request, $prices, $storefront),
            'fresh_content' => $this->freshContent($request, $prices),
            'channels' => $this->homeChannels(),
        ]);
    }

    public function products(Request $request, StorefrontDataService $storefront): JsonResponse
    {
        $products = $this->productQuery($request)
            ->paginate(max(1, min(50, $request->integer('per_page', 18))))
            ->withQueryString()
            ->through(fn (Product $product) => $storefront->product($product, $request->user()));

        return response()->json($products);
    }

    public function offers(Request $request, StorefrontDataService $storefront): JsonResponse
    {
        $request->merge(['offers' => true]);

        return $this->products($request, $storefront);
    }

    public function exchangeProducts(Request $request, StorefrontDataService $storefront): JsonResponse
    {
        $request->merge(['trade' => true]);

        return $this->products($request, $storefront);
    }

    public function product(
        Request $request,
        Product $product,
        ProductPriceService $prices,
        FeedService $feed,
        StorefrontDataService $storefront,
    ): JsonResponse {
        abort_unless(
            Product::query()->publiclyVisible()->whereKey($product->getKey())->exists(),
            404,
        );

        $product->loadMissing([
            'category:id,name,slug',
            'brand:id,name',
            'game:id,name,slug,cover,background,developer,publisher',
            'platforms:id,name',
            'attributeValues.attribute:id,name,slug',
            'media',
            'variants',
        ]);

        $isPartner = $request->user()?->role === 'partner';
        $exchangeRequestId = null;

        if ($request->user() && $request->integer('exchange_request_id')) {
            $exchangeRequestId = Ticket::query()
                ->whereKey($request->integer('exchange_request_id'))
                ->where('type', 'exchange')
                ->where('user_id', $request->user()->id)
                ->where('exchange_status', 'accepted')
                ->whereNull('exchange_order_id')
                ->where('target_product_id', $product->id)
                ->where(fn ($query) => $query
                    ->whereNull('exchange_credit_expires_at')
                    ->orWhere('exchange_credit_expires_at', '>', now()))
                ->value('id');
        }

        $detail = [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'short_description' => RichText::plainText($product->short_description),
            'description' => RichText::sanitize($product->description),
            'availability' => $product->availability,
            'stock' => $product->show_stock
                ? max(0, (int) $product->stock - (int) $product->reserved_stock)
                : null,
            'trade_enabled' => (bool) $product->trade_enabled,
            'release_date' => $product->release_date?->format('Y-m-d'),
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ] : null,
            'brand' => $product->brand?->only(['id', 'name']),
            'game' => $product->game ? [
                'id' => $product->game->id,
                'name' => $product->game->name,
                'slug' => $product->game->slug,
                'cover_url' => MediaStorage::url($product->game->cover),
                'background_url' => MediaStorage::url($product->game->background),
                'developer' => $product->game->developer,
                'publisher' => $product->game->publisher,
            ] : null,
            'platforms' => $product->platforms
                ->map(fn ($platform) => $platform->only(['id', 'name']))
                ->values(),
            'attributes' => $product->attributeValues
                ->filter(fn ($value) => $value->attribute)
                ->map(fn ($value) => [
                    'name' => $value->attribute->name,
                    'slug' => $value->attribute->slug,
                    'value' => $value->value,
                ])
                ->values(),
            'media' => $product->media
                ->map(fn ($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => MediaStorage::url($media->path),
                    'alt' => $media->alt ?: $product->title,
                    'is_primary' => (bool) $media->is_primary,
                ])
                ->values(),
            'variants' => $product->variants
                ->where('status', 'active')
                ->values()
                ->map(function ($variant) use ($isPartner, $product) {
                    $regular = $variant->price ?? $product->price;
                    $customer = $variant->discount_price ?? $regular;
                    $final = $isPartner && $variant->partner_price !== null
                        ? $variant->partner_price
                        : $customer;

                    return [
                        'id' => $variant->id,
                        'name' => $variant->name,
                        'attributes' => $variant->attributes ?? [],
                        'stock' => (int) $variant->stock,
                        'pricing' => [
                            'regular_price' => $regular,
                            'sale_price' => $customer,
                            'final_price' => $final,
                            'is_partner_price' => $isPartner && $variant->partner_price !== null,
                            'discount_amount' => max(0, $regular - $customer),
                        ],
                    ];
                }),
            'pricing' => $prices->forUser($product, $request->user()),
        ];

        return response()->json([
            'product' => $detail,
            'exchange_request_id' => $exchangeRequestId,
            'latest_feed' => $feed->latestPostsExcept($request, 0, 4),
            'latest_videos' => SocialContent::query()
                ->published()
                ->where('type', 'video')
                ->with('game:id,name,slug,cover')
                ->latest('published_at')
                ->latest('id')
                ->limit(4)
                ->get()
                ->map(fn (SocialContent $video) => $storefront->content($video))
                ->values(),
            'related_products' => Product::query()
                ->publiclyVisible()
                ->whereKeyNot($product->id)
                ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
                ->with($this->productRelations())
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Product $related) => $storefront->product($related, $request->user()))
                ->values(),
        ]);
    }

    public function categories(StorefrontDataService $storefront): JsonResponse
    {
        return response()->json(['categories' => $storefront->navigation()]);
    }

    public function category(
        Request $request,
        Category $category,
        StorefrontDataService $storefront,
    ): JsonResponse {
        abort_unless($category->status === 'active', 404);

        $category->load([
            'children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order'),
        ]);

        $allCategories = Category::query()
            ->where('status', 'active')
            ->get(['id', 'parent_id']);

        $ids = [$category->id];
        for ($cursor = 0; $cursor < count($ids); $cursor++) {
            array_push(
                $ids,
                ...$allCategories->where('parent_id', $ids[$cursor])->pluck('id')->all(),
            );
        }

        $products = $this->productQuery($request)
            ->whereIn('category_id', $ids)
            ->paginate(max(1, min(50, $request->integer('per_page', 18))))
            ->withQueryString()
            ->through(fn (Product $product) => $storefront->product($product, $request->user()));

        return response()->json([
            'category' => $storefront->category($category),
            'products' => $products,
        ]);
    }

    public function search(
        Request $request,
        StorefrontDataService $storefront,
        SmartSearchService $search,
    ): JsonResponse {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));
        $matches = $search->rankedIds($term);

        $products = Product::query()
            ->whereIn('id', $matches['products'])
            ->with($this->productRelations())
            ->get();

        $contents = SocialContent::query()
            ->whereIn('id', $matches['content'])
            ->with(['game:id,name,slug,cover', 'media'])
            ->get();

        $categories = Category::query()
            ->whereIn('id', $matches['categories'])
            ->withCount(['products' => fn ($query) => $query->publiclyVisible()])
            ->get();

        $games = Game::query()
            ->whereIn('id', $matches['games'])
            ->get();

        return response()->json([
            'query' => $term,
            'products' => $this->sortByRank($products, $matches['products'])
                ->map(fn (Product $item) => $storefront->product($item, $request->user()))
                ->values(),
            'content' => $this->sortByRank($contents, $matches['content'])
                ->map(fn (SocialContent $item) => $storefront->content($item))
                ->values(),
            'categories' => $this->sortByRank($categories, $matches['categories'])
                ->map(fn (Category $item) => $storefront->category($item))
                ->values(),
            'channels' => $this->sortByRank($games, $matches['games'])
                ->map(fn (Game $game) => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'slug' => $game->slug,
                    'developer' => $game->developer,
                    'cover_url' => MediaStorage::url($game->cover),
                ])
                ->values(),
        ]);
    }

    public function suggestions(Request $request, SmartSearchService $search): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));

        return response()->json([
            'suggestions' => Cache::remember(
                'mobile-smart-search:v1:'.sha1(mb_strtolower($term)),
                now()->addSeconds(90),
                fn () => $search->suggestions($term),
            ),
        ]);
    }

    public function radar(GameRadarService $radar): JsonResponse
    {
        return response()->json($radar->linkedSnapshot());
    }

    private function nexusLatest(
        Request $request,
        StorefrontDataService $storefront,
    ): Collection {
        $content = SocialContent::query()
            ->published()
            ->whereIn('type', ['post', 'video'])
            ->with(['game:id,name,slug,cover', 'media'])
            ->latest('published_at')
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(function (SocialContent $item) use ($storefront) {
                $card = $storefront->content($item);

                return [
                    'key' => 'content-'.$item->id,
                    'kind' => $item->type === 'video' ? 'video' : 'feed',
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => $item->game?->name ?? 'PlayNexus',
                    'slug' => $item->slug,
                    'image_url' => $card['thumbnail_url'] ?? null,
                    'url' => $card['url'] ?? null,
                    'created_at' => ($item->published_at ?? $item->created_at)?->toISOString(),
                ];
            });

        $studios = Studio::query()
            ->where('status', 'active')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Studio $studio) => [
                'key' => 'studio-'.$studio->id,
                'kind' => 'studio',
                'id' => $studio->id,
                'title' => $studio->name,
                'subtitle' => 'استودیو جدید',
                'slug' => $studio->slug,
                'image_url' => MediaStorage::url($studio->background ?: $studio->logo),
                'url' => route('studios.show', $studio->slug, false),
                'created_at' => $studio->created_at?->toISOString(),
            ]);

        $games = Game::query()
            ->whereIn('status', ['active', 'published'])
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Game $game) => [
                'key' => 'game-'.$game->id,
                'kind' => 'game',
                'id' => $game->id,
                'title' => $game->name,
                'subtitle' => $game->developer ?: 'بازی جدید',
                'slug' => $game->slug,
                'image_url' => MediaStorage::url($game->background ?: $game->cover),
                'url' => route('channels.show', $game->slug, false),
                'created_at' => $game->created_at?->toISOString(),
            ]);

        $products = Product::query()
            ->with($this->productRelations())
            ->publiclyVisible()
            ->latest()
            ->limit(3)
            ->get()
            ->map(function (Product $product) use ($request, $storefront) {
                $card = $storefront->product($product, $request->user());

                return [
                    'key' => 'product-'.$product->id,
                    'kind' => 'product',
                    'id' => $product->id,
                    'title' => $product->title,
                    'subtitle' => $card['category'] ?? 'محصول جدید',
                    'slug' => $product->slug,
                    'image_url' => $card['cover_url'] ?? null,
                    'url' => route('products.show', $product->slug, false),
                    'created_at' => $product->created_at?->toISOString(),
                ];
            });

        return $content
            ->concat($studios)
            ->concat($games)
            ->concat($products)
            ->sortByDesc(fn (array $item) => $item['created_at'] ?? '')
            ->take(10)
            ->values();
    }

    private function contentSections(
        Request $request,
        ProductPriceService $prices,
        StorefrontDataService $storefront,
    ): Collection {
        return HomeSection::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (HomeSection $section) use ($request, $prices, $storefront) {
                if ($section->content_type === 'products') {
                    $query = Product::query()
                        ->with($this->productRelations())
                        ->publiclyVisible()
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('sold_stock'))
                        ->when($section->query_type === 'category', fn ($query) => $query->where('category_id', $section->category_id))
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []));

                    if ($section->query_type !== 'popular') {
                        $query->latest();
                    }

                    $items = $query
                        ->limit($section->items_limit)
                        ->get()
                        ->map(fn (Product $product) => [
                            ...$storefront->product($product, $request->user()),
                            'pricing' => $prices->forUser($product, $request->user()),
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

                    $query = $model::query()
                        ->whereIn('status', ['active', 'published'])
                        ->when(
                            $section->query_type === 'manual',
                            fn ($query) => $query->whereIn('id', $section->item_ids ?? []),
                        )
                        ->when(
                            $section->content_type === 'games' || $section->query_type !== 'manual',
                            fn ($query) => $query->latest(),
                        );

                    $items = $query
                        ->limit($section->items_limit)
                        ->get()
                        ->map(fn ($item) => [
                            'id' => $item->id,
                            'title' => $item->name,
                            'slug' => $item->slug ?? null,
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
                    $type = rtrim($section->content_type, 's');
                    $query = SocialContent::query()
                        ->published()
                        ->where('type', $type)
                        ->with(['game:id,name,slug,cover', 'media', 'relatedContent:id,thumbnail', 'relatedProduct:id', 'relatedProduct.coverMedia'])
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('views'))
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []));

                    if ($section->query_type !== 'popular') {
                        $query->latest('published_at');
                    }

                    $items = $query
                        ->limit($section->items_limit)
                        ->get()
                        ->map(fn (SocialContent $content) => $storefront->content($content));
                }

                return [
                    ...$section->only(['id', 'title', 'subtitle', 'content_type', 'layout']),
                    'items' => $items->values(),
                ];
            })
            ->filter(fn (array $section) => $section['items']->isNotEmpty())
            ->values();
    }

    private function freshContent(Request $request, ProductPriceService $prices): Collection
    {
        $cutoff = now()->subDays(14);

        $products = Product::query()
            ->with(['category:id,name', 'coverMedia'])
            ->publiclyVisible()
            ->where(fn ($query) => $query
                ->where('published_at', '>=', $cutoff)
                ->orWhere(fn ($query) => $query
                    ->whereNull('published_at')
                    ->where('created_at', '>=', $cutoff)))
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->limit(8)
            ->get()
            ->map(fn (Product $product) => [
                'key' => 'product-'.$product->id,
                'type' => 'product',
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'image_url' => MediaStorage::url($product->coverMedia?->path),
                'eyebrow' => $product->category?->name ?? 'محصول گیمینگ',
                'published_at' => ($product->published_at ?? $product->created_at)->toISOString(),
                'pricing' => $prices->forUser($product, $request->user()),
            ]);

        $videos = SocialContent::query()
            ->published()
            ->where('type', 'video')
            ->where('published_at', '>=', $cutoff)
            ->latest('published_at')
            ->limit(8)
            ->get()
            ->map(fn (SocialContent $video) => [
                'key' => 'video-'.$video->id,
                'type' => 'video',
                'id' => $video->id,
                'title' => $video->title,
                'slug' => $video->slug,
                'image_url' => MediaStorage::url($video->thumbnail),
                'eyebrow' => 'ویدیوی بلند',
                'published_at' => $video->published_at->toISOString(),
                'duration' => $video->duration,
                'views' => $video->views,
            ]);

        return $products
            ->concat($videos)
            ->sortByDesc('published_at')
            ->take(10)
            ->values();
    }

    private function homeChannels(): Collection
    {
        return Game::query()
            ->whereIn('status', ['active', 'published'])
            ->with([
                'playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
            ])
            ->withCount([
                'videos' => fn ($query) => $query->published(),
                'subscribers',
            ])
            ->latest()
            ->latest('id')
            ->limit(16)
            ->get(['id', 'name', 'slug', 'cover'])
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'image_url' => MediaStorage::url($game->cover ?: $game->playlists->first()?->logo),
                'videos_count' => (int) $game->videos_count,
                'subscribers_count' => (int) $game->subscribers_count,
            ])
            ->values();
    }

    private function personalizedHome(
        Request $request,
        FeedService $feed,
        UserGamingRelevanceService $relevance,
        GameEventService $gameEvents,
        FollowedGameWatchService $watch,
        Collection $radarItems,
    ): ?array {
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
        $lookup = $relevantGameIds->flip();

        $matchedRadar = $relevantGameIds->isEmpty()
            ? collect()
            : $radarItems
                ->filter(fn (array $item) => $lookup->has((int) ($item['playnexus_game_id'] ?? 0)))
                ->take(6)
                ->values();

        $focusGame = null;
        if ($profile['focus_game_id'] ?? null) {
            $focusGame = Game::query()
                ->whereKey($profile['focus_game_id'])
                ->whereIn('status', ['active', 'published'])
                ->first(['id', 'name', 'slug', 'cover']);
        }

        return [
            'followed_games' => $followedGames->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'image_url' => MediaStorage::url($game->cover ?: $game->playlists->first()?->logo),
            ])->values(),
            'events' => $gameEvents->forProfile($profile, 8),
            'videos' => $feed->smartVideosForProfile($request, $profile, 4),
            'feed' => $feed->smartEditorialForProfile($request, $profile, 8),
            'radar' => $matchedRadar,
            'watch' => $watch->summaryForGames($followedGames->pluck('id')),
            'intelligence' => [
                'confidence' => $profile['confidence'] ?? null,
                'top_signals' => $profile['top_signals'] ?? [],
                'focus_reason' => $profile['focus_reason'] ?? null,
                'focus_game' => $focusGame ? [
                    'id' => $focusGame->id,
                    'name' => $focusGame->name,
                    'slug' => $focusGame->slug,
                    'image_url' => MediaStorage::url($focusGame->cover),
                ] : null,
            ],
            'updated_at' => now()->toISOString(),
        ];
    }

    private function productQuery(Request $request): Builder
    {
        return Product::query()
            ->publiclyVisible()
            ->with($this->productRelations())
            ->search($request->string('q')->toString() ?: null)
            ->when(
                $request->filled('category'),
                fn (Builder $query) => $query->whereHas(
                    'category',
                    fn ($category) => $category->where('slug', $request->string('category')->toString()),
                ),
            )
            ->when($request->boolean('trade'), fn (Builder $query) => $query->where('trade_enabled', true))
            ->when(
                $request->boolean('offers'),
                fn (Builder $query) => $query
                    ->whereNotNull('discount_price')
                    ->whereColumn('discount_price', '<', 'price'),
            )
            ->when(
                $request->string('sort')->toString() === 'popular',
                fn (Builder $query) => $query->orderByDesc('sold_stock'),
            )
            ->when(
                $request->string('sort')->toString() === 'price_asc',
                fn (Builder $query) => $query->orderByRaw('COALESCE(discount_price, price) asc'),
            )
            ->when(
                $request->string('sort')->toString() === 'price_desc',
                fn (Builder $query) => $query->orderByRaw('COALESCE(discount_price, price) desc'),
            )
            ->when(
                ! $request->filled('sort') || $request->string('sort')->toString() === 'latest',
                fn (Builder $query) => $query->latest(),
            );
    }

    private function productRelations(): array
    {
        return [
            'category:id,name',
            'type:id,title',
            'game:id,name,developer,publisher',
            'platforms:id,name',
            'attributeValues.attribute:id,name,slug',
            'coverMedia',
            'variants:id,product_id,status',
        ];
    }

    private function sortByRank(Collection $items, array $ids): Collection
    {
        $order = array_flip($ids);

        return $items
            ->sortBy(fn ($item) => $order[$item->id] ?? PHP_INT_MAX)
            ->values();
    }
}
