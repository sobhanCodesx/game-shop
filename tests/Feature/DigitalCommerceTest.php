<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_digital_store_uses_predefined_attributes_as_features_and_filters(): void
    {
        $region = $this->digitalAttribute();
        [, $product] = $this->digitalProduct();

        $product->media()->create([
            'type' => 'image',
            'path' => 'digital-products/test/cover.webp',
            'alt' => 'کاور تست',
            'sort_order' => 1,
            'is_primary' => true,
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'turkey',
        ]);

        [, $other] = $this->digitalProduct('other-region-game');
        $other->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'usa',
        ]);

        $this->get('/digital')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->has('products.data', 2)
                ->where('filters.0.title', 'ریجن')
                ->where('filters.0.slug', 'region')
                ->where('filters.0.options.0.value', 'turkey'));

        $this->get('/digital?filters[region][]=turkey')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id)
                ->where('selectedFilters.region.0', 'turkey'));

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.id', $product->id)
                ->has('product.media', 1)
                ->where('product.media.0.alt', 'کاور تست')
                ->where('product.features.0.name', 'ریجن')
                ->where('product.features.0.value', 'ترکیه')
                ->has('product.offers', 4)
                ->where('product.offers.0.price', 1_000_000)
                ->missing('product.offers.0.supplier_cost'));
    }

    public function test_digital_seller_creates_product_with_media_features_and_only_sale_price(): void
    {
        config()->set('digital_media.disk', 'public');
        Storage::fake('public');

        $seller = User::factory()->create([
            'is_admin' => true,
            'role' => 'digital-seller',
            'status' => 'active',
        ]);
        $seller->roles()->sync([Role::query()->where('slug', 'digital-seller')->firstOrFail()->id]);

        $game = Game::factory()->create();
        $platform = Platform::factory()->create();
        $region = $this->digitalAttribute();

        Attribute::query()->firstOrCreate(
            ['slug' => 'capacity'],
            [
                'title' => 'ظرفیت',
                'input_type' => 'select',
                'is_required' => true,
                'is_filterable' => true,
                'is_searchable' => false,
                'is_visible_on_product' => true,
                'is_usable_for_variant' => true,
                'status' => 'active',
                'sort_order' => 0,
            ],
        );

        $this->actingAs($seller)
            ->get('/admin/digital-products/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Digital/Products/Form')
                ->has('attributes', 1)
                ->where('attributes.0.slug', 'region'));

        $response = $this->actingAs($seller)->post('/admin/digital-products', [
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'title' => 'Digital Test',
            'short_description' => 'توضیح محصول',
            'support_days' => 7,
            'status' => 'published',
            'featured' => false,
            'offers' => [
                ['code' => 'capacity_1', 'label' => 'ظرفیت ۱', 'price' => 1_000_000, 'stock' => 2, 'status' => 'active'],
                ['code' => 'capacity_2', 'label' => 'ظرفیت ۲', 'price' => 2_000_000, 'stock' => 2, 'status' => 'active'],
                ['code' => 'capacity_3', 'label' => 'ظرفیت ۳', 'price' => 800_000, 'stock' => 2, 'status' => 'active'],
                ['code' => 'full', 'label' => 'فول ظرفیت', 'price' => 3_000_000, 'stock' => 1, 'status' => 'active'],
            ],
            'attribute_values' => [
                (string) $region->id => ['turkey'],
            ],
            'media' => [
                [
                    'type' => 'image',
                    'file' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
                    'alt' => 'کاور محصول',
                    'is_primary' => true,
                ],
            ],
        ]);

        $response->assertRedirect('/admin/digital-products');

        $product = DigitalProduct::query()->where('title', 'Digital Test')->firstOrFail();
        $this->assertSame($seller->id, $product->seller_id);
        $this->assertCount(1, $product->media);
        $this->assertCount(1, $product->attributeValues);
        $this->assertSame('turkey', $product->attributeValues()->value('value'));
        $this->assertSame(1_000_000, $product->offers()->where('code', 'capacity_1')->value('price'));
        $this->assertFalse(Schema::hasColumn('digital_offers', 'supplier_cost'));
    }

    public function test_admin_digital_product_edit_uses_id_binding_and_listing_is_paginated(): void
    {
        [$seller, $product] = $this->digitalProduct('admin-edit');

        $product->media()->create([
            'type' => 'image',
            'path' => 'digital-products/admin-edit/cover.webp',
            'alt' => 'کاور محصول دیجیتال',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        foreach (range(1, 12) as $index) {
            DigitalProduct::query()->create([
                'game_id' => $product->game_id,
                'platform_id' => $product->platform_id,
                'seller_id' => $seller->id,
                'title' => 'Digital Product '.$index,
                'slug' => 'digital-product-'.$index,
                'short_description' => 'محصول صفحه‌بندی',
                'support_days' => 7,
                'status' => 'draft',
            ]);
        }

        $this->actingAs($seller)
            ->get('/admin/digital-products/'.$product->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Digital/Products/Form')
                ->where('product.id', $product->id)
                ->where('product.title', $product->title)
                ->where('product.media.0.alt', 'کاور محصول دیجیتال'));

        $this->actingAs($seller)
            ->get('/admin/digital-products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Digital/Products/Index')
                ->where('products.per_page', 12)
                ->where('products.total', 13)
                ->where('products.last_page', 2)
                ->has('products.data', 12)
                ->has('products.links'));

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.id', $product->id));
    }

    public function test_editing_digital_product_with_new_image_upload_succeeds(): void
    {
        config()->set('media.disk', 'broken-legacy-ftp');
        config()->set('digital_media.disk', 'public');
        Storage::fake('public');

        [$seller, $product] = $this->digitalProduct('media-edit');
        $product->media()->create([
            'type' => 'image',
            'path' => 'digital-products/media-edit/old-cover.webp',
            'alt' => 'کاور قبلی',
            'sort_order' => 1,
            'is_primary' => true,
        ]);

        $offers = $product->offers->map(fn ($offer) => [
            'code' => $offer->code,
            'label' => $offer->label,
            'price' => $offer->price,
            'stock' => $offer->stock,
            'status' => $offer->status,
        ])->values()->all();

        $response = $this->actingAs($seller)->post(
            '/admin/digital-products/'.$product->id,
            [
                '_method' => 'put',
                'game_id' => $product->game_id,
                'platform_id' => $product->platform_id,
                'title' => $product->title,
                'short_description' => 'ویرایش همراه با تصویر جدید',
                'support_days' => 7,
                'status' => 'published',
                'featured' => false,
                'offers' => $offers,
                'attribute_values' => [],
                'media' => [
                    [
                        'type' => 'image',
                        'file' => UploadedFile::fake()->image('new-cover.jpg', 1200, 800),
                        'alt' => 'کاور جدید',
                        'is_primary' => true,
                    ],
                ],
            ],
        );

        $response->assertRedirect('/admin/digital-products');

        $product->refresh();
        $this->assertSame('ویرایش همراه با تصویر جدید', $product->short_description);
        $this->assertCount(1, $product->media);
        $this->assertSame('کاور جدید', $product->media()->firstOrFail()->alt);
        $this->assertTrue((bool) $product->media()->firstOrFail()->is_primary);
        $this->assertStringStartsWith(
            'digital-products/',
            $product->media()->firstOrFail()->path,
        );
        Storage::disk('public')
            ->assertExists($product->media()->firstOrFail()->path);
    }

    public function test_customer_creates_digital_order_and_stock_is_reserved_not_sold(): void
    {
        [, $product] = $this->digitalProduct();
        $customer = User::factory()->create();
        $offer = $product->offers()->where('code', 'capacity_2')->firstOrFail();

        $this->actingAs($customer)
            ->post('/digital/'.$product->slug.'/orders', ['offer_id' => $offer->id])
            ->assertRedirect();

        $order = $customer->digitalOrders()->first();
        $this->assertNotNull($order);
        $this->assertSame('new', $order->order_status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame(2_000_000, $order->sale_price);

        $offer->refresh();
        $this->assertSame(5, $offer->stock);
        $this->assertSame(1, $offer->reserved_stock);
    }

    public function test_digital_seller_can_only_access_own_digital_orders_and_not_physical_orders(): void
    {
        [$seller, $product] = $this->digitalProduct();
        [$otherSeller, $otherProduct] = $this->digitalProduct('other-game');
        $customer = User::factory()->create();

        $own = app(\App\Services\DigitalOrderService::class)
            ->create($customer, $product->offers()->first());
        $other = app(\App\Services\DigitalOrderService::class)
            ->create($customer, $otherProduct->offers()->first());

        $this->actingAs($seller)->get('/admin/digital-orders')->assertOk();
        $this->actingAs($seller)->get('/admin/digital-orders/'.$own->id)->assertOk();
        $this->actingAs($seller)->get('/admin/digital-orders/'.$other->id)->assertNotFound();

        $this->actingAs($seller)->get('/admin/orders')->assertForbidden();
        $this->assertNotSame($seller->id, $otherSeller->id);
    }

    public function test_receipt_payment_delivery_and_completion_flow_keeps_credentials_encrypted(): void
    {
        Storage::fake('local');

        [$seller, $product] = $this->digitalProduct();
        $customer = User::factory()->create();
        $offer = $product->offers()->where('code', 'full')->firstOrFail();
        $order = app(\App\Services\DigitalOrderService::class)->create($customer, $offer);

        $this->actingAs($customer)
            ->post('/account/digital-orders/'.$order->id.'/receipt', [
                'receipt' => UploadedFile::fake()->image('receipt.jpg'),
                'message' => 'پرداخت کردم',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame('receipt_sent', $order->payment_status);
        $this->assertDatabaseHas('digital_order_messages', [
            'digital_order_id' => $order->id,
            'type' => 'payment_receipt',
        ]);

        $this->actingAs($seller)
            ->patch('/admin/digital-orders/'.$order->id.'/payment')
            ->assertRedirect();

        $this->actingAs($seller)
            ->post('/admin/digital-orders/'.$order->id.'/delivery', [
                'login' => 'buyer@example.com',
                'password' => 'super-secret-pass',
                'backup_code' => 'backup-123',
                'instructions' => 'ابتدا طبق راهنما وارد حساب شوید.',
            ])
            ->assertRedirect();

        $rawPassword = DB::table('digital_deliveries')
            ->where('digital_order_id', $order->id)
            ->value('password');

        $this->assertNotSame('super-secret-pass', $rawPassword);
        $this->assertSame('super-secret-pass', $order->fresh()->delivery->password);

        $this->actingAs($customer)
            ->get('/account/digital-orders/'.$order->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Orders/Show')
                ->where('order.delivery.login', 'buyer@example.com')
                ->where('order.delivery.password', 'super-secret-pass'));

        $this->actingAs($customer)
            ->patch('/account/digital-orders/'.$order->id.'/confirm')
            ->assertRedirect();

        $order->refresh();
        $offer->refresh();
        $this->assertSame('completed', $order->order_status);
        $this->assertSame('resolved', $order->delivery_status);
        $this->assertSame(4, $offer->stock);
        $this->assertSame(0, $offer->reserved_stock);
    }


    public function test_paid_order_cannot_be_cancelled_and_keeps_reserved_stock(): void
    {
        [$seller, $product] = $this->digitalProduct('paid-cancel');
        $customer = User::factory()->create();
        $offer = $product->offers()->firstOrFail();
        $order = app(\App\Services\DigitalOrderService::class)->create($customer, $offer);

        app(\App\Services\DigitalOrderService::class)->markPaid($order, $seller);

        $this->actingAs($seller)
            ->patch('/admin/digital-orders/'.$order->id.'/cancel')
            ->assertSessionHasErrors('status');

        $order->refresh();
        $offer->refresh();
        $this->assertSame('active', $order->order_status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(1, $offer->reserved_stock);
    }

    public function test_cancelled_order_cannot_be_completed_even_if_delivery_state_is_tampered(): void
    {
        [, $product] = $this->digitalProduct('cancel-complete');
        $customer = User::factory()->create();
        $offer = $product->offers()->firstOrFail();
        $service = app(\App\Services\DigitalOrderService::class);
        $order = $service->create($customer, $offer);

        $service->cancel($order, $customer);
        $order->forceFill([
            'payment_status' => 'paid',
            'delivery_status' => 'delivered',
        ])->save();

        $this->actingAs($customer)
            ->patch('/account/digital-orders/'.$order->id.'/confirm')
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $order->fresh()->order_status);
        $this->assertSame(5, $offer->fresh()->stock);
        $this->assertSame(0, $offer->fresh()->reserved_stock);
    }

    private function digitalAttribute(): Attribute
    {
        $type = ProductType::query()->firstOrCreate(
            ['slug' => 'capacity_account'],
            [
                'title' => 'اکانت ظرفیتی',
                'inventory_type' => 'digital',
                'supports_variants' => true,
                'supports_shipping' => false,
                'supports_exchange' => false,
                'supports_digital_delivery' => true,
                'supports_digital_inventory' => true,
                'requires_cover' => true,
                'status' => 'active',
                'sort_order' => 0,
            ],
        );

        $attribute = Attribute::query()->firstOrCreate(
            ['slug' => 'region'],
            [
                'title' => 'ریجن',
                'input_type' => 'select',
                'is_required' => false,
                'is_filterable' => true,
                'is_searchable' => true,
                'is_visible_on_product' => true,
                'is_usable_for_variant' => false,
                'status' => 'active',
                'sort_order' => 0,
            ],
        );

        if (! $attribute->options()->exists()) {
            $attribute->options()->createMany([
                ['title' => 'ترکیه', 'value' => 'turkey', 'status' => 'active', 'sort_order' => 1],
                ['title' => 'آمریکا', 'value' => 'usa', 'status' => 'active', 'sort_order' => 2],
            ]);
        }

        $type->attributes()->syncWithoutDetaching([
            $attribute->id => ['is_required' => false, 'sort_order' => 1],
        ]);

        return $attribute->fresh('options');
    }

    private function digitalProduct(string $slug = 'test-game'): array
    {
        $seller = User::factory()->create([
            'name' => 'Digital Seller '.fake()->unique()->randomNumber(),
            'is_admin' => true,
            'role' => 'digital-seller',
            'status' => 'active',
        ]);
        $seller->roles()->sync([Role::query()->where('slug', 'digital-seller')->firstOrFail()->id]);

        $game = Game::factory()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
        ]);
        $platform = Platform::factory()->create([
            'name' => 'PS5 '.fake()->unique()->randomNumber(),
            'slug' => 'ps5-'.fake()->unique()->randomNumber(),
        ]);

        $product = DigitalProduct::query()->create([
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'seller_id' => $seller->id,
            'title' => $game->name.' - '.$platform->name,
            'slug' => $slug.'-digital-'.fake()->unique()->randomNumber(),
            'short_description' => 'محصول تست دیجیتال',
            'support_days' => 7,
            'status' => 'published',
        ]);

        foreach ([
            ['capacity_1', 'ظرفیت ۱', 1_000_000],
            ['capacity_2', 'ظرفیت ۲', 2_000_000],
            ['capacity_3', 'ظرفیت ۳', 750_000],
            ['full', 'فول ظرفیت', 3_000_000],
        ] as $index => [$code, $label, $price]) {
            $product->offers()->create([
                'code' => $code,
                'label' => $label,
                'price' => $price,
                'stock' => 5,
                'reserved_stock' => 0,
                'status' => 'active',
                'sort_order' => $index + 1,
            ]);
        }

        return [$seller, $product->fresh('offers')];
    }
}
