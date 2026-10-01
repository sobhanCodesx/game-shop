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
use App\Services\StorefrontRecommendationService;
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
        StorefrontRecommendationService $recommendations,
    ): Response {
        abort_unless(
            Product::query()->publiclyVisible()->whereKey($product->getKey())->exists(),
            404,
        );

        $cached = $page->get($product);
        $product->loadMissing('game:id,name,slug,cover');
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
            'relatedGame' => $product->game ? [
                'id' => $product->game->id,
                'name' => $product->game->name,
                'slug' => $product->game->slug,
                'channel_url' => route('channels.show', $product->game->slug, false),
            ] : null,
            'latestFeed' => $product->game
                ? $feed->channelPosts($request, $product->game, 4)
                : $feed->latestPostsExcept($request, 0, 4),
            'latestVideos' => SocialContent::query()
                ->published()
                ->where('type', 'video')
                ->when(
                    $product->game_id,
                    fn ($query) => $query->where('game_id', $product->game_id),
                )
                ->with('game:id,name,slug,cover')
                ->latest('published_at')
                ->latest('id')
                ->limit(4)
                ->get()
                ->map(fn (SocialContent $video) => $storefront->content($video))
                ->values(),
            'latestProducts' => $recommendations->forProduct(
                $product,
                $request->user(),
                10,
            ),
        ]);
    }

}
