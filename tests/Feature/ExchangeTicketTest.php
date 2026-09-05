<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExchangeTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creates_exchange_ticket_with_an_immutable_item_snapshot(): void
    {
        Storage::fake('public');
        config(['media.disk' => 'public']);
        $user = User::factory()->create();
        $product = Product::factory()->create(['trade_enabled' => true, 'status' => 'published', 'visibility' => 'public']);
        $this->actingAs($user)->withSession(['ticket_anti_bot' => ['code' => 'ABCDE', 'created_at' => now()->timestamp]])->post(route('account.tickets.store'), [
            'type' => 'exchange', 'product_id' => $product->id, 'trade_item_title' => 'PlayStation 4 Pro 1TB',
            'message' => 'مشخصات کامل کالای پیشنهادی من برای معاوضه', 'anti_bot_code' => 'ABCDE',
            'attachments' => [UploadedFile::fake()->image('item.jpg')],
        ])->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $this->assertSame('pending_review', $ticket->exchange_status);
        $this->assertNull($ticket->target_product_id);
        $this->assertSame('PlayStation 4 Pro 1TB', $ticket->trade_item_title);
        $this->assertCount(1, $ticket->trade_item_images);
    }

    public function test_admin_offer_is_bound_to_an_explicit_product(): void
    {
        $user = User::factory()->create();
        $requested = Product::factory()->create(['trade_enabled' => true]);
        $approved = Product::factory()->create(['trade_enabled' => true]);
        $ticket = Ticket::create(['number' => 'TK-EX-1', 'user_id' => $user->id, 'product_id' => $requested->id, 'trade_item_title' => 'PS4', 'subject' => 'معاوضه', 'type' => 'exchange', 'status' => 'open', 'exchange_status' => 'pending_review', 'last_replied_at' => now(), 'created_by' => $user->id]);
        app(ExchangeService::class)->offer($ticket, $approved, 6_000_000);
        $this->assertSame($approved->id, $ticket->fresh()->target_product_id);
        $this->assertSame(6_000_000, $ticket->fresh()->exchange_offer_amount);
    }

    public function test_accepted_exchange_ticket_exposes_the_target_product_order_link(): void
    {
        $user = User::factory()->create();
        $requested = Product::factory()->create(['trade_enabled' => true]);
        $target = Product::factory()->create([
            'trade_enabled' => true,
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $ticket = Ticket::create([
            'number' => 'TK-EX-ACCEPTED',
            'user_id' => $user->id,
            'product_id' => $requested->id,
            'target_product_id' => $target->id,
            'trade_item_title' => 'PS4',
            'subject' => 'معاوضه',
            'type' => 'exchange',
            'status' => 'open',
            'exchange_status' => 'accepted',
            'exchange_offer_amount' => 6_000_000,
            'last_replied_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('account.tickets.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Tickets/Show')
                ->where('ticket.exchange_status', 'accepted')
                ->where('ticket.target_product.id', $target->id)
                ->where('ticket.target_product.slug', $target->slug));

        $this->get(route('products.show', [
            'product' => $target,
            'exchange_request_id' => $ticket->id,
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Products/Show')
            ->where('exchangeRequestId', $ticket->id));
    }

    public function test_customer_acceptance_preselects_and_deducts_exchange_at_checkout(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'price' => 20_000_000,
            'discount_price' => null,
            'stock' => 2,
        ]);
        $ticket = Ticket::create([
            'number' => 'TK-EX-OFFERED',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'target_product_id' => $product->id,
            'trade_item_title' => 'کنسول PlayStation 4 Pro',
            'subject' => 'معاوضه',
            'type' => 'exchange',
            'status' => 'open',
            'exchange_status' => 'offered',
            'exchange_offer_amount' => 6_000_000,
            'exchange_credit_expires_at' => now()->addDay(),
            'last_replied_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patch(route('account.tickets.exchange-response', $ticket), [
                'decision' => 'accepted',
            ])->assertRedirect();
        $this->assertSame('accepted', $ticket->fresh()->exchange_status);

        $this->withSession([
            'cart' => ["{$product->id}:base" => [
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 1,
            ]],
        ])->get(route('checkout.show', ['exchange_request_id' => $ticket->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Checkout/Index')
                ->where('selectedExchangeId', $ticket->id)
                ->where('availableExchanges.0.trade_item_title', 'کنسول PlayStation 4 Pro')
                ->where('summary.exchange_credit_used', 6_000_000));
    }

    public function test_admin_can_cancel_an_unattached_exchange_request(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true, 'role' => 'admin']);
        $user = User::factory()->create();
        $product = Product::factory()->create(['trade_enabled' => true]);
        $ticket = Ticket::create([
            'number' => 'TK-EX-CANCEL',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'target_product_id' => $product->id,
            'trade_item_title' => 'Xbox Series S',
            'subject' => 'معاوضه',
            'type' => 'exchange',
            'status' => 'open',
            'exchange_status' => 'accepted',
            'exchange_offer_amount' => 8_000_000,
            'last_replied_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.tickets.exchange-cancel', $ticket))
            ->assertRedirect();

        $this->assertSame('cancelled', $ticket->fresh()->exchange_status);
    }

    public function test_admin_exchange_menu_lists_only_exchange_tickets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        Ticket::create(['number' => 'TK-SUPPORT', 'user_id' => $user->id, 'subject' => 'پشتیبانی', 'type' => 'support', 'status' => 'pending', 'last_replied_at' => now(), 'created_by' => $user->id]);
        $exchange = Ticket::create(['number' => 'TK-EXCHANGE', 'user_id' => $user->id, 'subject' => 'معاوضه', 'type' => 'exchange', 'status' => 'pending', 'exchange_status' => 'pending_review', 'last_replied_at' => now(), 'created_by' => $user->id]);
        $this->actingAs($admin)->get(route('admin.tickets.index', ['type' => 'exchange']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Tickets/Index')->has('tickets.data', 1)->where('tickets.data.0.id', $exchange->id)->where('filters.type', 'exchange'));
    }
}
