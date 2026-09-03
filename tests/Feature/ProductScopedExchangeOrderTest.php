<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductScopedExchangeOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_only_applies_to_approved_product_user_and_status(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $a = Product::factory()->create(['price' => 50_000_000, 'discount_price' => null, 'stock' => 5, 'status' => 'published', 'visibility' => 'public']);
        $b = Product::factory()->create(['price' => 10_000_000, 'discount_price' => null, 'stock' => 5, 'status' => 'published', 'visibility' => 'public']);
        $ticket = $this->exchange($user, $a, 'accepted', 30_000_000);
        $orders = app(OrderService::class);

        $summary = $orders->preview($this->cart($a, $b), $user, exchangeRequestId: $ticket->id);
        $this->assertSame(30_000_000, $summary['exchange_credit_used']);
        $this->assertSame(30_000_000, $summary['grand_total']);

        foreach ([[$this->cart($b), $user], [$this->cart($a), $other]] as [$cart, $actor]) {
            try {
                $orders->preview($cart, $actor, exchangeRequestId: $ticket->id);
                $this->fail('Invalid exchange was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        foreach (['pending_review', 'rejected'] as $status) {
            $invalid = $this->exchange($user, $a, $status, 1_000_000);
            try {
                $orders->preview($this->cart($a), $user, exchangeRequestId: $invalid->id);
                $this->fail('Invalid status was accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_current_price_is_used_then_order_snapshots_and_consumes_exchange_once(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 48_000_000, 'discount_price' => null, 'stock' => 5, 'status' => 'published', 'visibility' => 'public']);
        $ticket = $this->exchange($user, $product, 'accepted', 30_000_000);
        $product->update(['price' => 52_000_000]);
        $orders = app(OrderService::class);
        $this->assertSame(22_000_000, $orders->preview($this->cart($product), $user, exchangeRequestId: $ticket->id)['grand_total']);

        $order = $orders->create($this->cart($product), $user, [], null, false, $ticket->id);
        $this->assertSame(30_000_000, $order->approved_trade_value);
        $this->assertSame($product->id, $order->approved_product_id);
        $this->assertSame('PlayStation 4 Pro', $order->trade_item_title);
        $this->assertSame(22_000_000, $order->grand_total);
        $product->update(['price' => 70_000_000]);
        $ticket->update(['exchange_offer_amount' => 10]);
        $this->assertSame(22_000_000, $order->fresh()->grand_total);

        try {
            $orders->create($this->cart($product), $user, [], null, false, $ticket->id);
            $this->fail('Exchange was spent twice.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    public function test_trade_value_is_capped_at_one_approved_product_price_without_wallet_credit(): void
    {
        $user = User::factory()->create(['wallet_balance' => 0]);
        $product = Product::factory()->create(['price' => 5_000_000, 'discount_price' => null, 'stock' => 5, 'status' => 'published', 'visibility' => 'public']);
        $ticket = $this->exchange($user, $product, 'accepted', 8_000_000);
        $order = app(OrderService::class)->create($this->cart($product), $user, [], null, false, $ticket->id);
        $this->assertSame(0, $order->payable_amount);
        $this->assertSame(5_000_000, $order->exchange_credit_used);
        $this->assertSame(0, $user->fresh()->wallet_balance);
    }

    private function exchange(User $user, Product $product, string $status, int $value): Ticket
    {
        return Ticket::create(['number' => uniqid('TK-'), 'user_id' => $user->id, 'product_id' => $product->id, 'target_product_id' => $product->id, 'trade_item_title' => 'PlayStation 4 Pro', 'trade_item_description' => 'Used console', 'trade_item_images' => ['tickets/item.jpg'], 'subject' => 'Exchange', 'type' => 'exchange', 'status' => 'open', 'exchange_status' => $status, 'exchange_offer_amount' => $value, 'last_replied_at' => now(), 'created_by' => $user->id]);
    }

    private function cart(Product ...$products): array
    {
        return collect($products)->mapWithKeys(fn (Product $product) => ["{$product->id}:base" => ['product_id' => $product->id, 'variant_id' => null, 'quantity' => 1]])->all();
    }
}
