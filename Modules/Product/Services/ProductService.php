<?php

namespace Modules\Product\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\Ticket;
use App\Models\User;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\ProductMediaService;
use App\Services\ProductPageDataService;
use App\Services\ProductPriceService;
use App\Services\ProductTypeRegistry;
use App\Services\StorefrontDataService;
use App\Services\StorefrontPageCache;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Modules\Product\DTO\CreateProductDTO;
use Modules\Product\DTO\ProductDTO;
use Modules\Product\DTO\UpdateProductDTO;

class ProductService
{
    public function __construct(
        private readonly ProductPageDataService $page,
        private readonly ProductPriceService $prices,
        private readonly ProductMediaService $media,
        private readonly ProductTypeRegistry $types,
        private readonly FeedService $feed,
        private readonly StorefrontDataService $storefront,
        private readonly StorefrontPageCache $cache,
    ) {}

    public function find(int $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    public function publicPage(Request $request, Product $product): array
    {
        abort_unless(
            Product::query()->publiclyVisible()->whereKey($product->getKey())->exists(),
            404,
        );

        $cached = $this->page->get($product);
        $pricing = $this->prices->forUser($product, $request->user());
        $isPartner = $request->user()?->role === 'partner';

        $exchangeRequestId = null;
        $exchangeOfferAmount = null;

        if ($request->user()) {
            $exchangeQuery = Ticket::query()
                ->where('type', 'exchange')
                ->where('user_id', $request->user()->id)
                ->where('exchange_status', 'accepted')
                ->whereNull('exchange_order_id')
                ->where('product_id', $product->id)
                ->where('target_product_id', $product->id)
                ->where(fn ($query) => $query
                    ->whereNull('exchange_credit_expires_at')
                    ->orWhere('exchange_credit_expires_at', '>', now()));

            if ($request->integer('exchange_request_id')) {
                $exchangeQuery->whereKey($request->integer('exchange_request_id'));
            } else {
                $exchangeQuery->latest('exchange_offer_responded_at')->latest('id');
            }

            $exchange = $exchangeQuery->first(['id', 'exchange_offer_amount']);
            $exchangeRequestId = $exchange?->id;
            $exchangeOfferAmount = $exchange?->exchange_offer_amount;
        }

        $variants = $product->variants()->where('status', 'active')->get()
            ->map(function ($variant) use ($isPartner, $product): array {
                $regular = $variant->price ?? $product->price;
                $customer = $variant->discount_price ?? $regular;
                $final = $isPartner && $variant->partner_price !== null
                    ? $variant->partner_price
                    : $customer;

                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'attributes' => $variant->attributes ?? [],
                    'stock' => $variant->stock,
                    'pricing' => [
                        'regular_price' => $regular,
                        'sale_price' => $customer,
                        'final_price' => $final,
                        'is_partner_price' => $isPartner && $variant->partner_price !== null,
                        'discount_amount' => max(0, $regular - $customer),
                    ],
                ];
            })->values();

        return [
            ...$this->page->seo(
                $cached['seo_input'],
                $product,
                $pricing,
                $request->filled('exchange_request_id'),
            ),
            'product' => [
                ...$cached['product'],
                'availability' => $product->availability,
                'trade_enabled' => (bool) $product->trade_enabled,
                'variants' => $variants,
                'pricing' => $pricing,
            ],
            'exchangeRequestId' => $exchangeRequestId,
            'exchangeOfferAmount' => $exchangeOfferAmount,
            'latestFeed' => $this->feed->latestPostsExcept($request, 0),
            'latestVideos' => SocialContent::query()
                ->published()->where('type', 'video')
                ->with('game:id,name,slug,cover')
                ->latest('published_at')->latest('id')->limit(4)->get()
                ->map(fn (SocialContent $video) => $this->storefront->content($video))
                ->values(),
            'latestProducts' => Product::query()
                ->publiclyVisible()
                ->where('id', '!=', $product->id)
                ->with([
                    'category:id,name',
                    'type:id,title',
                    'game:id,name,developer,publisher',
                    'platforms:id,name',
                    'attributeValues.attribute:id,name,slug',
                    'coverMedia',
                    'variants:id,product_id,status',
                ])
                ->latest()->limit(4)->get()
                ->map(fn (Product $related) => $this->storefront->product($related, $request->user()))
                ->values(),
        ];
    }

