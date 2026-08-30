<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\HomeSetting;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OrderCashbackNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
