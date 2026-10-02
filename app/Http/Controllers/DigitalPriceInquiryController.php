<?php

namespace App\Http\Controllers;

use App\Models\DigitalOffer;
use App\Models\DigitalProduct;
use App\Services\DigitalPriceInquiryService;
use App\Services\DigitalProductMediaStorage;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DigitalPriceInquiryController extends Controller
{
    public function create(Request $request, DigitalProduct $digitalProduct): Response|RedirectResponse
    {
        abort_unless($digitalProduct->status === 'published', 404);

        if ($existing = $this->openInquiry($request, $digitalProduct)) {
            return to_route('account.tickets.show', $existing)
                ->with('info', 'استعلام باز برای این محصول از قبل وجود دارد؛ همان گفت‌وگو را ادامه دهید.');
        }

        $digitalProduct->load([
            'seller:id,name,avatar',
            'platform:id,name',
            'coverMedia',
            'offers' => fn ($query) => $query->where('status', 'active'),
        ]);

        return Inertia::render('Digital/PriceInquiry', [
            'product' => [
                ...$digitalProduct->only(['id', 'title', 'slug']),
                'cover_url' => DigitalProductMediaStorage::url($digitalProduct->coverMedia?->path),
                'platform' => $digitalProduct->platform?->only(['id', 'name']),
                'seller' => $digitalProduct->seller ? [
                    ...$digitalProduct->seller->only(['id', 'name']),
                    'avatar_url' => MediaStorage::url($digitalProduct->seller->avatar),
                ] : null,
                'offers' => $digitalProduct->offers
                    ->map(fn (DigitalOffer $offer) => [
                        ...$offer->only(['id', 'code', 'label', 'price']),
                        'available_stock' => $offer->availableStock(),
                        'available' => $offer->availableStock() > 0,
                        'updated_at' => $offer->updated_at?->toISOString(),
                    ])
                    ->values(),
            ],
            'submitUrl' => route('digital.price-inquiry.store', $digitalProduct, false),
            'productUrl' => route('digital.show', $digitalProduct, false),
        ]);
    }

    public function store(
        Request $request,
        DigitalProduct $digitalProduct,
        DigitalPriceInquiryService $inquiries,
    ): RedirectResponse {
        abort_unless($digitalProduct->status === 'published', 404);

        if ($existing = $this->openInquiry($request, $digitalProduct)) {
            return to_route('account.tickets.show', $existing)
                ->with('info', 'استعلام باز برای این محصول از قبل وجود دارد؛ همان گفت‌وگو را ادامه دهید.');
        }

        $data = $request->validate([
            'offer_id' => [
                'nullable',
                'integer',
                Rule::exists('digital_offers', 'id')
                    ->where('digital_product_id', $digitalProduct->id)
                    ->where('status', 'active'),
            ],
        ], [
            'offer_id.exists' => 'این ظرفیت برای این محصول قابل استعلام نیست.',
        ]);

        // Keep old direct POST clients safe while the storefront now always
        // asks the customer to choose a capacity first.
        $offer = isset($data['offer_id'])
            ? DigitalOffer::query()
                ->where('digital_product_id', $digitalProduct->id)
                ->where('status', 'active')
                ->findOrFail((int) $data['offer_id'])
            : $digitalProduct->offers()
                ->where('status', 'active')
                ->first();

        if (! $offer) {
            throw ValidationException::withMessages([
                'offer_id' => 'برای این محصول هنوز ظرفیتی جهت استعلام تعریف نشده است.',
            ]);
        }

        $ticket = $inquiries->create($request->user(), $digitalProduct, $offer);

        return to_route('account.tickets.show', $ticket)
            ->with('success', 'استعلام '.$offer->label.' برای فروشنده ارسال شد. پاسخ را از همین گفت‌وگو دنبال کنید.');
    }

    private function openInquiry(Request $request, DigitalProduct $digitalProduct): mixed
    {
        return $request->user()
            ->tickets()
            ->where('type', 'digital_price')
            ->where('digital_product_id', $digitalProduct->id)
            ->whereIn('status', ['pending', 'open'])
            ->latest('last_replied_at')
            ->first();
    }
}
