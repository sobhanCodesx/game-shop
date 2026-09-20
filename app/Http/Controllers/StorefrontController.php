<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Game;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SocialContent;
use App\Services\ContentViewService;
use App\Services\MediaStorage;
use App\Services\SmartSearchService;
use App\Services\StorefrontDataService;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function shop(Request $request, StorefrontDataService $data): Response
    {
        $isOffers = $request->routeIs('offers.index');
        $products = $this->productQuery($request)
            ->when($isOffers, fn (Builder $query) => $query->whereNotNull('discount_price')->whereColumn('discount_price', '<', 'price'))
            ->paginate(18)->withQueryString()
            ->through(fn (Product $product) => $data->product($product, $request->user()));
        $canonical = route($isOffers ? 'offers.index' : 'shop.index');
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $description = $isOffers
            ? "تخفیف‌ها و پیشنهادهای ویژه بازی و محصولات گیمینگ در {$siteName}."
            : "خرید بازی، اکانت و تجهیزات گیمینگ از فروشگاه {$siteName}؛ مشاهده قیمت، موجودی و جدیدترین محصولات.";
        $pageName = $isOffers ? 'پیشنهادهای ویژه گیمینگ' : "فروشگاه گیمینگ {$siteName}";
        $firstProduct = collect($products->items())->first();
        $image = url(data_get($firstProduct, 'cover_url') ?: (string) config('seo.default_image', '/logo.png'));
        $itemListId = $canonical.'#products';
        $hasFilters = array_intersect(array_keys($request->query()), ['q', 'category', 'sort', 'trade', 'page']) !== [];

        return Inertia::render('Shop/Index', [
            ...Seo::page([
                'title' => $isOffers ? 'تخفیف‌ها و پیشنهادهای ویژه بازی' : "فروشگاه بازی و محصولات گیمینگ {$siteName}",
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $hasFilters
                    ? 'noindex, follow'
                    : 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'website',
                'image' => $image,
                'imageAlt' => data_get($firstProduct, 'cover_alt') ?: "فروشگاه {$siteName}",
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            '@id' => $canonical.'#shop',
                            'name' => $pageName,
                            'url' => $canonical,
                            'description' => $description,
                            'mainEntity' => ['@id' => $itemListId],
                        ],
                        [
                            '@type' => 'ItemList',
                            '@id' => $itemListId,
                            'name' => $isOffers ? 'محصولات تخفیف‌دار' : 'محصولات فروشگاه',
                            'numberOfItems' => count($products->items()),
                            'itemListElement' => collect($products->items())->values()->map(fn (array $product, int $index) => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => $product['title'],
                                'url' => url($product['url']),
                            ])->all(),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => $isOffers ? 'پیشنهادهای ویژه' : 'فروشگاه', 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'products' => $products,
            'filters' => $request->only(['q', 'category', 'sort', 'trade']),
            'tradeOnly' => false,
            'pageType' => $isOffers ? 'offers' : 'shop',
        ]);
    }

    public function exchangeProducts(Request $request, StorefrontDataService $data): Response
    {
        $products = $this->productQuery($request)->where('trade_enabled', true)
            ->paginate(18)->withQueryString()
            ->through(fn (Product $product) => $data->product($product, $request->user()));
        $canonical = route('exchange-products.index');
        $description = 'مشاهده و انتخاب بازی‌ها و محصولات قابل معاوضه؛ ثبت درخواست معاوضه سریع و امن در PlayNexus.';
        $firstProduct = collect($products->items())->first();

        return Inertia::render('Shop/Index', [
            ...Seo::page([
                'title' => 'معاوضه بازی و محصولات گیمینگ',
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $request->hasAny(['q', 'sort', 'page']) ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'website',
                'image' => url(data_get($firstProduct, 'cover_url') ?: (string) config('seo.default_image', '/logo.png')),
                'imageAlt' => data_get($firstProduct, 'cover_alt') ?: 'محصولات قابل معاوضه',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            'name' => 'محصولات قابل معاوضه',
                            'url' => $canonical,
                            'description' => $description,
                        ],
                        [
                            '@type' => 'ItemList',
                            'numberOfItems' => count($products->items()),
                            'itemListElement' => collect($products->items())->values()->map(fn (array $product, int $index) => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => $product['title'],
                                'url' => url($product['url']),
                            ])->all(),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'محصولات قابل معاوضه', 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'products' => $products,
            'filters' => [...$request->only(['q', 'sort']), 'trade' => '1'],
            'tradeOnly' => true,
        ]);
    }

    public function category(Request $request, Category $category, StorefrontDataService $data): Response
    {
        abort_unless($category->status === 'active', 404);
        $category->load(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')]);
        $allCategories = Category::query()->where('status', 'active')->get(['id', 'parent_id']);
        $ids = [$category->id];
        for ($cursor = 0; $cursor < count($ids); $cursor++) {
            array_push($ids, ...$allCategories->where('parent_id', $ids[$cursor])->pluck('id')->all());
        }
        $products = $this->productQuery($request)->whereIn('category_id', $ids)->paginate(18)->withQueryString()
            ->through(fn (Product $product) => $data->product($product, $request->user()));

        return Inertia::render('Categories/Show', [
            'category' => $data->category($category),
            'products' => $products,
            'filters' => $request->only(['q', 'sort', 'trade']),
        ]);
    }

    public function discover(Request $request, StorefrontDataService $data): Response|JsonResponse
    {
        $productMediaFeed = ProductMedia::query()
            ->whereHas('product', fn (Builder $query) => $query->publiclyVisible())
            ->select(['id', 'updated_at as sort_at'])
            ->selectRaw("'product_media' as kind");
        $contentFeed = SocialContent::query()->published()
            ->where('type', '!=', 'short')
            ->where(fn (Builder $query) => $query
                ->whereNotNull('thumbnail')
                ->orWhereNotNull('video_path')
                ->orWhereHas('media'))
            ->select(['id', 'published_at as sort_at'])
            ->selectRaw("'content' as kind");

        // Infinite scroll does not need an expensive total count on every request.
        $feed = DB::query()->fromSub($productMediaFeed->unionAll($contentFeed), 'explore_feed')
            ->orderByDesc('sort_at')->orderByDesc('id')->simplePaginate(18)->withQueryString();
        $rows = collect($feed->items());

        $media = ProductMedia::query()
            ->with(['product' => fn ($query) => $query->with($this->productRelations())])
            ->whereIn('id', $rows->where('kind', 'product_media')->pluck('id'))
            ->get()
            ->keyBy('id');

        $contentQuery = SocialContent::query()
            ->with(['game:id,name,slug,cover', 'media'])
            ->withCount([
                'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                'comments as comments_count' => fn (Builder $query) => $query->published(),
            ]);

        if ($request->user()) {
            $contentQuery->withExists([
                'reactions as is_liked' => fn (Builder $query) => $query
                    ->where('type', 'like')
                    ->where('user_id', $request->user()->id),
            ]);
        }

        $content = $contentQuery
            ->whereIn('id', $rows->where('kind', 'content')->pluck('id'))
            ->get()
            ->keyBy('id');

        $feed->setCollection($rows->map(function (object $row) use ($media, $content, $data, $request) {
            if ($row->kind === 'product_media' && $media->has($row->id)) {
                $item = $media[$row->id];

                return [
                    'key' => "product-media-{$row->id}",
                    'kind' => 'product_media',
                    'data' => [
                        ...$data->product($item->product, $request->user()),
                        'media_url' => MediaStorage::url($item->path),
                        'media_type' => $item->type,
                        'media_alt' => $item->alt ?: $item->product->title,
                    ],
                ];
            }

            if (! $content->has($row->id)) {
                return null;
            }

            $item = $content[$row->id];

            return [
                'key' => "content-{$row->id}",
                'kind' => 'content',
                'data' => [
                    ...$data->content($item),
                    'likes_count' => (int) $item->likes_count,
                    'comments_count' => (int) $item->comments_count,
                    'is_liked' => (bool) ($item->is_liked ?? false),
                    'allow_comments' => (bool) $item->allow_comments,
                ],
            ];
        })->filter()->values());

        if ($request->wantsJson()) {
            return response()->json($feed);
        }

        $canonical = route('discover');
        $description = 'کشف تازه‌ترین بازی‌ها، ویدیوها و محصولات گیمینگ منتخب در اکسپلور PlayNexus.';
        $firstItem = collect($feed->items())->first();
        $imagePath = data_get($firstItem, 'kind') === 'product_media'
            ? data_get($firstItem, 'data.media_url')
            : data_get($firstItem, 'data.thumbnail_url');

        return Inertia::render('Discover/Index', [
            ...Seo::page([
                'title' => 'اکسپلور بازی‌ها و محتوای گیمینگ',
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $request->filled('page') ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                'type' => 'website',
                'image' => url($imagePath ?: (string) config('seo.default_image', '/logo.png')),
                'imageAlt' => data_get($firstItem, 'data.media_alt') ?: data_get($firstItem, 'data.title') ?: 'اکسپلور PlayNexus',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            'name' => 'اکسپلور PlayNexus',
                            'url' => $canonical,
                            'description' => $description,
                        ],
                        [
                            '@type' => 'ItemList',
                            'numberOfItems' => count($feed->items()),
                            'itemListElement' => collect($feed->items())->values()->map(fn (array $item, int $index) => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => data_get($item, 'data.title'),
                                'url' => url(data_get($item, 'data.url')),
                            ])->all(),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'اکسپلور', 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'feed' => $feed,
        ]);
    }

    public function recordDiscoverView(
        Request $request,
        SocialContent $content,
        ContentViewService $views,
    ): JsonResponse {
        abort_unless(
            in_array($content->type, ['post', 'video'], true)
                && $content->status === 'published'
                && $content->published_at?->isPast(),
            404,
        );

        $views->record($request, $content);

        return response()->json(['views' => (int) $content->views]);
    }

    public function videos(Request $request, StorefrontDataService $data): Response
    {
        $videos = SocialContent::query()->published()->where('type', 'video')
            ->with(['game:id,name,slug,cover', 'media'])
            ->latest('published_at')->paginate(18)->through(fn ($item) => $data->content($item));
        $canonical = route('videos.index');
        $description = 'تماشای تازه‌ترین تریلرها، گیم‌پلی‌ها، بررسی‌ها و ویدیوهای دنیای بازی در PlayNexus.';
        $firstVideo = collect($videos->items())->first();

        return Inertia::render('Videos/Index', [
            ...Seo::page([
                'title' => 'ویدیوهای گیمینگ؛ تریلر، گیم‌پلی و بررسی',
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $request->filled('page') ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                'type' => 'website',
                'image' => url(data_get($firstVideo, 'thumbnail_url') ?: (string) config('seo.default_image', '/logo.png')),
                'imageAlt' => data_get($firstVideo, 'title') ?: 'ویدیوهای گیمینگ PlayNexus',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            'name' => 'مرکز ویدیوهای گیمینگ',
                            'url' => $canonical,
                            'description' => $description,
                        ],
                        [
                            '@type' => 'ItemList',
                            'numberOfItems' => count($videos->items()),
                            'itemListElement' => collect($videos->items())->values()->map(fn (array $video, int $index) => [
                                '@type' => 'ListItem',
                                'position' => $index + 1,
                                'name' => $video['title'],
                                'url' => url($video['url']),
                            ])->all(),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'ویدیوها', 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'videos' => $videos,
        ]);
    }

    public function search(Request $request, StorefrontDataService $data, SmartSearchService $search): Response
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim($request->string('q')->toString());
        $matches = $search->rankedIds($term);
        $products = Product::query()->whereIn('id', $matches['products'])->with($this->productRelations())->get();
        $content = SocialContent::query()->whereIn('id', $matches['content'])->with('game:id,name,slug,cover')->get();
        $categories = Category::query()->whereIn('id', $matches['categories'])
            ->withCount(['products' => fn ($query) => $query->publiclyVisible()])->get();
        $games = Game::query()->whereIn('id', $matches['games'])->get();

        return Inertia::render('Search/Index', [
            'query' => $term,
            'products' => $this->sortByRank($products, $matches['products'])->map(fn ($item) => $data->product($item, $request->user())),
            'content' => $this->sortByRank($content, $matches['content'])->map(fn ($item) => $data->content($item)),
            'categories' => $this->sortByRank($categories, $matches['categories'])->map(fn ($item) => $data->category($item)),
            'channels' => $this->sortByRank($games, $matches['games'])->map(fn ($game) => [
                'id' => $game->id,
                'name' => $game->name,
                'developer' => $game->developer,
                'cover_url' => MediaStorage::url($game->cover),
                'url' => route('channels.show', $game->slug, false),
            ]),
        ]);
    }

    public function searchSuggestions(Request $request, SmartSearchService $search): JsonResponse
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $term = trim((string) ($validated['q'] ?? ''));

        return response()->json([
            'suggestions' => Cache::remember(
                'smart-search:v4:'.sha1(mb_strtolower($term)),
                now()->addSeconds(90),
                fn () => $search->suggestions($term),
            ),
        ]);
    }

    private function productQuery(Request $request): Builder
    {
        return Product::query()->publiclyVisible()->with($this->productRelations())
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn ($query) => $query->where('slug', $request->string('category'))))
            ->when($request->boolean('trade'), fn (Builder $query) => $query->where('trade_enabled', true))
            ->when($request->string('sort')->toString() === 'popular', fn (Builder $query) => $query->orderByDesc('sold_stock'))
            ->when($request->string('sort')->toString() === 'price_asc', fn (Builder $query) => $query->orderByRaw('COALESCE(discount_price, price) asc'))
            ->when($request->string('sort')->toString() === 'price_desc', fn (Builder $query) => $query->orderByRaw('COALESCE(discount_price, price) desc'))
            ->when(! $request->filled('sort') || $request->string('sort')->toString() === 'latest', fn (Builder $query) => $query->latest());
    }

    private function productRelations(): array
    {
        return [
            'category:id,name', 'type:id,title', 'game:id,name,developer,publisher',
            'platforms:id,name', 'attributeValues.attribute:id,name,slug',
            'coverMedia', 'variants:id,product_id,status',
        ];
    }

    private function sortByRank(Collection $items, array $ids): Collection
    {
        $order = array_flip($ids);

        return $items->sortBy(fn ($item) => $order[$item->id] ?? PHP_INT_MAX)->values();
    }
}
