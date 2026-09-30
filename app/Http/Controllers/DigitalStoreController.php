<?php

namespace App\Http\Controllers;

use App\Models\DigitalOffer;
use App\Models\DigitalProduct;
use App\Services\DigitalOrderService;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DigitalStoreController extends Controller
{
    public function index(): Response
    {
        $products = DigitalProduct::query()
            ->published()
            ->with(['game:id,name,slug,cover,background', 'platform:id,name,slug', 'offers', 'media', 'features'])
            ->whereHas('offers', fn ($query) => $query->where('status', 'active')->whereColumn('stock', '>', 'reserved_stock'))
            ->orderByDesc('featured')
            ->latest('id')
            ->paginate(18)
            ->through(fn (DigitalProduct $product) => $this->productPayload($product));

        return Inertia::render('Digital/Index', ['products' => $products]);
    }

    public function show(DigitalProduct $digitalProduct): Response
    {
        abort_unless($digitalProduct->status === 'published', 404);

        $digitalProduct->load(['game:id,name,slug,cover,background', 'platform:id,name,slug', 'offers', 'media', 'features']);

        return Inertia::render('Digital/Show', [
            'product' => $this->productPayload($digitalProduct),
        ]);
    }

    public function order(Request $request, DigitalProduct $digitalProduct, DigitalOrderService $orders): RedirectResponse
    {
        abort_unless($digitalProduct->status === 'published', 404);

        $data = $request->validate([
            'offer_id' => [
                'required',
                'integer',
                Rule::exists('digital_offers', 'id')->where('digital_product_id', $digitalProduct->id),
            ],
        ]);

        $offer = DigitalOffer::query()->findOrFail($data['offer_id']);
        $order = $orders->create($request->user(), $offer);

        return to_route('account.digital-orders.show', $order)
            ->with('success', 'سفارش دیجیتال ثبت شد؛ ادامه خرید از همین گفت‌وگو انجام می‌شود.');
    }

    private function productPayload(DigitalProduct $product): array
    {
        return [
            ...$product->only(['id', 'title', 'slug', 'short_description', 'support_days', 'featured']),
            'game' => $product->game ? [
                ...$product->game->only(['id', 'name', 'slug']),
                'cover_url' => MediaStorage::url($product->game->cover),
                'background_url' => MediaStorage::url($product->game->background),
            ] : null,
            'platform' => $product->platform?->only(['id', 'name', 'slug']),
            'cover_url' => MediaStorage::url($product->media->firstWhere('is_primary', true)?->path)
                ?: MediaStorage::url($product->media->firstWhere('type', 'image')?->path)
                ?: MediaStorage::url($product->game?->cover),
            'media' => $product->media->map(fn ($media) => [
                ...$media->only(['id', 'type', 'alt', 'is_primary']),
                'url' => MediaStorage::url($media->path),
            ])->values(),
            'features' => $product->features->map(fn ($feature) => $feature->only([
                'id', 'name', 'value',
            ]))->values(),
            'offers' => $product->offers
                ->where('status', 'active')
                ->map(fn ($offer) => [
                    ...$offer->only(['id', 'code', 'label', 'price']),
                    'available_stock' => $offer->availableStock(),
                    'available' => $offer->availableStock() > 0,
                ])
                ->values(),
        ];
    }
}
