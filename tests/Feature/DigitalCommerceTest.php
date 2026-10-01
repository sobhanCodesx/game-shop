<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\ProductType;
use App\Models\Role;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\TelegramBotSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config()->set('media.disk', 'downloads');
        config()->set('product_media.disk', 'downloads');
        config()->set('digital_media.disk', 'downloads');
        config()->set('filesystems.disks.downloads.url', 'https://cdn.test/storage');
        Storage::fake('downloads');
    }

    public function test_public_digital_store_uses_predefined_attributes_as_features_and_filters(): void
    {
        $region = $this->digitalAttribute();
        [$seller, $product] = $this->digitalProduct();

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
                ->where('product.cover_url', 'https://cdn.test/storage/digital-products/test/cover.webp')
                ->has('product.media', 1)
                ->where('product.media.0.url', 'https://cdn.test/storage/digital-products/test/cover.webp')
                ->where('product.media.0.alt', 'کاور تست')
                ->where('product.features.0.name', 'ریجن')
                ->where('product.features.0.value', 'ترکیه')
                ->where('product.seller.id', $seller->id)
                ->where('product.seller.name', $seller->name)
                ->where('product.game.channel_url', '/channels/'.$product->game->slug)
                ->where('product.game.digital_products_url', '/digital?game='.$product->game->slug)
                ->has('product.offers', 4)
                ->where('product.offers.0.price', 1_000_000)
                ->missing('product.offers.0.supplier_cost'));

        $this->get('/digital?game='.$product->game->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->where('products.total', 1)
                ->where('products.data.0.id', $product->id)
                ->where('selectedGame.slug', $product->game->slug));

        $this->get('/channels/'.$product->game->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Channels/Show')
                ->where('productsCount', 1)
                ->where('digitalProductsCount', 1)
                ->where('products.0.id', $product->id)
                ->where('products.0.url', '/digital/'.$product->slug));
    }

    public function test_published_digital_product_is_visible_in_shop_and_category_even_when_out_of_stock(): void
    {
        $category = Category::query()->create([
            'name' => 'بازی دیجیتال',
            'slug' => 'digital-games',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $region = $this->digitalAttribute();
        [, $product] = $this->digitalProduct('catalog-digital');

        $product->update(['category_id' => $category->id]);
        $product->offers()->update([
            'stock' => 0,
            'reserved_stock' => 0,
        ]);
        $product->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'turkey',
        ]);

        $this->get('/digital')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->has('products.data', 1)
                ->where('products.data.0.id', $product->id)
                ->where('categories.0.slug', 'digital-games'));

        $this->get('/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shop/Index')
                ->where('products.total', 1)
                ->where('products.data.0.id', $product->id)
                ->where('products.data.0.url', '/digital/'.$product->slug)
                ->where('products.data.0.badge', 'دیجیتال')
                ->where('products.data.0.category', 'بازی دیجیتال'));

        [, $other] = $this->digitalProduct('catalog-digital-usa');
        $other->update(['category_id' => $category->id]);
        $other->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'usa',
        ]);

        $this->get('/categories/'.$category->slug.'?filters[region][]=turkey')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories/Show')
                ->where('products.total', 1)
                ->where('products.data.0.id', $product->id)
                ->where('catalogFilters.0.slug', 'region')
                ->where('catalogFilters.0.options.0.value', 'turkey')
                ->where('selectedAttributeFilters.region.0', 'turkey'));

        $this->get('/categories/'.$category->slug.'?filters[region][]=usa')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Categories/Show')
                ->where('products.total', 1)
                ->where('products.data.0.id', $other->id)
                ->where('selectedAttributeFilters.region.0', 'usa'));
    }

    public function test_digital_seller_creates_product_with_media_features_and_only_sale_price(): void
    {
        config()->set('digital_media.disk', 'downloads');
        Storage::fake('downloads');

        $seller = User::factory()->create([
            'is_admin' => true,
            'role' => 'digital-seller',
            'status' => 'active',
        ]);
        $seller->roles()->sync([Role::query()->where('slug', 'digital-seller')->firstOrFail()->id]);

        $game = Game::factory()->create();
        $platform = Platform::factory()->create();
        $category = Category::query()->create([
            'name' => 'اکانت بازی',
            'slug' => 'game-accounts',
            'status' => 'active',
            'sort_order' => 1,
        ]);
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
                ->has('categories', 1)
                ->where('categories.0.id', $category->id)
                ->has('attributes', 1)
                ->where('attributes.0.slug', 'region'));

        $response = $this->actingAs($seller)->post('/admin/digital-products', [
            'category_id' => $category->id,
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
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($seller->id, $product->seller_id);
        $this->assertCount(1, $product->media);
        $this->assertCount(1, $product->attributeValues);
        $this->assertSame('turkey', $product->attributeValues()->value('value'));
        $this->assertSame(1_000_000, $product->offers()->where('code', 'capacity_1')->value('price'));
        $this->assertFalse(Schema::hasColumn('digital_offers', 'supplier_cost'));

        $cover = $product->media()->firstOrFail();
        $this->assertStringNotContainsString('/', $cover->path);
        Storage::disk('downloads')->assertExists($cover->path);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.id', $product->id)
                ->where('product.cover_url', 'https://cdn.test/storage/'.$cover->path)
                ->where('product.media.0.url', 'https://cdn.test/storage/'.$cover->path));
    }

    public function test_digital_product_can_be_created_without_a_linked_game(): void
    {
        config()->set('digital_media.disk', 'downloads');
        Storage::fake('downloads');

        [$seller, $existing] = $this->digitalProduct('optional-game-source');
        $platform = $existing->platform()->firstOrFail();

        $response = $this->actingAs($seller)->post('/admin/digital-products', [
            'game_id' => '',
            'platform_id' => $platform->id,
            'title' => 'Standalone Digital Account',
            'short_description' => 'محصول دیجیتال بدون اتصال اجباری به بازی',
            'support_days' => 7,
            'status' => 'published',
            'featured' => false,
            'offers' => [
                ['code' => 'capacity_1', 'label' => 'ظرفیت ۱', 'price' => 1_000_000, 'stock' => 1, 'status' => 'active'],
                ['code' => 'capacity_2', 'label' => 'ظرفیت ۲', 'price' => 2_000_000, 'stock' => 1, 'status' => 'active'],
                ['code' => 'capacity_3', 'label' => 'ظرفیت ۳', 'price' => 900_000, 'stock' => 1, 'status' => 'active'],
                ['code' => 'full', 'label' => 'فول ظرفیت', 'price' => 3_000_000, 'stock' => 1, 'status' => 'active'],
            ],
            'attribute_values' => [],
            'media' => [[
                'type' => 'image',
                'file' => UploadedFile::fake()->image('standalone.jpg', 1200, 800),
                'alt' => 'کاور محصول مستقل',
                'is_primary' => true,
            ]],
        ]);

        $response->assertRedirect('/admin/digital-products');

        $product = DigitalProduct::query()
            ->where('title', 'Standalone Digital Account')
            ->firstOrFail();

        $this->assertNull($product->game_id);
        $this->assertSame($platform->id, $product->platform_id);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.id', $product->id)
                ->where('product.game', null)
                ->where('product.platform.id', $platform->id));
    }

    public function test_unlinked_digital_product_requires_an_explicit_title(): void
    {
        [$seller, $existing] = $this->digitalProduct('optional-game-title');
        $platform = $existing->platform()->firstOrFail();

        $this->actingAs($seller)
            ->from('/admin/digital-products/create')
            ->post('/admin/digital-products', [
                'game_id' => '',
                'platform_id' => $platform->id,
                'title' => '',
                'support_days' => 7,
                'status' => 'published',
                'featured' => false,
                'offers' => [
                    ['code' => 'capacity_1', 'label' => 'ظرفیت ۱', 'price' => 1_000_000, 'stock' => 1, 'status' => 'active'],
                    ['code' => 'capacity_2', 'label' => 'ظرفیت ۲', 'price' => 2_000_000, 'stock' => 1, 'status' => 'active'],
                    ['code' => 'capacity_3', 'label' => 'ظرفیت ۳', 'price' => 900_000, 'stock' => 1, 'status' => 'active'],
                    ['code' => 'full', 'label' => 'فول ظرفیت', 'price' => 3_000_000, 'stock' => 1, 'status' => 'active'],
                ],
                'attribute_values' => [],
                'media' => [[
                    'type' => 'image',
                    'file' => UploadedFile::fake()->image('standalone-title.jpg', 1200, 800),
                    'is_primary' => true,
                ]],
            ])
            ->assertRedirect('/admin/digital-products/create')
            ->assertSessionHasErrors('title');
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
                ->where('product.media.0.alt', 'کاور محصول دیجیتال')
                ->where('product.media.0.url', 'https://cdn.test/storage/digital-products/admin-edit/cover.webp'));

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

    public function test_admin_game_picker_searches_all_non_deleted_games_with_pagination(): void
    {
        [$seller] = $this->digitalProduct('picker-access');

        foreach (range(1, 31) as $index) {
            Game::factory()->create([
                'name' => sprintf('Catalog Game %02d', $index),
                'slug' => 'catalog-game-'.$index,
                'status' => $index === 31 ? 'draft' : 'active',
            ]);
        }

        Game::factory()->create([
            'name' => 'Hidden Search Needle',
            'slug' => 'hidden-search-needle',
            'status' => 'draft',
        ]);

        $this->actingAs($seller)
            ->getJson('/admin/digital-products/game-options?page=1&per_page=25')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(25, 'data');

        $this->actingAs($seller)
            ->getJson('/admin/digital-products/game-options?q=Needle')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Hidden Search Needle')
            ->assertJsonPath('data.0.status', 'draft');
    }

    public function test_digital_product_page_links_similar_products_and_game_content(): void
    {
        $region = $this->digitalAttribute();
        $category = Category::query()->create([
            'name' => 'اکانت ظرفیتی',
            'slug' => 'capacity-accounts-related',
            'status' => 'active',
            'sort_order' => 1,
        ]);

        [, $product] = $this->digitalProduct('related-main-game');
        $product->update(['category_id' => $category->id]);
        $product->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'turkey',
        ]);

        [, $related] = $this->digitalProduct('related-other-game');
        $related->update(['category_id' => $category->id]);
        $related->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'turkey',
        ]);

        $video = SocialContent::query()->create([
            'user_id' => $related->seller_id,
            'game_id' => $product->game_id,
            'type' => 'video',
            'title' => 'ویدیوی مرتبط تست',
            'slug' => 'related-video-test',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
            'views' => 12,
        ]);

        $feed = SocialContent::query()->create([
            'user_id' => $related->seller_id,
            'game_id' => $product->game_id,
            'type' => 'post',
            'title' => 'فید مرتبط تست',
            'slug' => 'related-feed-test',
            'status' => 'published',
            'published_at' => now(),
            'allow_comments' => true,
            'views' => 4,
        ]);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('relatedProducts.0.id', $related->id)
                ->where('relatedProducts.0.url', '/digital/'.$related->slug)
                ->where('gameVideos.0.id', $video->id)
                ->where('gameVideos.0.url', '/videos/'.$video->slug)
                ->where('gameFeed.0.id', $feed->id)
                ->where('gameFeed.0.url', '/posts/'.$feed->slug));
    }

    public function test_editing_digital_product_with_new_image_upload_succeeds(): void
    {
        config()->set('media.disk', 'broken-legacy-ftp');
        config()->set('digital_media.disk', 'downloads');
        Storage::fake('downloads');

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
        $this->assertStringNotContainsString(
            '/',
            $product->media()->firstOrFail()->path,
        );
        Storage::disk('downloads')
            ->assertExists($product->media()->firstOrFail()->path);

        $newPath = $product->media()->firstOrFail()->path;
        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.id', $product->id)
                ->where('product.cover_url', 'https://cdn.test/storage/'.$newPath)
                ->where('product.media.0.url', 'https://cdn.test/storage/'.$newPath));
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

    public function test_digital_product_builds_a_semantic_internal_link_hub(): void
    {
        $region = $this->digitalAttribute();
        [$seller, $product] = $this->digitalProduct('linked-channel-game');
        $game = $product->game()->firstOrFail();
        $platform = $product->platform()->firstOrFail();

        $category = Category::query()->create([
            'name' => 'اکانت ظرفیتی',
            'slug' => 'capacity-link-hub',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $product->update(['category_id' => $category->id]);
        $product->attributeValues()->create([
            'attribute_id' => $region->id,
            'value' => 'turkey',
        ]);

        $studio = Studio::query()->create([
            'name' => 'Linked Studio',
            'slug' => 'linked-studio',
            'status' => 'active',
        ]);
        $game->update(['studio_id' => $studio->id]);

        $sameGame = DigitalProduct::query()->create([
            'category_id' => $category->id,
            'game_id' => $game->id,
            'platform_id' => $platform->id,
            'seller_id' => $seller->id,
            'title' => 'Same Game Alternate Account',
            'slug' => 'same-game-alternate-account',
            'short_description' => 'نسخه دیگر همین بازی',
            'support_days' => 7,
            'status' => 'published',
        ]);

        foreach ($product->offers as $offer) {
            $sameGame->offers()->create([
                'code' => $offer->code,
                'label' => $offer->label,
                'price' => $offer->price,
                'stock' => 2,
                'reserved_stock' => 0,
                'status' => 'active',
                'sort_order' => $offer->sort_order,
            ]);
        }

        [, $related] = $this->digitalProduct('related-link-hub-game');
        $related->update(['category_id' => $category->id]);

        $playlist = VideoPlaylist::query()->create([
            'game_id' => $game->id,
            'title' => 'راهنمای کامل بازی',
            'slug' => 'complete-game-guide',
            'visibility' => 'public',
            'sort_order' => 1,
        ]);

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.game.id', $game->id)
                ->where('product.game.channel_url', '/channels/'.$game->slug)
                ->where('product.game.digital_products_url', '/digital?game='.$game->slug)
                ->where('product.game.feed_url', '/channels/'.$game->slug.'#feed')
                ->where('product.game.videos_url', '/channels/'.$game->slug.'#videos')
                ->where('product.game.playlists_url', '/channels/'.$game->slug.'#playlists')
                ->where('product.game.products_url', '/channels/'.$game->slug.'#products')
                ->where('product.game.studio.id', $studio->id)
                ->where('product.game.studio.url', '/studios/'.$studio->slug)
                ->where('product.category.url', '/categories/'.$category->slug)
                ->where('product.category.digital_url', '/digital?category='.$category->slug)
                ->where('product.platform.digital_products_url', '/digital?platform='.$platform->slug)
                ->where(
                    'product.features.0.filter_url',
                    fn ($value) => is_string($value)
                        && str_contains($value, 'filters')
                        && str_contains($value, 'region')
                        && str_contains($value, 'turkey'),
                )
                ->where('sameGameProducts.0.id', $sameGame->id)
                ->where('sameGameProducts.0.url', '/digital/'.$sameGame->slug)
                ->where('relatedProducts.0.id', $related->id)
                ->where('gamePlaylists.0.id', $playlist->id)
                ->where(
                    'gamePlaylists.0.url',
                    '/channels/'.$game->slug.'/playlists/'.$playlist->slug,
                )
                ->where('seo.canonical', route('digital.show', $product)));

        $this->get('/digital?game='.$game->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->where('selectedGame.id', $game->id)
                ->where('selectedGame.slug', $game->slug)
                ->where('products.total', 2));

        $this->get('/digital?platform='.$platform->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->where('selectedPlatform.id', $platform->id)
                ->where('selectedPlatform.slug', $platform->slug)
                ->where('products.total', 2));

        $this->get('/channels/'.$game->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Channels/Show')
                ->where('channel.id', $game->id)
                ->where('digitalProductsCount', 2)
                ->where('productsCount', 2)
                ->where('products.0.badge', 'دیجیتال'));
    }

    public function test_digital_price_ticket_migration_can_run_again_safely(): void
    {
        $this->assertTrue(Schema::hasColumn('tickets', 'digital_product_id'));
        $this->assertTrue(Schema::hasColumn('tickets', 'assigned_user_id'));

        $migration = require database_path(
            'migrations/2026_10_01_041500_add_digital_price_inquiries_to_tickets.php',
        );
        $migration->up();

        $this->assertTrue(Schema::hasColumn('tickets', 'digital_product_id'));
        $this->assertTrue(Schema::hasColumn('tickets', 'assigned_user_id'));

        $indexNames = collect(Schema::getIndexes('tickets'))->pluck('name');
        $this->assertTrue(
            $indexNames->contains('tickets_type_assignee_status_index'),
        );
    }

    public function test_latest_price_inquiry_targets_only_the_linked_product_seller(): void
    {
        [$seller, $product] = $this->digitalProduct('latest-price-inquiry');
        $seller->forceFill([
            'telegram_user_id' => '555555555',
            'telegram_chat_id' => '555555555',
            'telegram_linked_at' => now(),
        ])->save();

        TelegramBotSetting::query()->create([
            'bot_token' => '123456:test-token',
            'admin_user_id' => '777777777',
            'enabled' => true,
            'write_enabled' => true,
            'publish_enabled' => true,
            'destructive_enabled' => false,
            'media_enabled' => true,
            'transport_mode' => 'direct',
            'api_base_url' => 'https://api.telegram.org',
            'bot_username' => 'playnexus_admin_bot',
            'webhook_secret' => 'webhook-secret',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => true,
            ]),
        ]);

        $customer = User::factory()->create(['status' => 'active']);

        $this->actingAs($customer)
            ->post('/digital/'.$product->slug.'/price-inquiry')
            ->assertRedirect();

        $ticket = Ticket::query()
            ->where('user_id', $customer->id)
            ->where('digital_product_id', $product->id)
            ->firstOrFail();

        $this->assertSame('digital_price', $ticket->type);
        $this->assertSame($seller->id, $ticket->assigned_user_id);
        $this->assertSame('pending', $ticket->status);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'is_admin' => false,
        ]);

        Http::assertSent(function ($request) use ($ticket, $product): bool {
            $button = $request->data()['reply_markup']['inline_keyboard'][0][0] ?? [];

            return str_ends_with($request->url(), '/sendMessage')
                && ($request->data()['chat_id'] ?? null) === '555555555'
                && str_contains((string) ($request->data()['text'] ?? ''), $product->title)
                && ($button['callback_data'] ?? null) === 'seller_ticket_reply:'.$ticket->id;
        });

        $this->actingAs($customer)
            ->post('/digital/'.$product->slug.'/price-inquiry')
            ->assertRedirect(route('account.tickets.show', $ticket));

        $this->assertSame(
            1,
            Ticket::query()
                ->where('user_id', $customer->id)
                ->where('digital_product_id', $product->id)
                ->whereIn('status', ['pending', 'open'])
                ->count(),
        );
        Http::assertSentCount(1);
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
