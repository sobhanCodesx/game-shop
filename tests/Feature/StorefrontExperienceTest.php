<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SocialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_only_returns_publicly_visible_products(): void
    {
        Product::factory()->create(['title' => 'محصول قابل نمایش', 'status' => 'published', 'visibility' => 'public']);
        Product::factory()->create(['title' => 'محصول مخفی', 'status' => 'draft', 'visibility' => 'public']);

        $this->get(route('shop.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Shop/Index')->has('products.data', 1)
            ->where('products.data.0.title', 'محصول قابل نمایش'));
    }

    public function test_active_category_has_a_real_landing_page(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        Product::factory()->create(['category_id' => $category->id]);

        $this->get(route('categories.show', $category))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Categories/Show')->where('category.id', $category->id)->has('products.data', 1));
    }

    public function test_product_variant_can_be_added_to_session_cart(): void
    {
        $product = Product::factory()->create(['stock' => 4]);
        $variant = ProductVariant::query()->create(['product_id' => $product->id, 'name' => 'ظرفیت ۲', 'sku' => 'CAP-2-TEST', 'price' => 850000, 'stock' => 2, 'status' => 'active']);

        $this->post(route('cart.items.store'), ['product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect()->assertSessionHas("cart.{$product->id}:{$variant->id}.quantity", 1);

        $this->get(route('cart.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Cart/Index')->has('items', 1)->where('items.0.variant', 'ظرفیت ۲')->where('total', 850000));
    }

    public function test_product_detail_exposes_real_content_media_and_specifications(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'short_description' => 'خلاصه واقعی محصول',
            'description' => 'توضیحات کامل و محتوایی محصول برای صفحه جزئیات.',
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $attribute = CategoryAttribute::query()->create([
            'category_id' => $category->id,
            'name' => 'ژانر',
            'slug' => 'genre',
            'type' => 'text',
        ]);

        ProductAttributeValue::query()->create([
            'product_id' => $product->id,
            'category_attribute_id' => $attribute->id,
            'value' => 'اکشن ماجراجویی',
        ]);
        ProductMedia::query()->create([
            'product_id' => $product->id,
            'type' => 'image',
            'path' => 'products/test-cover.jpg',
            'alt' => 'کاور محصول تست',
            'is_primary' => true,
        ]);

        $this->get(route('products.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('product.description', 'توضیحات کامل و محتوایی محصول برای صفحه جزئیات.')
            ->where('product.attributes.0.name', 'ژانر')
            ->where('product.attributes.0.value', 'اکشن ماجراجویی')
            ->where('product.media.0.alt', 'کاور محصول تست'));
    }

    public function test_search_groups_real_results(): void
    {
        Product::factory()->create(['title' => 'بازی تست جستجو']);

        $this->get(route('search', ['q' => 'تست']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Search/Index')->where('query', 'تست')->has('products', 1));
    }

    public function test_discover_returns_a_paginated_mixed_feed_and_json_pages(): void
    {
        $product = Product::factory()->create(['title' => 'محصول اکسپلور']);
        foreach (range(1, 19) as $index) {
            ProductMedia::query()->create([
                'product_id' => $product->id,
                'type' => 'image',
                'path' => "products/explore-{$index}.jpg",
                'is_primary' => $index === 1,
            ]);
        }
        SocialContent::query()->create([
            'type' => 'video',
            'title' => 'ویدیوی اکسپلور',
            'slug' => 'explore-video',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('discover'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Discover/Index')
            ->has('feed.data', 18)
            ->where('feed.data.0.kind', 'product_media')
            ->where('feed.total', 20)
            ->where('feed.current_page', 1));

        $this->getJson(route('discover', ['page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonStructure(['data' => [['key', 'kind', 'data']], 'current_page', 'last_page']);
    }

    public function test_media_stream_supports_http_range_for_video_seeking(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('videos/seekable.mp4', str_repeat('a', 1000));

        $this->withHeader('Range', 'bytes=100-199')
            ->get(route('media.stream', ['path' => 'videos/seekable.mp4']))
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 100-199/1000')
            ->assertHeader('Content-Length', '100');
    }
}
