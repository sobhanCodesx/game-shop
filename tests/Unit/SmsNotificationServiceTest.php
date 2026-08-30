<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\OrderActivityNotification;
use App\Notifications\OrderCashbackNotification;
use App\Notifications\TicketActivityNotification;
use App\Services\Sms\SmsNotificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsNotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payamak_panel', ['base_url' => 'https://rest.payamak-panel.com/api/SmartSMS', 'username' => 'user', 'api_key' => 'key', 'from' => '5000', 'timeout' => 5]);
    }

    public function test_notification_sms_uses_parameterized_text_and_cancellation_footer(): void
    {
        Http::fake(['*/Send' => Http::response(['Value' => '91', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);
        $user = new User(['phone' => '+989121234567']);
        $user->id = 10;
        app(SmsNotificationService::class)->send($user, 'سفارش شما ثبت شد', 'سفارش NP-100 ثبت شد.');
        Http::assertSent(fn ($request) => $request['to'] === '09121234567' && $request['text'] === "سفارش شما ثبت شد\nسفارش NP-100 ثبت شد.\nلغو11");
    }

    public function test_sms_failure_does_not_break_business_flow_and_missing_phone_is_skipped(): void
    {
        Http::fake(['*/Send' => Http::response([], 500)]);
        app(SmsNotificationService::class)->send(new User(['phone' => '09121234567']), 'عنوان', 'پیام');
        app(SmsNotificationService::class)->send(new User(['phone' => null]), 'عنوان', 'پیام');
        $this->assertTrue(true);
        Http::assertSentCount(1);
    }

    public function test_all_business_notifications_include_sms_channel(): void
    {
        $user = new User;
        $order = new Order(['number' => 'NP-1', 'cashback_amount' => 1000]);
        $order->id = 1;
        $ticket = new Ticket(['number' => 'TK-1']);
        $ticket->id = 1;
        foreach ([new OrderActivityNotification($order, 'عنوان', 'پیام'), new OrderCashbackNotification($order), new TicketActivityNotification($ticket, 'عنوان', 'پیام')] as $notification) {
            $this->assertContains(SmsChannel::class, $notification->via($user));
            $this->assertSame(['title', 'message'], array_keys($notification->toSms($user)));
        }
    }

    public function test_order_sms_contains_order_number_products_quantities_and_total(): void
    {
        $order = new Order(['number' => 'NP-1405', 'grand_total' => 250000]);
        $order->setRelation('items', collect([
            new OrderItem(['title' => 'گیفت کارت پلی‌استیشن', 'quantity' => 2]),
            new OrderItem(['title' => 'اشتراک گیم‌پس', 'quantity' => 1]),
        ]));
        $payload = (new OrderActivityNotification($order, 'سفارش ثبت شد', 'در انتظار تأیید است.'))->toSms(new User);

        $this->assertStringContainsString('شماره سفارش: NP-1405', $payload['message']);
        $this->assertStringContainsString('گیفت کارت پلی‌استیشن × 2', $payload['message']);
        $this->assertStringContainsString('اشتراک گیم‌پس × 1', $payload['message']);
        $this->assertStringContainsString('مبلغ نهایی: 250,000 تومان', $payload['message']);
    }

    public function test_notification_destinations_are_internal_relative_routes(): void
    {
        $order = new Order(['number' => 'NP-1']);
        $order->id = 12;
        $ticket = new Ticket(['number' => 'TK-1']);
        $ticket->id = 8;

        $this->assertSame('/orders/12', (new OrderActivityNotification($order, 'عنوان', 'پیام'))->toArray(new User)['url']);
        $this->assertSame('/admin/orders/12', (new OrderActivityNotification($order, 'عنوان', 'پیام', true))->toArray(new User)['url']);
        $this->assertSame('/account/tickets/8', (new TicketActivityNotification($ticket, 'عنوان', 'پیام'))->toArray(new User)['url']);
        $this->assertSame('/admin/tickets/8', (new TicketActivityNotification($ticket, 'عنوان', 'پیام', true))->toArray(new User)['url']);
    }
}
