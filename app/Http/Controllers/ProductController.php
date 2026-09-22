<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Ticket;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\ProductPageDataService;
use App\Services\ProductPriceService;
use App\Services\StorefrontDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(
        Request $request,
        Product $product,
        ProductPriceService $prices,
        ProductPageDataService $page,
        FeedService $feed,
        StorefrontDataService $storefront,
    ): Response {
        abort_unless(
            Product::query()->publiclyVisible()->whereKey($product->getKey())->exists(),
            404,
        );

        $cached = $page->get($product);
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

        $pricing = $prices->forUser($product, $request->user());
        $variants = $product->variants()
            ->where('status', 'active')
            ->get()
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
            })
            ->values();

        return Inertia::render('Products/Show', [
            ...$page->seo(
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
            'latestFeed' => $feed->latestPostsExcept($request, 0),
            'latestVideos' => SocialContent::query()
                ->published()
                ->where('type', 'video')
                ->with('game:id,name,slug,cover')
                ->latest('published_at')
                ->latest('id')
                ->limit(4)
                ->get()
                ->map(fn (SocialContent $video) => $storefront->content($video))
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
                ->latest()
                ->limit(4)
                ->get()
                ->map(fn (Product $related) => $storefront->product($related, $request->user()))
                ->values(),
        ]);
    }

}
