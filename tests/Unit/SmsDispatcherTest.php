<?php

namespace Tests\Unit;

use App\Models\SmsOutbox;
use App\Models\SmsPatternConfiguration;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsMessageFormatter;
use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsProvider;
use App\Services\Sms\SmsSendResult;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SmsDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_item_is_claimed_and_marked_sent(): void
    {
        SmsPatternConfiguration::query()->where('code', 'otp_verify_mobile')->update(['provider_id' => 'real-configured-id']);
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldReceive('sendPattern')->once()->with('09121234567', 'real-configured-id', ['code' => "123456\nلغو11"])
            ->andReturn(new SmsSendResult(true, [42], 1, 'ok', []));
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456'], 'otp:test');

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'sent', 'attempts' => 1, 'provider_message_id' => '42']);
        $this->assertDatabaseHas('sms_delivery_attempts', ['attempt' => 1, 'successful' => true, 'provider_status' => 1, 'provider_message_id' => '42']);
    }

    public function test_temporary_failure_is_scheduled_for_a_later_cycle(): void
    {
        SmsPatternConfiguration::query()->where('code', 'otp_verify_mobile')->update(['provider_id' => 'real-configured-id']);
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldReceive('sendPattern')->once()->andThrow(new RuntimeException('timeout'));
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);

        app(SmsDispatcher::class)->dispatchDue(1);

        $item = SmsOutbox::query()->firstOrFail();
        $this->assertSame('pending', $item->status);
        $this->assertSame(1, $item->attempts);
        $this->assertTrue($item->next_attempt_at->isFuture());
        $this->assertDatabaseHas('sms_delivery_attempts', ['sms_outbox_id' => $item->id, 'attempt' => 1, 'successful' => false, 'retryable' => true, 'error' => 'timeout']);
    }

    public function test_default_template_sends_without_a_provider_pattern_id(): void
    {
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldNotReceive('sendPattern');
        $provider->shouldReceive('send')->once()
            ->with('09121234567', "کد تأیید موبایل شما: 123456\nلغو11")
            ->andReturn(new SmsSendResult(true, [43], 1, 'ok', []));
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'sent', 'attempts' => 1, 'provider_message_id' => '43']);
        $this->assertDatabaseHas('sms_delivery_attempts', ['attempt' => 1, 'successful' => true]);
    }

    public function test_inactive_pattern_fails_without_calling_provider(): void
    {
        SmsPatternConfiguration::query()->where('code', 'otp_verify_mobile')->update(['is_active' => false]);
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldNotReceive('sendPattern');
        $provider->shouldNotReceive('send');
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'failed', 'attempts' => 1, 'last_error' => 'SMS pattern is inactive in the pattern registry.']);
    }

    public function test_every_service_pattern_has_a_working_default_template(): void
    {
        $payloads = [
            SmsPattern::OtpVerifyMobile->value => ['code' => '111111'],
            SmsPattern::OtpPasswordlessLogin->value => ['code' => '222222'],
            SmsPattern::OtpResetPassword->value => ['code' => '333333'],
            SmsPattern::OrderActivity->value => ['title' => 'ثبت سفارش', 'order' => 'NP-1', 'products' => 'بازی', 'amount' => '100000', 'message' => 'ثبت شد'],
            SmsPattern::OrderCashback->value => ['order' => 'NP-1', 'products' => 'بازی', 'amount' => '10000'],
            SmsPattern::TicketActivity->value => ['title' => 'پاسخ جدید', 'message' => 'پاسخ ثبت شد'],
        ];
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldNotReceive('sendPattern');
        foreach (SmsPattern::cases() as $pattern) {
            $variables = $payloads[$pattern->value];
            $provider->shouldReceive('send')->once()
                ->with('09121234567', SmsMessageFormatter::withOptOutFooter($pattern->render($variables)))
                ->andReturn(new SmsSendResult(true, [100], 1, 'ok', []));
            app(SmsService::class)->enqueue($pattern, '09121234567', $variables);
        }
        $this->app->instance(SmsProvider::class, $provider);

        app(SmsDispatcher::class)->dispatchDue(5);
        app(SmsDispatcher::class)->dispatchDue(5);

        $this->assertSame(6, SmsOutbox::query()->where('status', 'sent')->count());
    }

    public function test_unknown_pattern_is_a_permanent_failure(): void
    {
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldNotReceive('sendPattern');
        $this->app->instance(SmsProvider::class, $provider);
        SmsOutbox::query()->create([
            'mobile' => '09121234567',
            'pattern' => 'unknown_pattern',
            'payload' => ['code' => '123456'],
            'status' => 'pending',
        ]);

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'failed', 'attempts' => 1]);
        $this->assertDatabaseHas('sms_delivery_attempts', ['attempt' => 1, 'successful' => false, 'retryable' => false]);
    }

    public function test_already_claimed_item_is_not_sent_by_another_dispatch_cycle(): void
    {
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldNotReceive('sendPattern');
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);
        SmsOutbox::query()->update(['status' => 'processing', 'claimed_at' => now()]);

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'processing', 'attempts' => 0]);
        $this->assertDatabaseCount('sms_delivery_attempts', 0);
    }

    public function test_outbox_item_is_rolled_back_with_its_business_transaction(): void
    {
        try {
            DB::transaction(function (): void {
                app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Expected: the business transaction and its SMS outbox item roll back together.
        }

        $this->assertDatabaseCount('sms_outbox', 0);
    }
}
