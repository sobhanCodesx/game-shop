<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPublicationScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_scheduled_product_is_not_publicly_visible(): void
    {
        $product = Product::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('products.show', $product->slug))->assertNotFound();
    }

    public function test_product_is_visible_after_publication_date(): void
    {
        $product = Product::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('products.show', $product->slug))->assertOk();
    }
}
