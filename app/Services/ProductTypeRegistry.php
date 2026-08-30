<?php

namespace App\Services;

use App\Models\ProductType;

class ProductTypeRegistry
{
    public function activeOptions(): array
    {
        $types = ProductType::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($types->isNotEmpty()) {
            return $types->map(fn (ProductType $type) => [
                'id' => $type->slug,
                'databaseId' => $type->id,
                'label' => $type->title,
                'description' => null,
                'requires_shipping' => $type->supports_shipping,
                'allows_trade' => $type->supports_exchange,
                'uses_digital_delivery' => $type->supports_digital_delivery,
                'supports_variants' => $type->supports_variants,
                'supports_digital_inventory' => $type->supports_digital_inventory,
                'requires_cover' => $type->requires_cover,
            ])->all();
        }

        return collect(config('catalog.product.types', []))
            ->map(fn (array $option, string $slug) => ['id' => $slug, ...$option])
            ->values()
            ->all();
    }

    public function exists(string $slug): bool
    {
        return ProductType::query()->where('slug', $slug)->where('status', 'active')->exists()
            || array_key_exists($slug, config('catalog.product.types', []));
    }

    public function idFor(string $slug): ?int
    {
        return ProductType::query()->where('slug', $slug)->value('id');
    }
}
