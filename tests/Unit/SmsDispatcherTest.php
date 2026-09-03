<?php

namespace Tests\Unit;

use App\Models\SmsOutbox;
use App\Services\Sms\SmsDispatcher;
use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsProvider;
use App\Services\Sms\SmsSendResult;
use App\Services\Sms\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SmsDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_item_is_claimed_and_marked_sent(): void
    {
        config()->set('sms.patterns.otp_verify_mobile', 'real-configured-id');
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldReceive('sendPattern')->once()->with('09121234567', 'real-configured-id', ['code' => '123456'])
            ->andReturn(new SmsSendResult(true, [42], 1, 'ok', []));
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456'], 'otp:test');

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'sent', 'attempts' => 1, 'provider_message_id' => '42']);
        $this->assertDatabaseHas('sms_delivery_attempts', ['attempt' => 1, 'successful' => true, 'provider_status' => 1, 'provider_message_id' => '42']);
    }

    public function test_temporary_failure_is_scheduled_for_a_later_cycle(): void
    {
        config()->set('sms.patterns.otp_verify_mobile', 'real-configured-id');
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldReceive('sendPattern')->once()->andThrow(new \RuntimeException('timeout'));
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);

        app(SmsDispatcher::class)->dispatchDue(1);

        $item = SmsOutbox::query()->firstOrFail();
        $this->assertSame('pending', $item->status);
        $this->assertSame(1, $item->attempts);
        $this->assertTrue($item->next_attempt_at->isFuture());
        $this->assertDatabaseHas('sms_delivery_attempts', ['sms_outbox_id' => $item->id, 'attempt' => 1, 'successful' => false, 'retryable' => true, 'error' => 'timeout']);
    }

    public function test_missing_pattern_fails_without_calling_provider(): void
    {
        $provider = Mockery::mock(SmsProvider::class);
        $provider->shouldNotReceive('sendPattern');
        $this->app->instance(SmsProvider::class, $provider);
        app(SmsService::class)->enqueue(SmsPattern::OtpVerifyMobile, '09121234567', ['code' => '123456']);

        app(SmsDispatcher::class)->dispatchDue(1);

        $this->assertDatabaseHas('sms_outbox', ['status' => 'failed', 'attempts' => 1, 'last_error' => 'SMS pattern is not configured in config/sms.php.']);
        $this->assertDatabaseHas('sms_delivery_attempts', ['attempt' => 1, 'successful' => false, 'retryable' => false]);
    }
}
