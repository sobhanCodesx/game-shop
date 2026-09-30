<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DigitalProductAgentService
{
    private const OFFER_CODES = [
        'capacity_1' => 'ظرفیت ۱',
        'capacity_2' => 'ظرفیت ۲',
        'capacity_3' => 'ظرفیت ۳',
        'full' => 'فول ظرفیت',
    ];

    public function listSellers(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $query = User::query()
            ->where('role', 'digital-seller')
            ->where('status', 'active')
            ->orderBy('name');

        $term = trim((string) ($data['query'] ?? ''));
        if ($term !== '') {
            $query->where('name', 'like', "%{$term}%");
        }

        return $query
            ->limit((int) ($data['limit'] ?? 20))
            ->get(['id', 'name'])
            ->map(fn (User $seller) => [
                'id' => $seller->id,
                'name' => $seller->name,
            ])
            ->values()
            ->all();
    }

    public function listAttributes(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'query' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $query = $this->digitalAttributes();
        $term = trim((string) ($data['query'] ?? ''));
        if ($term !== '') {
            $query = $query->filter(fn (Attribute $attribute) =>
                str_contains(mb_strtolower($attribute->title), mb_strtolower($term))
                || str_contains(mb_strtolower($attribute->slug), mb_strtolower($term))
            )->values();
        }

        return $query->map(fn (Attribute $attribute) => $this->serializeAttribute($attribute))->all();
    }

    public function get(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->serialize(
            DigitalProduct::query()
                ->with([
                    'game:id,name,slug',
                    'platform:id,name,slug',
                    'seller:id,name',
                    'offers',
                    'media',
                    'attributeValues.attribute.options',
                ])
                ->findOrFail((int) $data['id'])
        );
    }

    public function create(array $arguments): array
    {
        $data = $this->validateProduct($arguments, creating: true);
        $game = Game::query()->findOrFail((int) $data['game_id']);
        $platform = Platform::query()->findOrFail((int) $data['platform_id']);
        $title = trim((string) ($data['title'] ?? '')) ?: "{$game->name} - {$platform->name}";

        $product = DB::transaction(function () use ($data, $title): DigitalProduct {
            $product = DigitalProduct::query()->create([
                'game_id' => (int) $data['game_id'],
                'platform_id' => (int) $data['platform_id'],
                'seller_id' => (int) $data['seller_id'],
                'title' => $title,
                'slug' => $this->uniqueSlug($title),
                'short_description' => filled($data['short_description'] ?? null)
                    ? trim((string) $data['short_description'])
                    : null,
                'support_days' => (int) ($data['support_days'] ?? 7),
                'status' => 'draft',
                'featured' => (bool) ($data['featured'] ?? false),
            ]);

            $this->syncOffers($product, $data['offers']);
            $this->syncFeatures($product, $data['features'] ?? []);

            return $product;
        }, 3);

        return $this->serialize($product->fresh([
            'game:id,name,slug',
            'platform:id,name,slug',
            'seller:id,name',
            'offers',
            'media',
            'attributeValues.attribute.options',
        ]));
    }

    public function update(array $arguments): array
    {
        $data = $this->validateProduct($arguments, creating: false);
        $product = DigitalProduct::query()->findOrFail((int) $data['id']);

        DB::transaction(function () use ($data, $product): void {
            $updates = [];

            if (array_key_exists('game_id', $data)) {
                $updates['game_id'] = (int) $data['game_id'];
            }
            if (array_key_exists('platform_id', $data)) {
                $updates['platform_id'] = (int) $data['platform_id'];
            }
            if (array_key_exists('seller_id', $data)) {
                $updates['seller_id'] = (int) $data['seller_id'];
            }
            if (array_key_exists('title', $data)) {
                $updates['title'] = trim((string) $data['title']);
            }
            if (array_key_exists('short_description', $data)) {
                $updates['short_description'] = filled($data['short_description'])
                    ? trim((string) $data['short_description'])
                    : null;
            }
            if (array_key_exists('support_days', $data)) {
                $updates['support_days'] = (int) $data['support_days'];
            }
            if (array_key_exists('featured', $data)) {
                $updates['featured'] = (bool) $data['featured'];
            }

            if ($updates !== []) {
                $product->update($updates);
            }

            if (array_key_exists('offers', $data)) {
                $this->syncOffers($product, $data['offers']);
            }

            if (array_key_exists('features', $data)) {
                $this->syncFeatures($product, $data['features']);
            }
        }, 3);

        return $this->serialize($product->fresh([
            'game:id,name,slug',
            'platform:id,name,slug',
            'seller:id,name',
            'offers',
            'media',
            'attributeValues.attribute.options',
        ]));
    }

    public function setState(array $arguments): array
    {
        $data = $this->validate($arguments, [
            'id' => ['required', 'integer', 'min:1'],
            'state' => ['required', Rule::in(['draft', 'published', 'hidden'])],
        ]);

        $product = DigitalProduct::query()->findOrFail((int) $data['id']);
        $state = (string) $data['state'];

        if ($state === 'published') {
            $this->ensurePublishingAllowed();
            $this->assertReadyForPublish($product);
        }

        $product->update(['status' => $state]);

        return $this->serialize($product->fresh([
            'game:id,name,slug',
            'platform:id,name,slug',
            'seller:id,name',
            'offers',
            'media',
            'attributeValues.attribute.options',
        ]));
    }

    private function validateProduct(array $arguments, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        $data = $this->validate($arguments, [
            'id' => $creating ? ['prohibited'] : ['required', 'integer', 'min:1'],
            'game_id' => [$required, 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'platform_id' => [$required, 'integer', Rule::exists('platforms', 'id')->whereNull('deleted_at')],
            'seller_id' => [
                $required,
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('role', 'digital-seller')->where('status', 'active')
                ),
            ],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'support_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'featured' => ['sometimes', 'boolean'],
            'offers' => [$required, 'array', 'size:4'],
            'offers.*.code' => ['required_with:offers', Rule::in(array_keys(self::OFFER_CODES)), 'distinct'],
            'offers.*.price' => ['required_with:offers', 'integer', 'min:1'],
            'offers.*.stock' => ['required_with:offers', 'integer', 'min:0'],
            'offers.*.status' => ['required_with:offers', Rule::in(['active', 'inactive'])],
            'features' => ['sometimes', 'array', 'max:30'],
            'features.*.attribute_slug' => ['required', 'string', 'max:120', 'distinct'],
            'features.*.values' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.values.*' => ['required', 'string', 'max:100', 'distinct'],
        ]);

        if (isset($data['offers'])) {
            $codes = collect($data['offers'])->pluck('code')->sort()->values()->all();
            $expected = collect(array_keys(self::OFFER_CODES))->sort()->values()->all();
            if ($codes !== $expected) {
                throw ValidationException::withMessages([
                    'offers' => 'Offers must contain capacity_1, capacity_2, capacity_3 and full exactly once.',
                ]);
            }
        }

        if (isset($data['features'])) {
            $this->validateFeatures($data['features']);
        }

        return $data;
    }

    private function validateFeatures(array $features): void
    {
        $available = $this->digitalAttributes()->keyBy('slug');

        foreach ($features as $index => $feature) {
            $slug = (string) $feature['attribute_slug'];
            /** @var Attribute|null $attribute */
            $attribute = $available->get($slug);

            if (! $attribute) {
                throw ValidationException::withMessages([
                    "features.{$index}.attribute_slug" => "Unknown or unavailable digital-product attribute: {$slug}.",
                ]);
            }

            $values = array_values(array_unique(array_map('strval', (array) $feature['values'])));
            $allowed = $attribute->input_type === 'boolean'
                ? ['1', '0']
                : $attribute->options->pluck('value')->map(fn ($value) => (string) $value)->all();

            foreach ($values as $value) {
                if (! in_array($value, $allowed, true)) {
                    throw ValidationException::withMessages([
                        "features.{$index}.values" => "Invalid value '{$value}' for {$attribute->title}.",
                    ]);
                }
            }

            if ($attribute->input_type !== 'multi_select' && count($values) > 1) {
                throw ValidationException::withMessages([
                    "features.{$index}.values" => "{$attribute->title} accepts only one value.",
                ]);
            }
        }
    }

    private function syncOffers(DigitalProduct $product, array $offers): void
    {
        foreach ($offers as $index => $offer) {
            $product->offers()->updateOrCreate(
                ['code' => $offer['code']],
                [
                    'label' => self::OFFER_CODES[$offer['code']],
                    'price' => (int) $offer['price'],
                    'stock' => (int) $offer['stock'],
                    'status' => (string) $offer['status'],
                    'sort_order' => $index + 1,
                ]
            );
        }
    }

    private function syncFeatures(DigitalProduct $product, array $features): void
    {
        $available = $this->digitalAttributes()->keyBy('slug');

        $product->attributeValues()->delete();

        foreach ($features as $feature) {
            /** @var Attribute $attribute */
            $attribute = $available->get((string) $feature['attribute_slug']);
            foreach (array_values(array_unique(array_map('strval', $feature['values']))) as $value) {
                $product->attributeValues()->create([
                    'attribute_id' => $attribute->id,
                    'value' => $value,
                ]);
            }
        }
    }

    private function assertReadyForPublish(DigitalProduct $product): void
    {
        $product->loadMissing(['offers', 'media', 'attributeValues']);

        if ($product->media->where('type', 'image')->isEmpty()) {
            throw new RuntimeException('Digital product cannot be published without at least one image.');
        }

        $codes = $product->offers->pluck('code')->sort()->values()->all();
        $expected = collect(array_keys(self::OFFER_CODES))->sort()->values()->all();
        if ($codes !== $expected) {
            throw new RuntimeException('Digital product must have all four sale offers before publishing.');
        }

        if ($product->offers->where('status', 'active')->isEmpty()) {
            throw new RuntimeException('Digital product cannot be published without at least one active offer.');
        }

        $required = $this->digitalAttributes()->where('is_required', true);
        $selectedAttributeIds = $product->attributeValues->pluck('attribute_id')->unique();

        foreach ($required as $attribute) {
            if (! $selectedAttributeIds->contains($attribute->id)) {
                throw new RuntimeException("Digital product is missing required feature: {$attribute->title}.");
            }
        }
    }

    private function digitalAttributes(): Collection
    {
        return Attribute::query()
            ->with(['options' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')])
            ->where('status', 'active')
            ->where('is_filterable', true)
            ->whereIn('input_type', ['select', 'multi_select', 'boolean'])
            ->whereNotIn('slug', ['capacity', 'platform'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function serialize(DigitalProduct $product): array
    {
        $product->loadMissing([
            'game:id,name,slug',
            'platform:id,name,slug',
            'seller:id,name',
            'offers',
            'media',
            'attributeValues.attribute.options',
        ]);

        $features = $product->attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values) {
                $attribute = $values->first()?->attribute;
                if (! $attribute) {
                    return null;
                }

                return [
                    'attribute_slug' => $attribute->slug,
                    'attribute_title' => $attribute->title,
                    'values' => $values->map(function ($item) use ($attribute): array {
                        $label = $attribute->input_type === 'boolean'
                            ? ($item->value === '1' ? 'بله' : 'خیر')
                            : ($attribute->options->firstWhere('value', $item->value)?->title ?? $item->value);

                        return ['value' => $item->value, 'label' => $label];
                    })->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'status' => $product->status,
            'game' => $product->game?->only(['id', 'name', 'slug']),
            'platform' => $product->platform?->only(['id', 'name', 'slug']),
            'seller' => $product->seller?->only(['id', 'name']),
            'short_description' => $product->short_description,
            'support_days' => (int) $product->support_days,
            'featured' => (bool) $product->featured,
            'offers' => $product->offers->map(fn ($offer) => [
                'id' => $offer->id,
                'code' => $offer->code,
                'label' => $offer->label,
                'price' => (int) $offer->price,
                'stock' => (int) $offer->stock,
                'reserved_stock' => (int) $offer->reserved_stock,
                'status' => $offer->status,
            ])->values()->all(),
            'features' => $features,
            'media' => $product->media->map(fn ($media) => [
                'id' => $media->id,
                'type' => $media->type,
                'url' => MediaStorage::url($media->path),
                'alt' => $media->alt,
                'sort_order' => (int) $media->sort_order,
                'is_primary' => (bool) $media->is_primary,
            ])->values()->all(),
            'url' => $product->status === 'published'
                ? route('digital.show', $product->slug, false)
                : null,
        ];
    }

    private function serializeAttribute(Attribute $attribute): array
    {
        return [
            'slug' => $attribute->slug,
            'title' => $attribute->title,
            'input_type' => $attribute->input_type,
            'required' => (bool) $attribute->is_required,
            'options' => $attribute->input_type === 'boolean'
                ? [
                    ['value' => '1', 'label' => 'بله'],
                    ['value' => '0', 'label' => 'خیر'],
                ]
                : $attribute->options->map(fn ($option) => [
                    'value' => (string) $option->value,
                    'label' => $option->title,
                ])->values()->all(),
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'digital-game';
        $slug = $base;

        for ($counter = 2; DigitalProduct::withTrashed()->where('slug', $slug)->exists(); $counter++) {
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }

    private function ensurePublishingAllowed(): void
    {
        if (! (bool) config('content_agent.allow_publish')) {
            throw new RuntimeException(
                'Publishing is disabled. Set PLAYNEXUS_CONTENT_AGENT_ALLOW_PUBLISH=true on the server to enable it.'
            );
        }
    }

    private function validate(array $arguments, array $rules): array
    {
        return Validator::make($arguments, $rules)->validate();
    }
}
