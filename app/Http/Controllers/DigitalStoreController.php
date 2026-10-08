<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\DigitalOffer;
use App\Models\DigitalProduct;
use App\Services\GameStoryLinkGraphService;
use App\Models\Game;
use App\Models\Platform;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\DigitalOrderService;
use App\Services\DigitalProductMediaStorage;
use App\Services\TicketService;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DigitalStoreController extends Controller
{
    public function __construct(
        private readonly StorefrontDataService $storefrontData,
    ) {}

    public function index(Request $request): Response
    {
        $filterAttributes = $this->filterAttributes();
        $selectedFilters = $this->selectedFilters($request, $filterAttributes);
        $selectedCategory = trim($request->string('category')->toString());
        $selectedGame = trim($request->string('game')->toString());
        $selectedPlatform = trim($request->string('platform')->toString());

        $query = DigitalProduct::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'game:id,name,slug,cover,background,status',
                'platform:id,name,slug,is_dual_platform',
                'platform.variants:id,platform_id,name,key,sort_order',
                'offers.variantPrices',
                'coverMedia',
            ])
            ->when(
                $selectedGame !== '',
                fn ($productQuery) => $productQuery->whereHas(
                    'game',
                    fn ($gameQuery) => $gameQuery
                        ->whereIn('status', ['active', 'published'])
                        ->where('slug', $selectedGame),
                ),
            )
            ->when(
                $selectedPlatform !== '',
                fn ($productQuery) => $productQuery->whereHas(
                    'platform',
                    fn ($platformQuery) => $platformQuery->where('slug', $selectedPlatform),
                ),
            )
            ->when(
                $selectedCategory !== '',
                fn ($productQuery) => $productQuery->whereHas(
                    'category',
                    fn ($categoryQuery) => $categoryQuery
                        ->where('status', 'active')
                        ->where('slug', $selectedCategory),
                ),
            );

        foreach ($selectedFilters as $attributeId => $values) {
            $query->whereHas(
                'attributeValues',
                fn ($valueQuery) => $valueQuery
                    ->where('attribute_id', $attributeId)
                    ->whereIn('value', $values),
            );
        }

        $products = $query
            ->orderByDesc('featured')
            ->latest('id')
            ->paginate(18)
            ->withQueryString()
            ->through(fn (DigitalProduct $product) => $this->productPayload($product, false));

        return Inertia::render('Digital/Index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('status', 'active')
                ->whereHas('digitalProducts', fn ($productQuery) => $productQuery->published())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'selectedCategory' => $selectedCategory !== '' ? $selectedCategory : null,
            'selectedGame' => $selectedGame !== ''
                ? Game::query()
                    ->where('slug', $selectedGame)
                    ->whereIn('status', ['active', 'published'])
                    ->first(['id', 'name', 'slug'])
                    ?->only(['id', 'name', 'slug'])
                : null,
            'selectedPlatform' => $selectedPlatform !== ''
                ? Platform::query()
                    ->where('slug', $selectedPlatform)
                    ->first(['id', 'name', 'slug'])
                    ?->only(['id', 'name', 'slug'])
                : null,
            'filters' => $filterAttributes->map(fn ($attribute) => [
                'id' => $attribute->id,
                'title' => $attribute->title,
                'slug' => $attribute->slug,
                'input_type' => $attribute->input_type,
                'options' => $this->attributeOptions($attribute),
            ])->values(),
            'selectedFilters' => $filterAttributes
                ->mapWithKeys(fn ($attribute) => [
                    $attribute->slug => $selectedFilters[$attribute->id] ?? [],
                ])
                ->filter(fn ($values) => $values !== [])
                ->all(),
        ]);
    }

    public function show(DigitalProduct $digitalProduct, GameStoryLinkGraphService $storyLinks): Response
    {
        abort_unless($digitalProduct->status === 'published', 404);

        $digitalProduct->load([
            'category:id,name,slug',
            'game:id,studio_id,name,slug,cover,background,status',
            'game.studio:id,name,slug,logo,background,status',
            'platform:id,name,slug,is_dual_platform',
                'platform.variants:id,platform_id,name,key,sort_order',
            'offers.variantPrices',
            'media',
            'seller:id,name,avatar',
            'attributeValues.attribute.options',
        ]);

        $product = $this->productPayload($digitalProduct, true);
        $canonical = route('digital.show', $digitalProduct);
        $description = Str::limit(
            $digitalProduct->short_description
                ?: "خرید {$digitalProduct->title} با مشاهده ظرفیت‌ها، موجودی و پشتیبانی فروشنده در PlayNexus.",
            160,
            '…',
        );
        $image = (string) ($product['cover_url'] ?: config('seo.default_image', '/logo.png'));
        $image = str_starts_with($image, 'http') ? $image : url($image);
        $gameUrl = data_get($product, 'game.channel_url')
            ? url((string) data_get($product, 'game.channel_url'))
            : null;

        $structuredOffers = collect($product['offers'])
            ->filter(fn ($offer) => (int) data_get($offer, 'price', 0) > 0)
            ->map(fn ($offer) => [
                '@type' => 'Offer',
                'name' => (string) data_get($offer, 'label', $digitalProduct->title),
                'url' => $canonical,
                'priceCurrency' => 'IRR',
                'price' => (int) data_get($offer, 'price') * 10,
                'availability' => (bool) data_get($offer, 'available', false)
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ])
            ->values()
            ->all();

        $productStructuredData = [
            '@type' => 'Product',
            '@id' => $canonical.'#product',
            'name' => $digitalProduct->title,
            'url' => $canonical,
            'description' => $description,
            'image' => $image,
            'offers' => $structuredOffers,
            ...($digitalProduct->category?->name
                ? ['category' => $digitalProduct->category->name]
                : []),
            ...($gameUrl
                ? ['isRelatedTo' => [
                    '@type' => 'VideoGame',
                    'name' => $digitalProduct->game?->name,
                    'url' => $gameUrl,
                ]]
                : []),
        ];

        $breadcrumbStructuredData = [
            '@type' => 'BreadcrumbList',
            '@id' => $canonical.'#breadcrumb',
            'itemListElement' => array_values(array_filter([
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'خانه',
                    'item' => route('home'),
                ],
                $gameUrl ? [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $digitalProduct->game?->name,
                    'item' => $gameUrl,
                ] : null,
                [
                    '@type' => 'ListItem',
                    'position' => $gameUrl ? 3 : 2,
                    'name' => $digitalProduct->title,
                    'item' => $canonical,
                ],
            ])),
        ];

        $structuredGraph = [
            ...($structuredOffers !== [] ? [$productStructuredData] : []),
            $breadcrumbStructuredData,
        ];

        $sameGameProducts = $this->sameGameProducts($digitalProduct);
        $relatedProducts = $this->relatedProducts($digitalProduct);
        $gameVideos = $this->gameContent($digitalProduct, 'video', 4);
        $gameFeed = $this->gameContent($digitalProduct, 'post', 4);
        $gamePlaylists = $this->gamePlaylists($digitalProduct);

        return Inertia::render('Digital/Show', [
            'gameStories' => $storyLinks->storiesForGame($digitalProduct->game_id),
            ...Seo::page([
                'title' => $digitalProduct->title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'product',
                'image' => $image,
                'imageAlt' => $digitalProduct->title,
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => $structuredGraph,
                ],
            ]),
            'product' => $product,
            'sameGameProducts' => $sameGameProducts,
            'relatedProducts' => $relatedProducts,
            'gameVideos' => $gameVideos,
            'gameFeed' => $gameFeed,
            'gamePlaylists' => $gamePlaylists,
        ]);
    }

    public function priceInquiry(
        Request $request,
        DigitalProduct $digitalProduct,
        TicketService $tickets,
    ): RedirectResponse {
        abort_unless($digitalProduct->status === 'published', 404);

        $existing = $request->user()
            ->tickets()
            ->where('type', 'digital_price')
            ->where('digital_product_id', $digitalProduct->id)
            ->whereIn('status', ['pending', 'open'])
            ->latest('last_replied_at')
            ->first();

        if ($existing) {
            return to_route('account.tickets.show', $existing)
                ->with('info', 'استعلام قیمت باز برای این محصول از قبل وجود دارد؛ همان گفت‌وگو را ادامه دهید.');
        }

        $ticket = $tickets->createDigitalPriceInquiry(
            $request->user(),
            $digitalProduct,
        );

        return to_route('account.tickets.show', $ticket)
            ->with('success', 'درخواست آخرین قیمت برای فروشنده ارسال شد. پاسخ را از همین تیکت دنبال کنید.');
    }

    public function order(
        Request $request,
        DigitalProduct $digitalProduct,
        DigitalOrderService $orders,
    ): RedirectResponse {
        abort_unless($digitalProduct->status === 'published', 404);

        $data = $request->validate([
            'offer_id' => [
                'required',
                'integer',
                Rule::exists('digital_offers', 'id')
                    ->where('digital_product_id', $digitalProduct->id),
            ],
            'platform_variant_id' => ['nullable', 'integer', Rule::exists('platform_variants', 'id')],
        ]);

        $offer = DigitalOffer::query()->findOrFail($data['offer_id']);
        $order = $orders->create(
            $request->user(),
            $offer,
            isset($data['platform_variant_id']) ? (int) $data['platform_variant_id'] : null,
        );

        return to_route('account.digital-orders.show', $order)
            ->with(
                'success',
                'سفارش دیجیتال ثبت شد؛ ادامه خرید از همین گفت‌وگو انجام می‌شود.',
            );
    }

    private function sameGameProducts(DigitalProduct $product): array
    {
        if (! $product->game_id) {
            return [];
        }

        return DigitalProduct::query()
            ->published()
            ->where('game_id', $product->game_id)
            ->where('id', '!=', $product->id)
            ->with([
                'category:id,name,slug',
                'game:id,name,slug,cover,background,status',
                'platform:id,name,slug,is_dual_platform',
                'platform.variants:id,platform_id,name,key,sort_order',
                'offers.variantPrices',
                'coverMedia',
                'attributeValues.attribute.options',
            ])
            ->orderByDesc('featured')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (DigitalProduct $candidate) => $this->storefrontData->digitalProduct($candidate))
            ->values()
            ->all();
    }

    private function relatedProducts(DigitalProduct $product): array
    {
        $attributePairs = $product->attributeValues
            ->map(fn ($value) => [
                'attribute_id' => (int) $value->attribute_id,
                'value' => (string) $value->value,
            ])
            ->unique(fn (array $item) => $item['attribute_id'].'|'.$item['value'])
            ->values();

        $query = DigitalProduct::query()
            ->published()
            ->where('id', '!=', $product->id)
            ->when(
                $product->game_id,
                fn ($candidateQuery) => $candidateQuery->where('game_id', '!=', $product->game_id),
            )
            ->with([
                'category:id,name,slug',
                'game:id,name,slug,cover,background,status',
                'platform:id,name,slug,is_dual_platform',
                'platform.variants:id,platform_id,name,key,sort_order',
                'offers.variantPrices',
                'coverMedia',
                'attributeValues.attribute.options',
            ]);

        $hasSimilaritySignal = filled($product->category_id)
            || filled($product->platform_id)
            || $attributePairs->isNotEmpty();

        if ($hasSimilaritySignal) {
            $query->where(function ($related) use ($product, $attributePairs): void {
                if ($product->category_id) {
                    $related->orWhere('category_id', $product->category_id);
                }
                if ($product->platform_id) {
                    $related->orWhere('platform_id', $product->platform_id);
                }

                foreach ($attributePairs as $pair) {
                    $related->orWhereHas(
                        'attributeValues',
                        fn ($values) => $values
                            ->where('attribute_id', $pair['attribute_id'])
                            ->where('value', $pair['value']),
                    );
                }
            });
        }

        return $query
            ->orderByDesc('featured')
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(function (DigitalProduct $candidate) use ($product, $attributePairs): array {
                $score = 0;

                if ($candidate->category_id === $product->category_id && $product->category_id) {
                    $score += 45;
                }
                if ($candidate->platform_id === $product->platform_id && $product->platform_id) {
                    $score += 30;
                }

                $candidatePairs = $candidate->attributeValues
                    ->map(fn ($value) => (int) $value->attribute_id.'|'.(string) $value->value)
                    ->flip();

                foreach ($attributePairs as $pair) {
                    if ($candidatePairs->has($pair['attribute_id'].'|'.$pair['value'])) {
                        $score += 15;
                    }
                }

                if ($candidate->featured) {
                    $score += 5;
                }

                return [
                    'score' => $score,
                    'product' => $this->storefrontData->digitalProduct($candidate),
                ];
            })
            ->sortByDesc('score')
            ->take(10)
            ->pluck('product')
            ->values()
            ->all();
    }

    private function gamePlaylists(DigitalProduct $product): array
    {
        if (! $product->game_id) {
            return [];
        }

        return VideoPlaylist::query()
            ->publiclyVisible()
            ->where('game_id', $product->game_id)
            ->withCount([
                'videos' => fn ($query) => $query
                    ->published()
                    ->where('type', 'video'),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(4)
            ->get(['id', 'game_id', 'title', 'slug', 'logo'])
            ->map(fn (VideoPlaylist $playlist) => [
                'id' => $playlist->id,
                'title' => $playlist->title,
                'slug' => $playlist->slug,
                'url' => route('channels.playlists.show', [
                    'game' => $product->game?->slug,
                    'playlist' => $playlist->slug,
                ], false),
                'cover_url' => MediaStorage::url($playlist->logo),
                'videos_count' => (int) $playlist->videos_count,
            ])
            ->values()
            ->all();
    }

    private function gameContent(
        DigitalProduct $product,
        string $type,
        int $limit,
    ): array {
        if (! $product->game_id) {
            return [];
        }

        return SocialContent::query()
            ->published()
            ->where('game_id', $product->game_id)
            ->where('type', $type)
            ->with([
                'media',
                'game:id,name,slug,cover',
            ])
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (SocialContent $content) => $this->storefrontData->content($content))
            ->values()
            ->all();
    }

    private function productPayload(
        DigitalProduct $product,
        bool $detailed,
    ): array {
        $cover = $product->relationLoaded('coverMedia')
            ? $product->coverMedia
            : $product->media->firstWhere('is_primary', true)
                ?? $product->media->firstWhere('type', 'image');

        return [
            ...$product->only([
                'id',
                'title',
                'slug',
                'short_description',
                'support_days',
                'featured',
            ]),
            'category' => $product->category ? [
                ...$product->category->only(['id', 'name', 'slug']),
                'url' => route('categories.show', $product->category->slug, false),
                'digital_url' => route('digital.index', ['category' => $product->category->slug], false),
            ] : null,
            'game' => $product->game ? [
                ...$product->game->only(['id', 'name', 'slug']),
                'cover_url' => MediaStorage::url($product->game->cover),
                'background_url' => MediaStorage::url($product->game->background),
                'channel_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('channels.show', $product->game->slug, false)
                    : null,
                'digital_products_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('digital.index', ['game' => $product->game->slug], false)
                    : null,
                'feed_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('channels.show', $product->game->slug, false).'#feed'
                    : null,
                'videos_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('channels.show', $product->game->slug, false).'#videos'
                    : null,
                'playlists_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('channels.show', $product->game->slug, false).'#playlists'
                    : null,
                'products_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('channels.show', $product->game->slug, false).'#products'
                    : null,
                'studio' => $product->game->studio?->status === 'active' ? [
                    ...$product->game->studio->only(['id', 'name', 'slug']),
                    'url' => route('studios.show', $product->game->studio->slug, false),
                    'logo_url' => MediaStorage::url($product->game->studio->logo),
                    'background_url' => MediaStorage::url($product->game->studio->background),
                ] : null,
            ] : null,
            'platform_name' => $product->platform?->name,
            'platform' => $product->platform ? [
                ...$product->platform->only(['id', 'name', 'slug']),
                'is_dual_platform' => (bool) $product->platform->is_dual_platform,
                'variants' => $product->platform->is_dual_platform && $product->platform->relationLoaded('variants')
                    ? $product->platform->variants->take(2)->map(fn ($variant) => $variant->only(['id', 'name', 'key']))->values()
                    : [],
                'digital_products_url' => route('digital.index', ['platform' => $product->platform->slug], false),
            ] : null,
            'seller' => $detailed && $product->seller ? [
                ...$product->seller->only(['id', 'name']),
                'avatar_url' => MediaStorage::url($product->seller->avatar),
            ] : null,
            'cover_url' => DigitalProductMediaStorage::url($cover?->path)
                ?: MediaStorage::url($product->game?->cover),
            'media' => $detailed
                ? $product->media->map(fn ($media) => [
                    ...$media->only(['id', 'type', 'alt', 'is_primary']),
                    'url' => DigitalProductMediaStorage::url($media->path),
                ])->values()
                : [],
            'features' => $detailed
                ? $this->featurePayload($product)
                : [],
            'offers' => $product->offers
                ->where('status', 'active')
                ->map(fn ($offer) => [
                    ...$offer->only(['id', 'code', 'label', 'price']),
                    'updated_at' => $offer->updated_at?->toISOString(),
                    'available_stock' => $offer->availableStock(),
                    'available' => $offer->availableStock() > 0,
                    'variant_prices' => $offer->relationLoaded('variantPrices')
                        ? $offer->variantPrices->map(fn ($row) => [
                            'platform_variant_id' => (int) $row->platform_variant_id,
                            'price' => (int) $row->price,
                        ])->values()
                        : [],
                ])
                ->values(),
        ];
    }

    private function featurePayload(DigitalProduct $product): Collection
    {
        return $product->attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values) {
                $attribute = $values->first()?->attribute;
                if (! $attribute || ! $attribute->is_visible_on_product) {
                    return null;
                }

                $labels = $values
                    ->map(function ($item) use ($attribute): string {
                        if ($attribute->input_type === 'boolean') {
                            return $item->value === '1' ? 'بله' : 'خیر';
                        }

                        return (string) (
                            $attribute->options
                                ->firstWhere('value', $item->value)
                                ?->title
                            ?? $item->value
                        );
                    })
                    ->filter()
                    ->values();

                $rawValues = $values
                    ->pluck('value')
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->map(fn ($value) => (string) $value)
                    ->unique()
                    ->values()
                    ->all();

                $canFilter = (bool) $attribute->is_filterable
                    && in_array($attribute->input_type, ['select', 'multi_select', 'boolean'], true)
                    && ! in_array($attribute->slug, ['capacity', 'platform'], true)
                    && $rawValues !== [];

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->title,
                    'slug' => $attribute->slug,
                    'value' => $labels->join('، '),
                    'filter_url' => $canFilter
                        ? route('digital.index', [
                            'filters' => [$attribute->slug => $rawValues],
                        ], false)
                        : null,
                ];
            })
            ->filter()
            ->values();
    }

    private function filterAttributes(): Collection
    {
        return Attribute::query()
            ->with([
                'options' => fn ($query) => $query
                    ->where('status', 'active')
                    ->orderBy('sort_order'),
            ])
            ->where('status', 'active')
            ->where('is_filterable', true)
            ->whereIn('input_type', ['select', 'multi_select', 'boolean'])
            ->whereNotIn('slug', ['capacity', 'platform'])
            ->whereHas('digitalValues')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function selectedFilters(
        Request $request,
        Collection $attributes,
    ): array {
        $requested = (array) $request->input('filters', []);
        $selected = [];

        foreach ($attributes as $attribute) {
            $values = array_values(array_unique(array_map(
                'strval',
                (array) ($requested[$attribute->slug] ?? []),
            )));

            $allowed = collect($this->attributeOptions($attribute))
                ->pluck('value')
                ->map(fn ($value) => (string) $value)
                ->all();

            $values = array_values(array_intersect($values, $allowed));
            if ($values !== []) {
                $selected[$attribute->id] = $values;
            }
        }

        return $selected;
    }

    private function attributeOptions($attribute): array
    {
        if ($attribute->input_type === 'boolean') {
            return [
                ['title' => 'بله', 'value' => '1'],
                ['title' => 'خیر', 'value' => '0'],
            ];
        }

        return $attribute->options
            ->map(fn ($option) => [
                'title' => $option->title,
                'value' => $option->value,
            ])
            ->values()
            ->all();
    }
}
