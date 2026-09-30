<?php

namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Game;
use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DigitalCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_digital_store_is_separate_and_does_not_expose_supplier_cost(): void
    {
        [$seller, $product] = $this->digitalProduct();

        $this->get('/digital')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Index')
                ->has('products.data', 1)
                ->where('products.data.0.title', $product->title)
                ->missing('products.data.0.offers.0.supplier_cost'));

        $this->get('/digital/'.$product->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Digital/Show')
                ->where('product.id', $product->id)
                ->has('product.offers', 4)
                ->missing('product.offers.0.supplier_cost'));
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
            ['capacity_1', 'ظرفیت ۱', 800_000, 1_000_000],
            ['capacity_2', 'ظرفیت ۲', 1_700_000, 2_000_000],
            ['capacity_3', 'ظرفیت ۳', 600_000, 750_000],
            ['full', 'فول ظرفیت', 2_500_000, 3_000_000],
        ] as $index => [$code, $label, $cost, $price]) {
            $product->offers()->create([
                'code' => $code,
                'label' => $label,
                'supplier_cost' => $cost,
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
