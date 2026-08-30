<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_public_sale_price_without_internal_prices(): void
    {
        $product = Product::factory()->create([
            'price' => 5_000_000,
            'discount_price' => 4_700_000,
            'partner_price' => 4_100_000,
            'cost_price' => 3_500_000,
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Products/Show')
                ->where('product.pricing.final_price', 4_700_000)
                ->where('product.pricing.is_partner_price', false)
                ->missing('product.partner_price')
                ->missing('product.cost_price'));
    }

    public function test_partner_sees_partner_price(): void
    {
        $partner = User::factory()->create(['role' => 'partner']);
        $product = Product::factory()->create([
            'price' => 5_000_000,
            'discount_price' => 4_700_000,
            'partner_price' => 4_100_000,
        ]);

        $this->actingAs($partner)
            ->get(route('products.show', $product->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.pricing.final_price', 4_100_000)
                ->where('product.pricing.is_partner_price', true));
    }

    public function test_product_rich_text_is_sanitized_for_storefront_rendering(): void
    {
        $product = Product::factory()->create([
            'short_description' => '<b onclick="alert(1)">معرفی کوتاه</b>',
            'description' => '<p class="bad">متن <strong onclick="alert(1)">مهم</strong></p><script>alert(1)</script><img src=x onerror=alert(1)>',
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('product.short_description', 'معرفی کوتاه')
                ->where('product.description', '<p>متن <strong>مهم</strong></p>'));
    }
}
