<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Game;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\Ticket;
use App\Services\FeedService;
use App\Services\GameRadarService;
use App\Services\MediaStorage;
use App\Services\ProductPriceService;
use App\Services\SmartSearchService;
use App\Services\StorefrontDataService;
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
    ): JsonResponse {
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

        return response()->json([
            'slides' => $slides,
            'categories' => $storefront->navigation(),
            'featured_products' => Product::query()
                ->with($relations)
                ->publiclyVisible()
                ->where('featured', true)
                ->latest()
                ->limit(12)
                ->get()
                ->map($productMap)
                ->values(),
            'latest_products' => Product::query()
                ->with($relations)
                ->publiclyVisible()
                ->latest()
                ->limit(12)
                ->get()
                ->map($productMap)
                ->values(),
            'latest_feed' => $feed->latestImportantPreview($request, 10),
            'latest_studios' => $studios,
            'game_radar' => $radarPreview,
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
