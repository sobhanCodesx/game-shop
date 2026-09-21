<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\HomeSetting;
use App\Models\MobileVerificationCode;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderCashbackNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_calculates_product_coupon_delivery_wallet_and_pending_order(): void
    {
        HomeSetting::query()->create(['content' => ['delivery_fee' => 50_000, 'cashback_percent' => 2]]);
        $user = User::factory()->create(['wallet_balance' => 60_000]);
        $product = Product::factory()->create(['price' => 1_100_000, 'discount_price' => 1_000_000, 'stock' => 5, 'requires_shipping' => true]);
        $coupon = Coupon::query()->create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'minimum_order' => 0, 'per_user_limit' => 1, 'is_active' => true]);

        $response = $this->actingAs($user)->withSession(['cart' => ["{$product->id}:base" => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]])->post(route('checkout.store'), [
            'address_mode' => 'new', 'address' => ['recipient_name' => 'کاربر تست', 'phone' => '09120000000', 'province' => 'تهران', 'city' => 'تهران', 'postal_code' => '1234567890', 'address_line' => 'خیابان تست'],
            'coupon_code' => 'SAVE10', 'use_wallet' => true,
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('pending', $order->status);
        $this->assertSame(100_000, $order->product_discount);
        $this->assertSame(100_000, $order->coupon_discount);
        $this->assertSame(50_000, $order->delivery_fee);
        $this->assertSame(950_000, $order->grand_total);
        $this->assertSame(60_000, $order->wallet_used);
        $this->assertSame(890_000, $order->payable_amount);
        $this->assertSame(18_000, $order->cashback_amount);
        $this->assertSame(0, $user->fresh()->wallet_balance);
        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_admin_approval_credits_cashback_once_and_notifies_user(): void
    {
        Notification::fake();
        $user = User::factory()->create(['wallet_balance' => 0]);
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $order = Order::query()->create(['number' => 'NP-TEST-1', 'user_id' => $user->id, 'shipping_address' => [], 'status' => 'pending', 'regular_subtotal' => 1_000_000, 'product_discount' => 0, 'subtotal' => 1_000_000, 'coupon_discount' => 0, 'delivery_fee' => 0, 'grand_total' => 1_000_000, 'wallet_used' => 0, 'payable_amount' => 1_000_000, 'cashback_percent' => 2, 'cashback_eligible_amount' => 1_000_000, 'cashback_amount' => 20_000]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'approved'])->assertRedirect();
        $this->assertSame(20_000, $user->fresh()->wallet_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Notification::assertSentTo($user, OrderCashbackNotification::class);
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame(20_000, $user->fresh()->wallet_balance);
    }

    public function test_rejection_refunds_wallet_and_stock(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $product = Product::factory()->create(['stock' => 4]);
        $order = Order::query()->create(['number' => 'NP-TEST-2', 'user_id' => $user->id, 'shipping_address' => [], 'status' => 'pending', 'regular_subtotal' => 100_000, 'product_discount' => 0, 'subtotal' => 100_000, 'coupon_discount' => 0, 'delivery_fee' => 0, 'grand_total' => 100_000, 'wallet_used' => 60_000, 'payable_amount' => 40_000, 'cashback_percent' => 2, 'cashback_eligible_amount' => 100_000, 'cashback_amount' => 2_000]);
        $order->items()->create(['product_id' => $product->id, 'title' => $product->title, 'sku' => $product->sku, 'quantity' => 1, 'regular_unit_price' => 100_000, 'unit_price' => 100_000, 'line_total' => 100_000, 'requires_shipping' => true]);
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'rejected'])->assertRedirect();
        $this->assertSame(60_000, $user->fresh()->wallet_balance);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $order->id, 'type' => 'order_refund', 'amount' => 60_000]);
    }

    public function test_checkout_address_validation_messages_are_persian_and_postal_code_is_optional(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 2]);
        $session = ['cart' => ["{$product->id}:base" => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]];

        $this->actingAs($user)->withSession($session)->post(route('checkout.store'), [
            'address_mode' => 'new', 'address' => ['recipient_name' => '', 'phone' => '', 'province' => 'تهران', 'city' => 'تهران', 'address_line' => ''],
        ])->assertSessionHasErrors([
            'address.recipient_name' => 'نام تحویل‌گیرنده الزامی است.',
            'address.phone' => 'شماره تماس تحویل‌گیرنده الزامی است.',
            'address.address_line' => 'نشانی کامل محل تحویل الزامی است.',
        ]);

        $this->actingAs($user)->withSession($session)->post(route('checkout.store'), [
            'address_mode' => 'new', 'address' => ['recipient_name' => 'کاربر', 'phone' => '09120000000', 'province' => 'تهران', 'city' => 'تهران', 'address_line' => 'نشانی بدون کد پستی'],
        ])->assertRedirect();
        $this->assertNull(Order::query()->latest('id')->firstOrFail()->shipping_address['postal_code'] ?? null);
    }

    public function test_saved_address_checkout_ignores_empty_new_address_payload(): void
    {
        $user = User::factory()->create();
        $address = $user->addresses()->create([
            'title' => 'خانه',
            'recipient_name' => 'کاربر تست',
            'phone' => '09120000000',
            'province' => 'تهران',
            'city' => 'تهران',
            'postal_code' => null,
            'address_line' => 'خیابان تست، کوچه یک',
            'plaque' => '12',
            'unit' => null,
            'is_default' => true,
        ]);
        $product = Product::factory()->create(['stock' => 2]);
        $session = ['cart' => ["{$product->id}:base" => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]];

        $response = $this->actingAs($user)->withSession($session)->post(route('checkout.store'), [
            'address_mode' => 'saved',
            'address_id' => $address->id,
            'address' => [
                'recipient_name' => '',
                'phone' => '',
                'province' => 'تهران',
                'city' => 'تهران',
                'postal_code' => '',
                'address_line' => '',
                'plaque' => '',
                'unit' => '',
            ],
        ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('خیابان تست، کوچه یک', $order->shipping_address['address_line']);
        $this->assertSame('09120000000', $order->shipping_address['phone']);
    }

    public function test_pickup_checkout_has_no_delivery_fee_and_snapshots_pickup_address(): void
    {
        $pickupAddress = 'تهران، خیابان تست، مجتمع پلی نکسوس، طبقه اول';
        HomeSetting::query()->create([
            'content' => [
                'delivery_fee' => 75_000,
                'pickup_address' => $pickupAddress,
                'cashback_percent' => 2,
            ],
        ]);
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 500_000,
            'discount_price' => null,
            'stock' => 3,
            'requires_shipping' => true,
        ]);
        $session = [
            'cart' => [
                "{$product->id}:base" => [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => 1,
                ],
            ],
        ];

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.preview'), ['delivery_method' => 'pickup'])
            ->assertOk()
            ->assertJsonPath('delivery_method', 'pickup')
            ->assertJsonPath('delivery_fee', 0)
            ->assertJsonPath('pickup_address', $pickupAddress);

        $response = $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.store'), [
                'delivery_method' => 'pickup',
                'use_wallet' => false,
            ]);

        $order = Order::query()->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('pickup', $order->delivery_method);
        $this->assertSame(0, $order->delivery_fee);
        $this->assertSame([], $order->shipping_address);
        $this->assertSame($pickupAddress, $order->pickup_address);

        HomeSetting::query()->firstOrFail()->update([
            'content' => [
                'delivery_fee' => 75_000,
                'pickup_address' => 'آدرس جدید فروشگاه',
                'cashback_percent' => 2,
            ],
        ]);
        $order->update(['status' => 'delivered']);

        $this->actingAs($user)
            ->get(route('orders.invoice', $order))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Orders/Invoice')
                ->where('invoice.delivery_method', 'pickup')
                ->where('invoice.delivery_fee', 0)
                ->where('invoice.pickup_address', $pickupAddress));
    }

    public function test_google_customer_must_verify_mobile_before_checkout(): void
    {
        config()->set('services.payamak_panel', [
            'base_url' => 'https://rest.payamak-panel.com/api/SmartSMS',
            'username' => 'user',
            'api_key' => 'key',
            'from' => '5000',
            'timeout' => 5,
        ]);
        Http::fake(['*/Send' => Http::response(['Value' => '30', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        $user = User::factory()->create([
            'google_id' => 'google-checkout-user',
            'phone' => null,
            'phone_verified_at' => null,
        ]);
        $product = Product::factory()->create(['stock' => 2]);
        $session = ['cart' => ["{$product->id}:base" => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]]];

        $this->actingAs($user)
            ->withSession($session)
            ->get(route('checkout.show'))
            ->assertRedirect(route('checkout.phone.show'));

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.store'), [
                'address_mode' => 'new',
                'address' => [
                    'recipient_name' => 'کاربر گوگل',
                    'phone' => '09121234567',
                    'province' => 'تهران',
                    'city' => 'تهران',
                    'address_line' => 'خیابان تست',
                ],
            ])
            ->assertSessionHasErrors('phone_verification');

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('checkout.phone.send'), ['phone' => '+989121234567'])
            ->assertRedirect(route('checkout.phone.show'))
            ->assertSessionHas('checkout_phone_verification_phone', '09121234567');

        $this->assertSame('09121234567', $user->fresh()->phone);
        $this->assertNull($user->fresh()->phone_verified_at);

        MobileVerificationCode::query()
            ->where('phone', '09121234567')
            ->where('purpose', 'checkout_verify_mobile')
            ->update(['code_hash' => Hash::make('123456')]);

        $this->actingAs($user)
            ->withSession([
                ...$session,
                'checkout_phone_verification_user_id' => $user->id,
                'checkout_phone_verification_phone' => '09121234567',
            ])
            ->post(route('checkout.phone.confirm'), ['code' => '123456'])
            ->assertRedirect(route('checkout.show'));

        $this->assertNotNull($user->fresh()->phone_verified_at);

        $this->actingAs($user)
            ->withSession($session)
            ->get(route('checkout.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Checkout/Index'));
    }

    public function test_authenticated_user_can_restore_local_cart_backup(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['stock' => 3]);
        $this->actingAs($user)->post(route('cart.restore'), ['items' => [['product_id' => $product->id, 'variant_id' => null, 'quantity' => 2]]])
            ->assertRedirect(route('checkout.show'))->assertSessionHas("cart.{$product->id}:base.quantity", 2);
    }

    public function test_customer_can_only_cancel_before_admin_approval(): void
    {
        $user = User::factory()->create();
        $pending = $this->orderFor($user, 'pending', 'NP-CANCEL-1');
        $this->actingAs($user)->patch(route('orders.cancel', $pending))->assertRedirect();
        $this->assertSame('cancelled', $pending->fresh()->status);

        $approved = $this->orderFor($user, 'approved', 'NP-CANCEL-2');
        $this->actingAs($user)->patch(route('orders.cancel', $approved))->assertSessionHasErrors('status');
        $this->assertSame('approved', $approved->fresh()->status);
    }

    public function test_only_owner_can_view_invoice_for_delivered_order(): void
    {
        $user = User::factory()->create(['name' => 'خریدار فاکتور']);
        $other = User::factory()->create();
        $pending = $this->orderFor($user, 'pending', 'NP-INVOICE-PENDING');
        $delivered = $this->orderFor($user, 'delivered', 'NP-INVOICE-FINAL', [
            'shipping_address' => ['recipient_name' => 'تحویل گیرنده', 'phone' => '09120000000', 'province' => 'تهران', 'city' => 'تهران', 'address_line' => 'خیابان تست'],
            'regular_subtotal' => 120_000,
            'product_discount' => 20_000,
            'subtotal' => 100_000,
            'coupon_code' => 'FINAL10',
            'coupon_discount' => 10_000,
            'delivery_fee' => 5_000,
            'grand_total' => 95_000,
            'wallet_used' => 15_000,
            'payable_amount' => 80_000,
            'cashback_amount' => 2_000,
        ]);
        $delivered->items()->create(['title' => 'محصول فاکتور', 'sku' => 'SKU-1', 'quantity' => 1, 'regular_unit_price' => 120_000, 'unit_price' => 100_000, 'discount_amount' => 20_000, 'line_total' => 100_000, 'requires_shipping' => true]);

        $this->actingAs($user)->get(route('orders.invoice', $pending))->assertNotFound();
        $this->actingAs($other)->get(route('orders.invoice', $delivered))->assertNotFound();
        $this->actingAs($user)->get(route('orders.invoice', $delivered))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Orders/Invoice')
            ->where('invoice.number', 'NP-INVOICE-FINAL')
            ->where('invoice.status', 'delivered')
            ->where('invoice.payable_amount', 80_000)
            ->where('invoice.items.0.discount_amount', 20_000));
    }

    public function test_pickup_order_cannot_be_marked_as_shipped(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $order = $this->orderFor($user, 'processing', 'NP-PICKUP-STATE', [
            'delivery_method' => 'pickup',
            'pickup_address' => 'تهران، محل مراجعه',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.update', $order), ['status' => 'shipped'])
            ->assertSessionHasErrors('status');

        $this->assertSame('processing', $order->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.orders.update', $order), ['status' => 'delivered'])
            ->assertRedirect();

        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_admin_can_cancel_shipped_order_and_reverse_cashback(): void
    {
        $user = User::factory()->create(['wallet_balance' => 5_000]);
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $product = Product::factory()->create(['stock' => 4]);
        $order = $this->orderFor($user, 'shipped', 'NP-SHIPPED-1', ['wallet_used' => 60_000, 'cashback_amount' => 20_000, 'cashback_credited_at' => now()]);
        $order->forceFill(['cashback_credited_at' => now()])->save();
        $order->items()->create(['product_id' => $product->id, 'title' => $product->title, 'sku' => $product->sku, 'quantity' => 1, 'regular_unit_price' => 100_000, 'unit_price' => 100_000, 'line_total' => 100_000, 'requires_shipping' => true]);

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(45_000, $user->fresh()->wallet_balance);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $order->id, 'type' => 'cashback_reversal', 'amount' => -20_000]);
        $this->assertDatabaseHas('wallet_transactions', ['order_id' => $order->id, 'type' => 'order_refund', 'amount' => 60_000]);
    }

    private function orderFor(User $user, string $status, string $number, array $extra = []): Order
    {
        return Order::query()->create([...['number' => $number, 'user_id' => $user->id, 'shipping_address' => [], 'status' => $status, 'regular_subtotal' => 100_000, 'product_discount' => 0, 'subtotal' => 100_000, 'coupon_discount' => 0, 'delivery_fee' => 0, 'grand_total' => 100_000, 'wallet_used' => 0, 'payable_amount' => 100_000, 'cashback_percent' => 2, 'cashback_eligible_amount' => 100_000, 'cashback_amount' => 2_000], ...$extra]);
    }
}
