<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Platform;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_search_paginated_catalog(): void
    {
        $product = Product::factory()->create(['title' => 'PlayStation Special']);
        Storage::disk('public')->put('products/playstation-cover.jpg', 'cover');
        $product->media()->create([
            'type' => 'image', 'path' => 'products/playstation-cover.jpg',
            'sort_order' => 0, 'is_primary' => true,
        ]);
        Product::factory()->create(['title' => 'Xbox Controller']);

        $this->actingAs($this->admin)
            ->get('/admin/products?search=PlayStation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Resource/Index')
                ->has('items', 1)
                ->where('items.0.coverUrl', 'http://localhost/storage/products/playstation-cover.jpg')
                ->where('items.0.mediaUrl', route('admin.products.media.edit', $product))
                ->where('pagination.total', 1));
    }

    public function test_product_catalog_has_a_real_second_page(): void
    {
        Product::factory()->count(25)->create();

        $this->actingAs($this->admin)
            ->get('/admin/products?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Resource/Index')
                ->has('items', 5)
                ->where('pagination.currentPage', 2)
                ->where('pagination.lastPage', 2)
                ->where('pagination.perPage', 20)
                ->where('pagination.total', 25));
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin)->post('/admin/categories', [
            'name' => 'تجهیزات گیمینگ',
            'slug' => 'gaming-equipment',
            'sort_order' => 1,
            'status' => 'active',
        ])->assertRedirect('/admin/categories');

        $this->assertDatabaseHas('categories', ['slug' => 'gaming-equipment']);
    }

    public function test_admin_can_create_child_category_with_filterable_attributes(): void
    {
        $parent = Category::factory()->create();

        $this->actingAs($this->admin)->post('/admin/categories', [
            'name' => 'بازی پلی‌استیشن ۵',
            'slug' => 'ps5-games',
            'parent_id' => $parent->id,
            'sort_order' => 1,
            'status' => 'active',
            'attributes' => [[
                'name' => 'ریجن',
                'slug' => 'region',
                'type' => 'select',
                'options' => ['اروپا', 'آمریکا'],
                'is_required' => true,
                'is_filterable' => true,
            ]],
        ])->assertRedirect('/admin/categories');

        $category = Category::where('slug', 'ps5-games')->firstOrFail();
        $this->assertSame($parent->id, $category->parent_id);
        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'slug' => 'region',
            'is_filterable' => true,
        ]);
    }

    public function test_admin_can_create_product_with_platforms(): void
    {
        $category = Category::factory()->create();
        $attribute = CategoryAttribute::create([
            'category_id' => $category->id,
            'name' => 'ریجن',
            'slug' => 'region',
            'type' => 'select',
            'options' => ['اروپا', 'آمریکا'],
            'is_filterable' => true,
        ]);
        $platform = Platform::factory()->create();

        $this->actingAs($this->admin)->post('/admin/products', [
            'title' => 'God of War Ragnarok',
            'slug' => 'god-of-war-ragnarok',
            'sku' => 'GOW-PS5-001',
            'category_id' => $category->id,
            'platform_ids' => [$platform->id],
            'product_type' => 'physical_game',
            'price' => 4_500_000,
            'partner_price' => 4_100_000,
            'cost_price' => 3_800_000,
            'stock' => 5,
            'low_stock_threshold' => 2,
            'availability' => 'in_stock',
            'release_date' => '2026-09-10',
            'published_at' => '2026-09-01',
            'attribute_values' => [$attribute->id => 'اروپا'],
            'minimum_quantity' => 1,
            'status' => 'published',
            'visibility' => 'public',
            'delivery_method' => 'manual',
            'tags' => ['PS5', 'اکشن'],
            'media' => [[
                'file' => UploadedFile::fake()->image('god-of-war-cover.jpg'),
                'alt' => 'کاور God of War',
                'is_primary' => true,
            ]],
        ])->assertRedirect('/admin/products');

        $product = Product::where('sku', 'GOW-PS5-001')->firstOrFail();

        $this->assertTrue($product->platforms()->whereKey($platform)->exists());
        $this->assertSame(4_100_000, $product->partner_price);
        $this->assertSame(['PS5', 'اکشن'], $product->tags);
        $this->assertSame('2026-09-10', $product->release_date->format('Y-m-d'));
        $this->assertSame('2026-09-01', $product->published_at->format('Y-m-d'));
        $this->assertTrue($product->coverMedia()->exists());
        $this->assertDatabaseHas('product_attribute_values', [
            'product_id' => $product->id,
            'category_attribute_id' => $attribute->id,
            'value' => 'اروپا',
        ]);
    }

    public function test_product_create_uses_dedicated_guided_form(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/products/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/ProductForm')
                ->has('options.categories')
                ->has('options.platforms')
                ->where('options.productTypes.0.id', 'physical_game')
                ->where('options.productTypes.0.requires_shipping', true)
                ->has('options.availability', count(config('catalog.product.availability')))
                ->has('options.conditions', count(config('catalog.product.conditions')))
                ->has('options.deliveryMethods', count(config('catalog.product.delivery_methods')))
                ->has('options.statuses', count(config('catalog.product.statuses')))
                ->has('options.visibilities', count(config('catalog.product.visibilities'))));
    }

    public function test_capacity_game_stores_independent_prices_and_stock_per_capacity(): void
    {
        $payload = [
            'title' => 'EA Sports FC 27',
            'slug' => 'ea-sports-fc-27-capacity',
            'sku' => 'FC27-ACCOUNT',
            'product_type' => 'capacity_account',
            'low_stock_threshold' => 2,
            'availability' => 'in_stock',
            'minimum_quantity' => 1,
            'status' => 'draft',
            'visibility' => 'public',
            'variants' => collect([1, 2, 3])->map(fn (int $capacity) => [
                'capacity' => $capacity,
                'sku' => "FC27-CAP-{$capacity}",
                'price' => $capacity * 1_000_000,
                'partner_price' => $capacity * 900_000,
                'stock' => $capacity + 1,
                'status' => 'active',
            ])->all(),
            'media' => [[
                'file' => UploadedFile::fake()->image('fc27-cover.jpg'),
                'alt' => 'کاور FC 27',
                'is_primary' => true,
            ]],
        ];

        $this->actingAs($this->admin)
            ->post('/admin/products', $payload)
            ->assertRedirect('/admin/products');

        $product = Product::where('sku', 'FC27-ACCOUNT')->firstOrFail();

        $this->assertSame(1_000_000, $product->price);
        $this->assertSame(9, $product->stock);
        $this->assertCount(3, $product->variants);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => 'FC27-CAP-2',
            'price' => 2_000_000,
            'partner_price' => 1_800_000,
            'stock' => 3,
        ]);
    }

    public function test_price_change_is_recorded(): void
    {
        $product = Product::factory()->create(['price' => 1_000_000]);
        $product->media()->create([
            'type' => 'image', 'path' => 'products/price-cover.jpg',
            'sort_order' => 0, 'is_primary' => true,
        ]);

        $this->actingAs($this->admin)->put("/admin/products/{$product->id}", [
            'title' => $product->title,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'platform_ids' => [],
            'product_type' => $product->product_type,
            'price' => 1_200_000,
            'stock' => $product->stock,
            'low_stock_threshold' => $product->low_stock_threshold,
            'availability' => $product->availability,
            'minimum_quantity' => $product->minimum_quantity,
            'status' => $product->status,
            'visibility' => $product->visibility,
        ])->assertRedirect('/admin/products');

        $this->assertDatabaseHas('product_price_histories', [
            'product_id' => $product->id,
            'old_price' => 1_000_000,
            'new_price' => 1_200_000,
        ]);
    }

    public function test_legacy_active_status_is_normalized_in_edit_form(): void
    {
        $product = Product::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)
            ->get("/admin/products/{$product->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Catalog/ProductForm')
                ->where('item.status', 'published'));
    }

    public function test_admin_can_archive_product(): void
    {
        $product = Product::factory()->create(['status' => 'published']);
        $product->media()->create([
            'type' => 'image', 'path' => 'products/archive-cover.jpg',
            'sort_order' => 0, 'is_primary' => true,
        ]);

        $this->actingAs($this->admin)->put("/admin/products/{$product->id}", [
            'title' => $product->title, 'slug' => $product->slug, 'sku' => $product->sku,
            'product_type' => $product->product_type, 'price' => $product->price,
            'stock' => $product->stock, 'low_stock_threshold' => $product->low_stock_threshold,
            'availability' => $product->availability, 'minimum_quantity' => 1,
            'status' => 'archived', 'visibility' => $product->visibility,
        ])->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'archived']);
    }

    public function test_non_admin_cannot_manage_catalog(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/products')->assertForbidden();
        $this->actingAs($user)->post('/admin/categories', [])->assertForbidden();
    }
}