    public function adminIndex(Request $request): array
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->toString();
        $query = Product::query()
            ->with(['category:id,name', 'coverMedia:id,product_id,path,type,is_primary,sort_order'])
            ->when($search, fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->when($status, fn ($query) => $query->where('status', $status));

        $paginator = $query->latest('id')->paginate(20)->withQueryString();

        return [
            'resource' => 'products',
            'title' => 'محصولات',
            'description' => 'مدیریت کامل محصولات کاتالوگ فروشگاه',
            'createLabel' => 'محصول جدید',
            'createUrl' => route('admin.catalog.create', 'products'),
            'columns' => ['محصول', 'SKU', 'دسته‌بندی', 'قیمت', 'موجودی', 'وضعیت'],
            'items' => collect($paginator->items())->map(fn (Product $product) => [
                'id' => $product->getKey(),
                'cells' => [
                    $product->title,
                    $product->sku,
                    $product->category?->name ?? '—',
                    number_format($product->discount_price ?? $product->price).' تومان',
                    $product->stock,
                    $product->status,
                ],
                'coverUrl' => $product->coverMedia ? MediaStorage::url($product->coverMedia->path) : null,
                'editUrl' => route('admin.catalog.edit', ['products', $product->getKey()]),
                'mediaUrl' => route('admin.products.media.edit', $product),
                'tradeEnabled' => (bool) $product->trade_enabled,
                'exchangeToggleUrl' => route('admin.products.exchange.toggle', $product),
                'deleteUrl' => route('admin.catalog.destroy', ['products', $product->getKey()]),
            ]),
            'filters' => ['search' => $search, 'status' => $status],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    public function adminForm(?Product $product): array
    {
        if ($product) {
            $product->loadMissing(['platforms', 'attributeValues', 'variants', 'media']);
        }

        return [
            'resource' => 'products',
            'title' => ($product ? 'ویرایش ' : 'ایجاد ').'محصول',
            'item' => $product ? [
                ...$product->toArray(),
                'status' => $this->normalizeStatus($product->status),
                'platform_ids' => $product->platforms->pluck('id'),
                'attribute_values' => $product->attributeValues->pluck('value', 'category_attribute_id'),
                'media' => $product->media->map(fn ($media) => [
                    ...$media->only(['id', 'type', 'alt', 'is_primary']),
                    'url' => MediaStorage::url($media->path),
                ])->values(),
            ] : null,
            'options' => [
                'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
                'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
                'games' => Game::query()->orderBy('name')->get(['id', 'name']),
                'studios' => Studio::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
                'platforms' => Platform::query()->orderBy('sort_order')->get(['id', 'name']),
                'attributes' => CategoryAttribute::query()->orderBy('sort_order')->get([
                    'id', 'category_id', 'name', 'type', 'options', 'is_required',
                ]),
                'productTypes' => $this->types->activeOptions(),
                'availability' => $this->options('availability'),
                'conditions' => $this->options('conditions'),
                'deliveryMethods' => $this->options('delivery_methods'),
                'statuses' => $this->options('statuses'),
                'visibilities' => $this->options('visibilities'),
            ],
        ];
    }

    public function create(CreateProductDTO $dto, ?User $actor = null): ProductDTO
    {
        $data = $this->prepare($dto->toArray());

        $product = DB::transaction(function () use ($data): Product {
            $product = Product::query()->create(Arr::except($data, [
                'platform_ids', 'attribute_values', 'variants', 'media',
            ]));
            $this->sync($product, $data);

            return $product;
        });

        return ProductDTO::fromModel($product);
    }

    public function update(Product $product, UpdateProductDTO $dto, ?User $actor = null): ProductDTO
    {
        $data = $this->prepare($dto->toArray());

        DB::transaction(function () use ($product, $data, $actor): void {
            if ($product->price !== $data['price']
                || $product->discount_price !== ($data['discount_price'] ?? null)) {
                DB::table('product_price_histories')->insert([
                    'product_id' => $product->getKey(),
                    'user_id' => $actor?->getKey(),
                    'old_price' => $product->price,
                    'new_price' => $data['price'],
                    'old_discount_price' => $product->discount_price,
                    'new_discount_price' => $data['discount_price'] ?? null,
                    'created_at' => now(),
                ]);
            }

            $product->update(Arr::except($data, [
                'platform_ids', 'attribute_values', 'variants', 'media',
            ]));
            $this->sync($product, $data);
        });

        return ProductDTO::fromModel($product->refresh());
    }

    public function delete(Product $product): void
    {
        $paths = $product->media()->pluck('path')->all();

        DB::transaction(function () use ($product): void {
            $product->media()->delete();
            $product->delete();
        });

        MediaStorage::disk()->delete(array_values(array_unique(array_filter($paths))));
    }

    public function toggleExchange(Product $product): bool
    {
        $product->update(['trade_enabled' => ! $product->trade_enabled]);

        return (bool) $product->trade_enabled;
    }

    public function mediaData(Product $product): array
    {
        $product->load('media');

        return [
            'product' => $product->only(['id', 'title', 'sku']),
            'media' => $product->media->map(fn ($media) => [
                ...$media->only(['id', 'type', 'alt', 'is_primary']),
                'url' => MediaStorage::url($media->path),
            ])->values(),
        ];
    }

    public function uploadMedia(Product $product, array $files, ?int $replaceId = null): array
    {
        $this->media->upload($product, $files, $replaceId);

        return $this->mediaData($product->refresh())['media']->all();
    }

    public function syncMedia(Product $product, array $items): void
    {
        $this->media->sync($product, $items);
    }

    private function prepare(array $data): array
    {
        if (($data['product_type'] ?? null) === 'capacity_account') {
            $variants = collect($data['variants'] ?? []);
            $data['price'] = $variants->min('price');
            $data['stock'] = $variants->sum('stock');
        }

        if (isset($data['product_type'])) {
            $data['product_type_id'] = $this->types->idFor($data['product_type']);
        }

        $data['short_description'] = RichText::plainText($data['short_description'] ?? null);

        foreach (['description', 'purchase_notes', 'delivery_notes', 'return_policy'] as $field) {
            $data[$field] = RichText::sanitize($data[$field] ?? null);
        }

        return $data;
    }

    private function sync(Product $product, array $data): void
    {
        $product->platforms()->sync($data['platform_ids'] ?? []);
        $this->cache->invalidate('product');

        $product->attributeValues()->delete();
        foreach (array_filter($data['attribute_values'] ?? [], fn ($value) => filled($value)) as $attributeId => $value) {
            $product->attributeValues()->create([
                'category_attribute_id' => $attributeId,
                'value' => $value,
            ]);
        }

        $product->variants()->delete();
        if ($product->product_type === 'capacity_account') {
            foreach ($data['variants'] ?? [] as $variant) {
                $capacity = (int) $variant['capacity'];
                $product->variants()->create([
                    'name' => "ظرفیت {$capacity}",
                    'sku' => $variant['sku'],
                    'attributes' => ['capacity' => $capacity],
                    'price' => $variant['price'],
                    'discount_price' => $variant['discount_price'] ?? null,
                    'compare_price' => $variant['compare_price'] ?? null,
                    'partner_price' => $variant['partner_price'] ?? null,
                    'cost_price' => $variant['cost_price'] ?? null,
                    'stock' => $variant['stock'],
                    'status' => $variant['status'] ?? 'active',
                ]);
            }
        }

        if (array_key_exists('media', $data)) {
            $this->media->sync($product, $data['media'] ?? []);
        }
    }

    private function options(string $key): array
    {
        return collect(config("catalog.product.{$key}", []))
            ->map(fn (string $label, string $id) => compact('id', 'label'))
            ->values()->all();
    }

    private function normalizeStatus(string $status): string
    {
        return match ($status) {
            'active' => 'published',
            'inactive' => 'disabled',
            'archive' => 'archived',
            default => $status,
        };
    }
}
