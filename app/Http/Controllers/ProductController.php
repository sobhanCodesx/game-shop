<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Ticket;
use App\Services\ProductPriceService;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaStorage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, ProductPriceService $prices): Response
    {
        abort_unless(Product::query()->publiclyVisible()->whereKey($product->getKey())->exists(), 404);

        $product->loadMissing(['category:id,name', 'brand:id,name', 'platforms:id,name', 'attributeValues.attribute:id,name', 'media', 'variants']);

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
                ->where(fn ($query) => $query->whereNull('exchange_credit_expires_at')->orWhere('exchange_credit_expires_at', '>', now()))
                ->value('id');
        }

        return Inertia::render('Products/Show', [
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
                'pricing' => $prices->forUser($product, $request->user()),
            ],
            'exchangeRequestId' => $exchangeRequestId,
        ]);
    }
}
