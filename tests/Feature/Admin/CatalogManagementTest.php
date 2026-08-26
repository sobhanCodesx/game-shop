<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Platform;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_search_paginated_catalog(): void
    {
        Product::factory()->create(['title' => 'PlayStation Special']);
        Product::factory()->create(['title' => 'Xbox Controller']);

        $this->actingAs($this->admin)
            ->get('/admin/products?search=PlayStation')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Resource/Index')
                ->has('items', 1)
                ->where('pagination.total', 1));
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
            'attribute_values' => [$attribute->id => 'اروپا'],
            'minimum_quantity' => 1,
            'status' => 'published',
            'visibility' => 'public',
            'delivery_method' => 'manual',
            'tags' => ['PS5', 'اکشن'],
        ])->assertRedirect('/admin/products');

        $product = Product::where('sku', 'GOW-PS5-001')->firstOrFail();

        $this->assertTrue($product->platforms()->whereKey($platform)->exists());
        $this->assertSame(4_100_000, $product->partner_price);
        $this->assertSame(['PS5', 'اکشن'], $product->tags);
        $this->assertSame('2026-09-10', $product->release_date->format('Y-m-d'));
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
                ->has('options.platforms'));
    }

    public function test_price_change_is_recorded(): void
    {
        $product = Product::factory()->create(['price' => 1_000_000]);

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

    public function test_non_admin_cannot_manage_catalog(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/products')->assertForbidden();
        $this->actingAs($user)->post('/admin/categories', [])->assertForbidden();
    }
}
