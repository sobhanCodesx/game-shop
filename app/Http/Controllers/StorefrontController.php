<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SocialContent;
use App\Services\ContentViewService;
use App\Services\MediaStorage;
use App\Services\ProductMediaStorage;
use App\Services\SmartSearchService;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    public function shop(Request $request, StorefrontDataService $data): Response
    {
        $isOffers = $request->routeIs('offers.index');
        $products = $isOffers
            ? $this->productQuery($request)
                ->whereNotNull('discount_price')
                ->whereColumn('discount_price', '<', 'price')
                ->paginate(18)
                ->withQueryString()
                ->through(fn (Product $product) => $data->product($product, $request->user()))
            : $this->catalogProducts($request, $data);
        $canonical = route($isOffers ? 'offers.index' : 'shop.index');
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $description = $isOffers
            ? "تخفیف‌ها و پیشنهادهای ویژه بازی و محصولات گیمینگ در {$siteName}."
            : "خرید بازی، اکانت و تجهیزات گیمینگ از فروشگاه {$siteName}؛ مشاهده قیمت، موجودی و جدیدترین محصولات.";
        $pageName = $isOffers ? 'پیشنهادهای ویژه گیمینگ' : "فروشگاه گیمینگ {$siteName}";
        $firstProduct = collect($products->items())->first();
        $image = url(data_get($firstProduct, 'cover_url') ?: (string) config('seo.default_image', '/logo.png'));
        $itemListId = $canonical.'#products';
        $hasFilters = array_intersect(array_keys($request->query()), ['q', 'category', 'game', 'sort', 'trade', 'page']) !== [];

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
            'filters' => $request->only(['q', 'category', 'game', 'sort', 'trade']),
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
        $category->load([
            'children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order'),
        ]);
        $category->loadCount([
            'products' => fn ($query) => $query->publiclyVisible(),
            'digitalProducts' => fn ($query) => $query->published(),
        ]);

        $allCategories = Category::query()->where('status', 'active')->get(['id', 'parent_id']);
        $ids = [$category->id];
        for ($cursor = 0; $cursor < count($ids); $cursor++) {
            array_push($ids, ...$allCategories->where('parent_id', $ids[$cursor])->pluck('id')->all());
        }

        $filterDefinitions = $this->categoryFilterDefinitions($ids);
        $selectedAttributeFilters = $this->selectedCategoryFilters($request, $filterDefinitions);
        $products = $this->catalogProducts(
            $request,
            $data,
            $ids,
            $filterDefinitions,
            $selectedAttributeFilters,
        );

        $categoryData = $data->category($category);
        $canonical = route('categories.show', $category->slug);
        $description = Str::limit(
            RichText::plainText($category->description)
                ?: "محصولات و بازی‌های دسته {$category->name} را در پلی نکسوس ببینید؛ قیمت، موجودی و تازه‌ترین گزینه‌های مرتبط.",
            160,
            '…',
        );
        $imagePath = (string) ($categoryData['image_url'] ?? '');
        $image = $imagePath !== ''
            ? (Str::startsWith($imagePath, ['http://', 'https://']) ? $imagePath : url($imagePath))
            : url((string) config('seo.default_image', '/logo.png'));
        $hasFilters = $request->hasAny(['q', 'sort', 'trade', 'filters', 'page']);
        $itemListId = $canonical.'#products';

        return Inertia::render('Categories/Show', [
            ...Seo::page([
                'title' => "{$category->name}؛ محصولات و بازی‌ها",
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $hasFilters
                    ? 'noindex, follow'
                    : 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'website',
                'image' => $image,
                'imageAlt' => "دسته {$category->name}",
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            '@id' => $canonical.'#category',
                            'url' => $canonical,
                            'name' => $category->name,
                            'description' => $description,
                            'image' => $image,
                            'mainEntity' => ['@id' => $itemListId],
                        ],
                        [
                            '@type' => 'ItemList',
                            '@id' => $itemListId,
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
                                ['@type' => 'ListItem', 'position' => 2, 'name' => $category->name, 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'category' => $categoryData,
            'products' => $products,
            'filters' => $request->only(['q', 'sort', 'trade']),
            'catalogFilters' => $filterDefinitions->map(fn (array $filter) => [
                'title' => $filter['title'],
                'slug' => $filter['slug'],
                'options' => $filter['options'],
            ])->values(),
            'selectedAttributeFilters' => collect($selectedAttributeFilters)
                ->mapWithKeys(fn (array $values, string $slug) => [$slug => array_values($values)])
                ->all(),
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
                        'media_url' => ProductMediaStorage::url($item->path),
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
        $pageNumber = max(1, $request->integer('page', 1));
        $canonical = $pageNumber > 1
            ? route('videos.index', ['page' => $pageNumber])
            : route('videos.index');
        $description = $pageNumber > 1
            ? "صفحه {$pageNumber} ویدیوهای گیمینگ PlayNexus؛ تریلرها، گیم‌پلی‌ها، بررسی‌ها و ویدیوهای تازه دنیای بازی."
            : 'تماشای تازه‌ترین تریلرها، گیم‌پلی‌ها، بررسی‌ها و ویدیوهای دنیای بازی در PlayNexus.';
        $firstVideo = collect($videos->items())->first();

        return Inertia::render('Videos/Index', [
            ...Seo::page([
                'title' => $pageNumber > 1
                    ? "ویدیوهای گیمینگ؛ صفحه {$pageNumber}"
                    : 'ویدیوهای گیمینگ؛ تریلر، گیم‌پلی و بررسی',
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $videos->isNotEmpty()
                    ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'
                    : 'noindex, follow',
                'type' => 'website',
                'image' => url(data_get($firstVideo, 'thumbnail_url') ?: (string) config('seo.default_image', '/logo.png')),
                'imageAlt' => data_get($firstVideo, 'title') ?: 'ویدیوهای گیمینگ PlayNexus',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'CollectionPage',
                            'name' => $pageNumber > 1 ? "مرکز ویدیوهای گیمینگ - صفحه {$pageNumber}" : 'مرکز ویدیوهای گیمینگ',
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

    private function catalogProducts(
        Request $request,
        StorefrontDataService $data,
        ?array $categoryIds = null,
        ?Collection $filterDefinitions = null,
        array $selectedAttributeFilters = [],
    ): LengthAwarePaginator {
        $physical = Product::query()
            ->publiclyVisible()
            ->search($request->string('q')->toString() ?: null)
            ->when(
                $request->filled('category'),
                fn (Builder $query) => $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category')),
                ),
            )
            ->when(
                $request->filled('game'),
                fn (Builder $query) => $query->whereHas(
                    'game',
                    fn ($gameQuery) => $gameQuery->where('slug', $request->string('game')),
                ),
            )
            ->when($request->boolean('trade'), fn (Builder $query) => $query->where('trade_enabled', true));

        $digital = DigitalProduct::query()
            ->published()
            ->when(
                $request->filled('q'),
                fn (Builder $query) => $query->where(
                    fn (Builder $inner) => $inner
                        ->where('title', 'like', '%'.$request->string('q')->toString().'%')
                        ->orWhereHas(
                            'game',
                            fn ($gameQuery) => $gameQuery->where('name', 'like', '%'.$request->string('q')->toString().'%'),
                        ),
                ),
            )
            ->when(
                $request->filled('category'),
                fn (Builder $query) => $query->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category')),
                ),
            )
            ->when(
                $request->filled('game'),
                fn (Builder $query) => $query->whereHas(
                    'game',
                    fn ($gameQuery) => $gameQuery->where('slug', $request->string('game')),
                ),
            );

        if ($request->boolean('trade')) {
            $digital->whereRaw('1 = 0');
        }

        if ($categoryIds !== null) {
            $physical->whereIn('category_id', $categoryIds);
            $digital->whereIn('category_id', $categoryIds);
        }

        if ($filterDefinitions && $selectedAttributeFilters !== []) {
            $bySlug = $filterDefinitions->keyBy('slug');

            foreach ($selectedAttributeFilters as $slug => $values) {
                $definition = $bySlug->get($slug);
                if (! $definition) {
                    continue;
                }

                $physicalIds = $definition['physical_ids'];
                if ($physicalIds === []) {
                    $physical->whereRaw('1 = 0');
                } else {
                    $physical->whereHas(
                        'attributeValues',
                        fn ($valueQuery) => $valueQuery
                            ->whereIn('category_attribute_id', $physicalIds)
                            ->whereIn('value', $values),
                    );
                }

                $digitalIds = $definition['digital_ids'];
                if ($digitalIds === []) {
                    $digital->whereRaw('1 = 0');
                } else {
                    $digital->whereHas(
                        'attributeValues',
                        fn ($valueQuery) => $valueQuery
                            ->whereIn('attribute_id', $digitalIds)
                            ->whereIn('value', $values),
                    );
                }
            }
        }

        $physicalRows = (clone $physical)
            ->reorder()
            ->selectRaw(
                "'physical' as kind, products.id as item_id, products.created_at as sort_at, "
                ."products.featured as featured_sort, COALESCE(products.discount_price, products.price) as price_sort, "
                ."products.sold_stock as popular_sort",
            )
            ->toBase();

        $digitalRows = (clone $digital)
            ->reorder()
            ->selectRaw(
                "'digital' as kind, digital_products.id as item_id, digital_products.created_at as sort_at, "
                ."digital_products.featured as featured_sort, "
                ."(select min(digital_offers.price) from digital_offers "
                ."where digital_offers.digital_product_id = digital_products.id "
                ."and digital_offers.status = 'active' and digital_offers.price > 0) as price_sort, "
                ."0 as popular_sort",
            )
            ->toBase();

        $catalog = DB::query()->fromSub(
            $physicalRows->unionAll($digitalRows),
            'catalog_items',
        );

        match ($request->string('sort')->toString()) {
            'popular' => $catalog->orderByDesc('popular_sort')->orderByDesc('sort_at'),
            'price_asc' => $catalog
                ->orderByRaw('(price_sort is null or price_sort = 0) asc')
                ->orderBy('price_sort')
                ->orderByDesc('sort_at'),
            'price_desc' => $catalog
                ->orderByRaw('(price_sort is null or price_sort = 0) asc')
                ->orderByDesc('price_sort')
                ->orderByDesc('sort_at'),
            default => $catalog->orderByDesc('sort_at')->orderByDesc('item_id'),
        };

        $page = $catalog->paginate(18)->withQueryString();
        $rows = $page->getCollection();

        $physicalItems = Product::query()
            ->with($this->productRelations())
            ->whereIn('id', $rows->where('kind', 'physical')->pluck('item_id'))
            ->get()
            ->keyBy('id');

        $digitalItems = DigitalProduct::query()
            ->with($this->digitalProductRelations())
            ->whereIn('id', $rows->where('kind', 'digital')->pluck('item_id'))
            ->get()
            ->keyBy('id');

        $page->setCollection(
            $rows->map(function ($row) use ($physicalItems, $digitalItems, $data, $request) {
                if ($row->kind === 'digital') {
                    $product = $digitalItems->get((int) $row->item_id);

                    return $product ? $data->digitalProduct($product) : null;
                }

                $product = $physicalItems->get((int) $row->item_id);

                return $product ? $data->product($product, $request->user()) : null;
            })->filter()->values(),
        );

        return $page;
    }

    private function categoryFilterDefinitions(array $categoryIds): Collection
    {
        $physical = CategoryAttribute::query()
            ->whereIn('category_id', $categoryIds)
            ->where('is_filterable', true)
            ->with([
                'values' => fn ($query) => $query->whereHas(
                    'product',
                    fn ($productQuery) => $productQuery
                        ->publiclyVisible()
                        ->whereIn('category_id', $categoryIds),
                ),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $digital = Attribute::query()
            ->with([
                'options' => fn ($query) => $query
                    ->where('status', 'active')
                    ->orderBy('sort_order'),
                'digitalValues' => fn ($query) => $query->whereHas(
                    'product',
                    fn ($productQuery) => $productQuery
                        ->published()
                        ->whereIn('category_id', $categoryIds),
                ),
            ])
            ->where('status', 'active')
            ->where('is_filterable', true)
            ->whereIn('input_type', ['select', 'multi_select', 'boolean'])
            ->whereNotIn('slug', ['capacity', 'platform'])
            ->whereHas(
                'digitalValues.product',
                fn ($productQuery) => $productQuery
                    ->published()
                    ->whereIn('category_id', $categoryIds),
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $filters = collect();

        foreach ($physical->groupBy('slug') as $slug => $definitions) {
            $values = $definitions
                ->flatMap(fn (CategoryAttribute $definition) => $definition->values->pluck('value'))
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => (string) $value)
                ->unique()
                ->values();

            if ($values->isEmpty()) {
                continue;
            }

            $first = $definitions->first();
            $filters->put($slug, [
                'title' => $first->name,
                'slug' => $slug,
                'options' => $values->map(fn (string $value) => [
                    'title' => $this->categoryAttributeValueLabel($definitions, $value),
                    'value' => $value,
                ])->all(),
                'physical_ids' => $definitions->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'digital_ids' => [],
            ]);
        }

        foreach ($digital as $attribute) {
            $values = $attribute->digitalValues
                ->pluck('value')
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => (string) $value)
                ->unique()
                ->values();

            if ($values->isEmpty()) {
                continue;
            }

            $options = $values->map(function (string $value) use ($attribute): array {
                $title = $attribute->input_type === 'boolean'
                    ? ($value === '1' ? 'بله' : 'خیر')
                    : (string) ($attribute->options->firstWhere('value', $value)?->title ?? $value);

                return ['title' => $title, 'value' => $value];
            })->all();

            if ($filters->has($attribute->slug)) {
                $current = $filters->get($attribute->slug);
                $current['digital_ids'] = array_values(array_unique([
                    ...$current['digital_ids'],
                    (int) $attribute->id,
                ]));
                $current['options'] = collect([...$current['options'], ...$options])
                    ->unique('value')
                    ->values()
                    ->all();
                $filters->put($attribute->slug, $current);
                continue;
            }

            $filters->put($attribute->slug, [
                'title' => $attribute->title,
                'slug' => $attribute->slug,
                'options' => $options,
                'physical_ids' => [],
                'digital_ids' => [(int) $attribute->id],
            ]);
        }

        return $filters->values();
    }

    private function selectedCategoryFilters(Request $request, Collection $definitions): array
    {
        $requested = (array) $request->input('filters', []);
        $selected = [];

        foreach ($definitions as $definition) {
            $allowed = collect($definition['options'])
                ->pluck('value')
                ->map(fn ($value) => (string) $value)
                ->all();
            $values = array_values(array_unique(array_map(
                'strval',
                (array) ($requested[$definition['slug']] ?? []),
            )));
            $values = array_values(array_intersect($values, $allowed));

            if ($values !== []) {
                $selected[$definition['slug']] = $values;
            }
        }

        return $selected;
    }

    private function categoryAttributeValueLabel(Collection $definitions, string $value): string
    {
        foreach ($definitions as $definition) {
            foreach ((array) $definition->options as $option) {
                if (is_array($option)) {
                    $optionValue = (string) ($option['value'] ?? $option['slug'] ?? $option['title'] ?? '');
                    if ($optionValue === $value) {
                        return (string) ($option['title'] ?? $option['label'] ?? $value);
                    }
                    continue;
                }

                if ((string) $option === $value) {
                    return $value;
                }
            }
        }

        return $value;
    }

    private function digitalProductRelations(): array
    {
        return [
            'category:id,name,slug',
            'game:id,name,slug,cover',
            'platform:id,name,slug',
            'offers',
            'coverMedia',
            'attributeValues.attribute.options',
        ];
    }

    private function productQuery(Request $request): Builder
    {
        return Product::query()->publiclyVisible()->with($this->productRelations())
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('category'), fn (Builder $query) => $query->whereHas('category', fn ($query) => $query->where('slug', $request->string('category'))))
            ->when($request->filled('game'), fn (Builder $query) => $query->whereHas('game', fn ($query) => $query->where('slug', $request->string('game'))))
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
