<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Ticket;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\ProductPriceService;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, ProductPriceService $prices, FeedService $feed, StorefrontDataService $storefront): Response
    {
        abort_unless(Product::query()->publiclyVisible()->whereKey($product->getKey())->exists(), 404);

        $product->loadMissing(['category:id,name,slug', 'brand:id,name', 'platforms:id,name', 'attributeValues.attribute:id,name', 'media', 'variants']);

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
                ->where(fn ($query) => $query->whereNull('exchange_credit_expires_at')->orWhere('exchange_credit_expires_at', '>', now()));

            if ($request->integer('exchange_request_id')) {
                $exchangeQuery->whereKey($request->integer('exchange_request_id'));
            } else {
                $exchangeQuery->latest('exchange_offer_responded_at')->latest('id');
            }

            $exchange = $exchangeQuery->first(['id', 'exchange_offer_amount']);
            $exchangeRequestId = $exchange?->id;
            $exchangeOfferAmount = $exchange?->exchange_offer_amount;
        }

        $pricing = $prices->forUser($product, $request->user());
        $canonical = route('products.show', $product->slug);
        $description = Str::limit(
            RichText::plainText($product->seo_description ?: $product->short_description ?: $product->description) ?: $product->title,
            160,
            '…',
        );
        $images = $product->media->where('type', 'image')->sortByDesc('is_primary')->pluck('path')
            ->map(fn (string $path) => url(MediaStorage::url($path)))->values();
        $image = $images->first() ?: url((string) config('seo.default_image', '/logo.png'));
        $availability = match ($product->availability) {
            'out_of_stock' => 'https://schema.org/OutOfStock',
            'preorder', 'coming_soon' => 'https://schema.org/PreOrder',
            'discontinued' => 'https://schema.org/Discontinued',
            default => 'https://schema.org/InStock',
        };

        return Inertia::render('Products/Show', [
            ...Seo::page([
                'title' => $product->seo_title ?: $product->title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $request->filled('exchange_request_id') ? 'noindex, follow' : 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'product',
                'image' => $image,
                'imageAlt' => $product->media->sortByDesc('is_primary')->first()?->alt ?: $product->title,
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'Product',
                            '@id' => $canonical.'#product',
                            'name' => $product->title,
                            'url' => $canonical,
                            'description' => $description,
                            'sku' => $product->sku,
                            'image' => $images->isNotEmpty() ? $images->all() : [$image],
                            ...($product->brand ? ['brand' => ['@type' => 'Brand', 'name' => $product->brand->name]] : []),
                            'offers' => [
                                '@type' => 'Offer',
                                'url' => $canonical,
                                'priceCurrency' => 'IRR',
                                'price' => (int) $pricing['final_price'] * 10,
                                'availability' => $availability,
                                'itemCondition' => 'https://schema.org/NewCondition',
                            ],
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => array_values(array_filter([
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'فروشگاه', 'item' => route('shop.index')],
                                $product->category ? ['@type' => 'ListItem', 'position' => 3, 'name' => $product->category->name, 'item' => route('categories.show', $product->category->slug)] : null,
                                ['@type' => 'ListItem', 'position' => $product->category ? 4 : 3, 'name' => $product->title, 'item' => $canonical],
                            ])),
                        ],
                    ],
                ],
            ]),
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'short_description' => RichText::plainText($product->short_description),
                'description' => RichText::sanitize($product->description),
                'availability' => $product->availability,
                'trade_enabled' => (bool) $product->trade_enabled,
                'release_date' => $product->release_date?->format('Y-m-d'),
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'platforms' => $product->platforms->pluck('name'),
                'attributes' => $product->attributeValues->map(fn ($value) => [
                    'name' => $value->attribute->name,
                    'value' => $value->value,
                ]),
                'media' => $product->media->map(fn ($media) => [
                    'id' => $media->id, 'type' => $media->type,
                    'url' => MediaStorage::url($media->path),
                    'alt' => $media->alt ?: $product->title, 'is_primary' => $media->is_primary,
                ]),
                'variants' => $product->variants->where('status', 'active')->values()->map(function ($variant) use ($isPartner, $product) {
                    $regular = $variant->price ?? $product->price;
                    $customer = $variant->discount_price ?? $regular;
                    $final = $isPartner && $variant->partner_price !== null ? $variant->partner_price : $customer;

                    return [
                        'id' => $variant->id, 'name' => $variant->name,
                        'attributes' => $variant->attributes ?? [], 'stock' => $variant->stock,
                        'pricing' => ['regular_price' => $regular, 'sale_price' => $customer, 'final_price' => $final,
                            'is_partner_price' => $isPartner && $variant->partner_price !== null,
                            'discount_amount' => max(0, $regular - $customer)],
                    ];
                }),
                'pricing' => $pricing,
            ],
            'exchangeRequestId' => $exchangeRequestId,
            'exchangeOfferAmount' => $exchangeOfferAmount,
            'latestFeed' => $feed->latestPostsExcept($request, 0),
            'latestVideos' => SocialContent::query()->published()->where('type', 'video')
                ->with('game:id,name,slug,cover')->latest('published_at')->latest('id')->limit(4)->get()
                ->map(fn (SocialContent $video) => $storefront->content($video))->values(),
            'latestProducts' => Product::query()->publiclyVisible()->where('id', '!=', $product->id)
                ->with(['category:id,name', 'type:id,title', 'game:id,name,developer,publisher', 'platforms:id,name', 'attributeValues.attribute:id,name,slug', 'coverMedia', 'variants:id,product_id,status'])
                ->latest()->limit(4)->get()
                ->map(fn (Product $related) => $storefront->product($related, $request->user()))->values(),
        ]);
    }
}
