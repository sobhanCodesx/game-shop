<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\ProductType;
use Illuminate\Database\Seeder;

class ProductCatalogFoundationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('catalog.product.types', []) as $slug => $definition) {
            ProductType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $definition['label'],
                    'inventory_type' => ($definition['uses_digital_delivery'] ?? false) ? 'digital' : 'standard',
                    'supports_variants' => true,
                    'supports_shipping' => $definition['requires_shipping'] ?? false,
                    'supports_exchange' => $definition['allows_trade'] ?? false,
                    'supports_digital_delivery' => $definition['uses_digital_delivery'] ?? false,
                    'supports_digital_inventory' => in_array($slug, ['digital_game', 'game_account', 'capacity_account', 'full_capacity', 'gift_card'], true),
                    'requires_cover' => true,
                    'status' => 'active',
                ],
            );
        }

        $definitions = [
            'platform' => ['پلتفرم', 'select', true],
            'capacity' => ['ظرفیت', 'select', true],
            'edition' => ['نسخه', 'select', true],
            'region' => ['ریجن', 'select', true],
            'color' => ['رنگ', 'select', true],
            'storage' => ['حافظه', 'select', true],
            'condition' => ['وضعیت کالا', 'select', true],
            'warranty' => ['گارانتی', 'text', false],
        ];

        foreach ($definitions as $slug => [$title, $inputType, $forVariant]) {
            Attribute::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'input_type' => $inputType,
                    'is_filterable' => $forVariant,
                    'is_searchable' => false,
                    'is_visible_on_product' => true,
                    'is_usable_for_variant' => $forVariant,
                    'status' => 'active',
                ],
            );
        }

        $capacity = Attribute::query()->where('slug', 'capacity')->firstOrFail();
        foreach ([1, 2, 3] as $value) {
            $capacity->options()->updateOrCreate(
                ['value' => (string) $value],
                ['title' => "ظرفیت {$value}", 'status' => 'active', 'sort_order' => $value],
            );
        }

        $capacityAccount = ProductType::query()->where('slug', 'capacity_account')->first();
        $capacityAccount?->attributes()->syncWithoutDetaching([
            $capacity->id => ['is_required' => true, 'sort_order' => 10],
        ]);

        ProductType::query()->each(function (ProductType $type): void {
            $type->products()->whereNull('product_type_id')->where('product_type', $type->slug)
                ->update(['product_type_id' => $type->id]);
        });
    }
}
