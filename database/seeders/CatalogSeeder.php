<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $playStation = Category::updateOrCreate(
            ['slug' => 'playstation'],
            ['name' => 'پلی‌استیشن', 'sort_order' => 1, 'status' => 'active'],
        );

        $games = Category::updateOrCreate(
            ['slug' => 'games'],
            ['name' => 'بازی‌ها', 'sort_order' => 2, 'status' => 'active'],
        );

        $ps5Games = Category::updateOrCreate(
            ['slug' => 'ps5-games'],
            ['name' => 'بازی PS5', 'parent_id' => $games->id, 'sort_order' => 1, 'status' => 'active'],
        );

        $attributes = [
            ['name' => 'ریجن', 'slug' => 'region', 'type' => 'select', 'options' => ['Global', 'US', 'EU', 'UK', 'TR', 'AE']],
            ['name' => 'نسخه بازی', 'slug' => 'edition', 'type' => 'select', 'options' => ['Standard', 'Deluxe', 'Gold', 'Ultimate', 'Complete']],
            ['name' => 'زبان', 'slug' => 'language', 'type' => 'select', 'options' => ['انگلیسی', 'فارسی', 'چندزبانه']],
        ];

        foreach ($attributes as $index => $attribute) {
            CategoryAttribute::updateOrCreate(
                ['category_id' => $ps5Games->id, 'slug' => $attribute['slug']],
                [...$attribute, 'is_filterable' => true, 'sort_order' => $index],
            );
        }

        $sony = Brand::updateOrCreate(
            ['slug' => 'sony'],
            ['name' => 'Sony', 'website' => 'https://www.sony.com', 'status' => 'active'],
        );

        $rockstar = Brand::updateOrCreate(
            ['slug' => 'rockstar-games'],
            ['name' => 'Rockstar Games', 'status' => 'active'],
        );

        $ps5 = Platform::updateOrCreate(
            ['slug' => 'ps5'],
            ['name' => 'PlayStation 5', 'manufacturer' => 'Sony', 'sort_order' => 1, 'status' => 'active'],
        );

        $pc = Platform::updateOrCreate(
            ['slug' => 'pc'],
            ['name' => 'PC', 'sort_order' => 2, 'status' => 'active'],
        );

        $gta = Game::updateOrCreate(
            ['slug' => 'grand-theft-auto-vi'],
            [
                'name' => 'Grand Theft Auto VI',
                'developer' => 'Rockstar Games',
                'publisher' => 'Rockstar Games',
                'age_rating' => 'PEGI 18',
                'status' => 'published',
            ],
        );
        $gta->platforms()->sync([$ps5->id]);

        $console = Product::updateOrCreate(
            ['sku' => 'PS5-SLIM-1TB'],
            [
                'title' => 'کنسول PlayStation 5 Slim یک ترابایت',
                'slug' => 'playstation-5-slim-1tb',
                'category_id' => $playStation->id,
                'brand_id' => $sony->id,
                'product_type' => 'console',
                'price' => 48_900_000,
                'stock' => 7,
                'status' => 'published',
                'visibility' => 'public',
                'featured' => true,
            ],
        );
        $console->platforms()->sync([$ps5->id]);

        $gameProduct = Product::updateOrCreate(
            ['sku' => 'GTA6-PS5-DISC'],
            [
                'title' => 'دیسک بازی GTA VI برای PS5',
                'slug' => 'gta-vi-ps5-disc',
                'category_id' => $ps5Games->id,
                'brand_id' => $rockstar->id,
                'game_id' => $gta->id,
                'product_type' => 'physical_game',
                'price' => 6_500_000,
                'discount_price' => 6_190_000,
                'stock' => 12,
                'status' => 'active',
                'visibility' => 'public',
                'trade_enabled' => true,
            ],
        );
        $gameProduct->platforms()->sync([$ps5->id]);

        $pc->games()->syncWithoutDetaching([$gta->id]);
    }
}
