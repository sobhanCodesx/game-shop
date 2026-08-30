<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ExchangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExchangeTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_creates_exchange_ticket_with_media(): void
    {
        Storage::fake('public');
        config(['media.disk' => 'public']);
        $user = User::factory()->create();
        $product = Product::factory()->create(['trade_enabled' => true, 'status' => 'published', 'visibility' => 'public']);

        $this->actingAs($user)->withSession(['ticket_anti_bot' => ['code' => 'ABCDE', 'created_at' => now()->timestamp]])
            ->post(route('account.tickets.store'), ['type' => 'exchange', 'product_id' => $product->id, 'message' => 'مشخصات کامل کالای پیشنهادی من برای معاوضه', 'anti_bot_code' => 'ABCDE', 'attachments' => [UploadedFile::fake()->image('item.jpg')]])
            ->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertSame('exchange', $ticket->type);
        $this->assertSame('pending_review', $ticket->exchange_status);
        $this->assertDatabaseCount('ticket_attachments', 1);
    }

    public function test_completed_exchange_credits_wallet_exactly_once_and_adjustments_have_transactions(): void
    {
        $user = User::factory()->create(['wallet_balance' => 100]);
        $ticket = Ticket::create(['number' => 'TK-EX-1', 'user_id' => $user->id, 'product_id' => Product::factory()->create(['trade_enabled' => true])->id, 'subject' => 'معاوضه', 'type' => 'exchange', 'status' => 'open', 'exchange_status' => 'accepted', 'exchange_offer_amount' => 6_000_000, 'last_replied_at' => now(), 'created_by' => $user->id]);
        $service = app(ExchangeService::class);

        $service->complete($ticket);
        $this->assertSame(6_000_100, $user->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['ticket_id' => $ticket->id, 'type' => 'exchange_credit', 'amount' => 6_000_000]);

        try { $service->complete($ticket->fresh()); $this->fail('Duplicate credit was allowed.'); } catch (ValidationException) {}
        $this->assertSame(6_000_100, $user->fresh()->wallet_balance);

        $service->adjust($ticket->fresh(), 250_000, 'اصلاح قیمت با توافق نهایی');
        $this->assertSame(6_250_100, $user->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['ticket_id' => $ticket->id, 'type' => 'exchange_adjustment', 'amount' => 250_000, 'description' => 'اصلاح قیمت با توافق نهایی']);
    }

    public function test_admin_exchange_menu_lists_only_exchange_tickets(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        Ticket::create(['number' => 'TK-SUPPORT', 'user_id' => $user->id, 'subject' => 'پشتیبانی', 'type' => 'support', 'status' => 'pending', 'last_replied_at' => now(), 'created_by' => $user->id]);
        $exchange = Ticket::create(['number' => 'TK-EXCHANGE', 'user_id' => $user->id, 'subject' => 'معاوضه', 'type' => 'exchange', 'status' => 'pending', 'exchange_status' => 'pending_review', 'last_replied_at' => now(), 'created_by' => $user->id]);

        $this->actingAs($admin)->get(route('admin.tickets.index', ['type' => 'exchange']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Tickets/Index')->has('tickets.data', 1)
                ->where('tickets.data.0.id', $exchange->id)
                ->where('filters.type', 'exchange'));
    }
}
