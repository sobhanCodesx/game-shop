<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\DigitalOffer;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Services\DigitalOrderService;
use App\Services\DigitalProductMediaStorage;
use App\Services\TicketService;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DigitalStoreController extends Controller
{
    public function index(Request $request): Response
    {
        $filterAttributes = $this->filterAttributes();
        $selectedFilters = $this->selectedFilters($request, $filterAttributes);
        $selectedCategory = trim($request->string('category')->toString());
        $selectedGame = trim($request->string('game')->toString());

        $query = DigitalProduct::query()
            ->published()
            ->with([
                'category:id,name,slug',
                'game:id,name,slug,cover,background,status',
                'platform:id,name,slug',
                'offers',
                'coverMedia',
            ])
            ->when(
                $selectedGame !== '',
                fn ($productQuery) => $productQuery->whereHas(
                    'game',
                    fn ($gameQuery) => $gameQuery->where('slug', $selectedGame),
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

    public function show(DigitalProduct $digitalProduct): Response
    {
        abort_unless($digitalProduct->status === 'published', 404);

        $digitalProduct->load([
            'category:id,name,slug',
            'game:id,name,slug,cover,background',
            'platform:id,name,slug',
            'offers',
            'media',
            'seller:id,name,avatar',
            'attributeValues.attribute.options',
        ]);

        return Inertia::render('Digital/Show', [
            'product' => $this->productPayload($digitalProduct, true),
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
        ]);

        $offer = DigitalOffer::query()->findOrFail($data['offer_id']);
        $order = $orders->create($request->user(), $offer);

        return to_route('account.digital-orders.show', $order)
            ->with(
                'success',
                'سفارش دیجیتال ثبت شد؛ ادامه خرید از همین گفت‌وگو انجام می‌شود.',
            );
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
            'category' => $product->category?->only(['id', 'name', 'slug']),
            'game' => $product->game ? [
                ...$product->game->only(['id', 'name', 'slug']),
                'cover_url' => MediaStorage::url($product->game->cover),
                'background_url' => MediaStorage::url($product->game->background),
                'channel_url' => in_array($product->game->status, ['active', 'published'], true)
                    ? route('channels.show', $product->game->slug, false)
                    : null,
            ] : null,
            'platform' => $product->platform?->only(['id', 'name', 'slug']),
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
                    'available_stock' => $offer->availableStock(),
                    'available' => $offer->availableStock() > 0,
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

                return [
                    'id' => $attribute->id,
                    'name' => $attribute->title,
                    'slug' => $attribute->slug,
                    'value' => $labels->join('، '),
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
