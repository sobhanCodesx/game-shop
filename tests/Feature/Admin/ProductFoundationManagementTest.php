<?php

namespace Tests\Feature\Admin;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductFoundationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_dynamic_attribute_with_options(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/attributes', [
            'title' => 'ریجن', 'slug' => 'region', 'input_type' => 'select',
            'is_filterable' => true, 'is_usable_for_variant' => true,
            'is_visible_on_product' => true, 'status' => 'active', 'sort_order' => 1,
            'options' => [
                ['title' => 'اروپا', 'value' => 'eu', 'status' => 'active', 'sort_order' => 1],
                ['title' => 'آمریکا', 'value' => 'us', 'status' => 'active', 'sort_order' => 2],
            ],
        ])->assertRedirect('/admin/attributes');

        $attribute = Attribute::query()->where('slug', 'region')->firstOrFail();
        $this->assertCount(2, $attribute->options);
        $this->assertTrue($attribute->is_usable_for_variant);
    }

    public function test_admin_can_create_product_type_and_assign_attributes(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $attribute = Attribute::query()->create([
            'title' => 'ظرفیت', 'slug' => 'capacity', 'input_type' => 'select',
            'is_usable_for_variant' => true, 'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/admin/product-types', [
            'title' => 'اکانت سفارشی', 'slug' => 'custom_account',
            'inventory_type' => 'digital', 'supports_variants' => true,
            'supports_digital_delivery' => true, 'supports_digital_inventory' => true,
            'requires_cover' => true, 'status' => 'active', 'sort_order' => 1,
            'attribute_ids' => [$attribute->id],
        ])->assertRedirect('/admin/product-types');

        $type = ProductType::query()->where('slug', 'custom_account')->firstOrFail();
        $this->assertTrue($type->attributes()->whereKey($attribute)->exists());
    }

    public function test_dynamic_product_type_is_accepted_by_product_validation(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $type = ProductType::query()->create([
            'title' => 'خدمت سفارشی', 'slug' => 'custom_service',
            'inventory_type' => 'none', 'status' => 'active',
        ]);

        $this->actingAs($admin)->post('/admin/products', [
            'title' => 'نصب و راه‌اندازی', 'slug' => 'custom-installation', 'sku' => 'SERVICE-1',
            'product_type' => 'custom_service', 'price' => 500_000, 'stock' => 0,
            'low_stock_threshold' => 0, 'availability' => 'in_stock',
            'minimum_quantity' => 1, 'status' => 'draft', 'visibility' => 'public',
            'media' => [[
                'file' => UploadedFile::fake()->image('service-cover.jpg'),
                'is_primary' => true,
            ]],
        ])->assertRedirect('/admin/products');

        $product = Product::query()->where('sku', 'SERVICE-1')->firstOrFail();
        $this->assertSame($type->id, $product->product_type_id);
    }

    public function test_regular_user_cannot_manage_product_foundation(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post('/admin/product-types', [])->assertForbidden();
        $this->actingAs($user)->post('/admin/attributes', [])->assertForbidden();
    }
}
