<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_only_sees_and_selects_their_purchased_products(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->orderItem($user, 'محصول من');
        $theirs = $this->orderItem($other, 'محصول دیگران');

        $this->actingAs($user)->get(route('account.tickets.create'))->assertOk()->assertInertia(fn ($page) => $page->component('Account/Tickets/Create')->has('purchases.data', 1)->where('purchases.data.0.id', $mine->id));
        $this->actingAs($user)->withSession(['ticket_anti_bot' => ['code' => 'ABCDE', 'created_at' => now()->timestamp]])->post(route('account.tickets.store'), ['order_item_id' => $theirs->id, 'message' => 'این متن درخواست آزمایشی است.', 'anti_bot_code' => 'ABCDE'])->assertSessionHasErrors('order_item_id');
    }

    public function test_customer_ticket_notifies_admin_and_support_reply_notifies_customer(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $this->actingAs($user)->withSession(['ticket_anti_bot' => ['code' => 'ABCDE', 'created_at' => now()->timestamp]])->post(route('account.tickets.store'), ['subject' => 'مشکل عمومی', 'message' => 'این متن درخواست پشتیبانی آزمایشی است.', 'anti_bot_code' => 'ABCDE'])->assertRedirect();
        $ticket = Ticket::firstOrFail();
        Notification::assertSentTo($admin, TicketActivityNotification::class);

        $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['message' => 'پاسخ پشتیبانی ثبت شد.'])->assertRedirect();
        Notification::assertSentTo($user, TicketActivityNotification::class);
        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertCount(2, $ticket->replies);
    }

    public function test_admin_can_create_order_ticket_for_customer(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $item = $this->orderItem($user, 'بازی خریداری‌شده');

        $this->actingAs($admin)->post(route('admin.orders.tickets.store', $item->order), ['order_item_id' => $item->id, 'message' => 'برای تکمیل سفارش لطفاً پاسخ دهید.'])->assertRedirect();
        $ticket = Ticket::firstOrFail();
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame($item->product_id, $ticket->product_id);
        Notification::assertSentTo($user, TicketActivityNotification::class);
    }

    public function test_ticket_anti_bot_is_validated_on_backend(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['ticket_anti_bot' => ['code' => 'ABCDE', 'created_at' => now()->timestamp]])->post(route('account.tickets.store'), ['subject' => 'درخواست عمومی', 'message' => 'متن کامل درخواست پشتیبانی', 'anti_bot_code' => 'WRONG'])->assertSessionHasErrors('anti_bot_code');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_anti_bot_stays_stable_through_the_entire_creation_flow(): void
    {
        $user = User::factory()->create();
        $first = $this->actingAs($user)->get(route('account.tickets.create'));
        $code = session('ticket_anti_bot.code');

        $first->assertInertia(fn ($page) => $page->where('antiBotCode', $code));
        $this->get(route('account.tickets.create', ['page' => 2]))
            ->assertInertia(fn ($page) => $page->where('antiBotCode', $code));

        $this->post(route('account.tickets.store'), [
            'subject' => '',
            'message' => 'متن معتبر ولی بدون موضوع',
            'anti_bot_code' => strtolower(" {$code} "),
        ])->assertSessionHasErrors('subject');
        $this->assertSame($code, session('ticket_anti_bot.code'));

        $this->post(route('account.tickets.store'), [
            'subject' => 'موضوع نهایی',
            'message' => 'متن کامل درخواست پشتیبانی',
            'anti_bot_code' => strtolower(" {$code} "),
        ])->assertRedirect();

        $this->assertNull(session('ticket_anti_bot'));
        $this->assertDatabaseCount('tickets', 1);
    }

    public function test_admin_can_purge_closed_ticket_files_without_deleting_ticket_or_replies(): void
    {
        Storage::fake('public');
        config(['media.disk' => 'public']);
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $ticket = Ticket::create(['number' => 'TK-PURGE-1', 'user_id' => $user->id, 'subject' => 'تیکت بسته', 'status' => 'closed', 'last_replied_at' => now(), 'created_by' => $user->id]);
        $reply = $ticket->replies()->create(['user_id' => $user->id, 'message' => 'پیام همراه فایل', 'is_admin' => false]);
        Storage::disk('public')->put('tickets/1/file.jpg', 'image');
        $reply->attachments()->create(['user_id' => $user->id, 'path' => 'tickets/1/file.jpg', 'original_name' => 'file.jpg', 'mime_type' => 'image/jpeg', 'type' => 'image', 'size' => 5]);

        $this->actingAs($admin)->delete(route('admin.tickets.attachments.destroy', $ticket))->assertRedirect();

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        $this->assertDatabaseHas('ticket_replies', ['id' => $reply->id]);
        $this->assertDatabaseCount('ticket_attachments', 0);
        Storage::disk('public')->assertMissing('tickets/1/file.jpg');
    }

    private function orderItem(User $user, string $title)
    {
        $product = Product::factory()->create(['title' => $title]);
        $order = Order::create(['number' => 'NP-'.uniqid(), 'user_id' => $user->id, 'shipping_address' => [], 'status' => 'delivered', 'regular_subtotal' => 1000, 'product_discount' => 0, 'subtotal' => 1000, 'coupon_discount' => 0, 'delivery_fee' => 0, 'grand_total' => 1000, 'wallet_used' => 0, 'payable_amount' => 1000, 'cashback_percent' => 2, 'cashback_eligible_amount' => 1000, 'cashback_amount' => 20]);

        return $order->items()->create(['product_id' => $product->id, 'title' => $title, 'sku' => 'SKU-'.uniqid(), 'quantity' => 1, 'regular_unit_price' => 1000, 'unit_price' => 1000, 'line_total' => 1000, 'requires_shipping' => false]);
    }
}
