<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SmsOutbox;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\OrderActivityNotification;
use App\Notifications\OrderCashbackNotification;
use App\Notifications\TicketActivityNotification;
use App\Services\Sms\SmsNotificationService;
use App\Services\Sms\SmsPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_is_only_written_to_outbox_without_network_io(): void
    {
        Http::fake();
        $user = User::factory()->create(['phone' => '+989121234567']);
        app(SmsNotificationService::class)->send($user, SmsPattern::TicketActivity, ['title' => 'عنوان', 'message' => 'پیام'], 'ticket:1:user:'.$user->id);

        Http::assertNothingSent();
        $this->assertDatabaseHas('sms_outbox', ['mobile' => '09121234567', 'pattern' => SmsPattern::TicketActivity->value, 'status' => 'pending']);
    }

    public function test_missing_phone_is_skipped_and_idempotency_prevents_duplicates(): void
    {
        $user = User::factory()->create(['phone' => '09121234567']);
        $service = app(SmsNotificationService::class);
        $service->send($user, SmsPattern::TicketActivity, ['title' => 'عنوان', 'message' => 'پیام'], 'same-key');
        $service->send($user, SmsPattern::TicketActivity, ['title' => 'عنوان', 'message' => 'پیام'], 'same-key');
        $service->send(new User(['phone' => null]), SmsPattern::TicketActivity, ['title' => 'عنوان', 'message' => 'پیام']);

        $this->assertSame(1, SmsOutbox::query()->count());
    }

    public function test_all_business_notifications_have_structured_patterns(): void
    {
        $user = new User;
        $order = new Order(['number' => 'NP-1', 'cashback_amount' => 1000]);
        $order->id = 1;
        $order->setRelation('items', collect());
        $ticket = new Ticket(['number' => 'TK-1']);
        $ticket->id = 1;
        foreach ([new OrderActivityNotification($order, 'عنوان', 'پیام'), new OrderCashbackNotification($order), new TicketActivityNotification($ticket, 'عنوان', 'پیام')] as $notification) {
            $payload = $notification->toSms($user);
            $this->assertContains(SmsChannel::class, $notification->via($user));
            $this->assertInstanceOf(SmsPattern::class, $payload['pattern']);
            $this->assertIsArray($payload['variables']);
            $this->assertNotEmpty($payload['idempotency_key']);
        }
    }

    public function test_order_pattern_contains_order_products_and_total_as_variables(): void
    {
        $order = new Order(['number' => 'NP-1405', 'grand_total' => 250000]);
        $order->setRelation('items', collect([new OrderItem(['title' => 'گیفت کارت', 'quantity' => 2])]));
        $payload = (new OrderActivityNotification($order, 'سفارش ثبت شد', 'در انتظار تأیید است.'))->toSms(new User);

        $this->assertSame('NP-1405', $payload['variables']['order']);
        $this->assertStringContainsString('گیفت کارت', $payload['variables']['products']);
        $this->assertSame('250000', $payload['variables']['amount']);
    }

    public function test_notification_destinations_are_internal_relative_routes(): void
    {
        $order = new Order(['number' => 'NP-1']); $order->id = 12;
        $ticket = new Ticket(['number' => 'TK-1']); $ticket->id = 8;
        $this->assertSame('/orders/12', (new OrderActivityNotification($order, 'عنوان', 'پیام'))->toArray(new User)['url']);
        $this->assertSame('/admin/orders/12', (new OrderActivityNotification($order, 'عنوان', 'پیام', true))->toArray(new User)['url']);
        $this->assertSame('/account/tickets/8', (new TicketActivityNotification($ticket, 'عنوان', 'پیام'))->toArray(new User)['url']);
        $this->assertSame('/admin/tickets/8', (new TicketActivityNotification($ticket, 'عنوان', 'پیام', true))->toArray(new User)['url']);
    }
}
