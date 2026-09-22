<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontCachePhaseSixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::store('file')->flush();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();

        parent::tearDown();
    }

    public function test_product_static_data_is_cached_while_price_and_variant_stock_remain_live(): void
    {
        $product = Product::factory()->create([
            'title' => 'Cached Product',
            'slug' => 'cached-product',
            'price' => 100000,
            'discount_price' => null,
            'partner_price' => 70000,
            'availability' => 'in_stock',
            'short_description' => 'Old product description',
        ]);

        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'sku' => 'VAR-CACHE-1',
            'price' => 90000,
            'stock' => 2,
            'status' => 'active',
        ]);

        $url = route('products.show', $product->slug);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.title', 'Cached Product')
                ->where('product.pricing.final_price', 100000)
                ->where('product.variants.0.stock', 2)
                ->where('product.variants.0.pricing.final_price', 90000)
                ->where('seo.structuredData.@graph.0.offers.price', 1000000));

        Product::query()->whereKey($product->id)->update([
            'price' => 130000,
            'availability' => 'out_of_stock',
        ]);
        ProductVariant::query()->whereKey($variant->id)->update([
            'price' => 80000,
            'stock' => 9,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.title', 'Cached Product')
                ->where('product.pricing.final_price', 130000)
                ->where('product.availability', 'out_of_stock')
                ->where('product.variants.0.stock', 9)
                ->where('product.variants.0.pricing.final_price', 80000)
                ->where('seo.structuredData.@graph.0.offers.price', 1300000)
                ->where('seo.structuredData.@graph.0.offers.availability', 'https://schema.org/OutOfStock'));

        $partner = User::factory()->create(['role' => 'partner']);

        $this->actingAs($partner)
            ->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.pricing.final_price', 70000)
                ->where('product.pricing.is_partner_price', true)
                ->where('seo.structuredData.@graph.0.offers.price', 700000));

        $product->refresh()->update([
            'title' => 'Updated Cached Product',
            'short_description' => 'New product description',
        ]);

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.title', 'Updated Cached Product')
                ->where('product.short_description', 'New product description'));
    }
}
