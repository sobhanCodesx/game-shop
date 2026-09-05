<?php

namespace Tests\Unit;

use App\Exceptions\SmsProviderException;
use App\Services\Sms\PayamakPanelSmsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class PayamakPanelSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.payamak_panel', ['base_url' => 'https://rest.payamak-panel.com/api/SmartSMS', 'username' => 'user', 'api_key' => 'secret-key', 'from' => '5000', 'timeout' => 5]);
    }

    public function test_pattern_send_uses_configured_endpoint_and_structured_variable_order(): void
    {
        config()->set('services.payamak_panel.pattern_endpoint', 'https://sms.example.test/pattern');
        Http::fake(['sms.example.test/*' => Http::response(['Value' => '77', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        $result = app(PayamakPanelSmsService::class)->sendPattern('09121234567', '12345', ['code' => '654321']);

        $this->assertTrue($result->success);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/pattern'
            && $request['username'] === 'user' && $request['password'] === 'secret-key'
            && $request['to'] === '09121234567' && $request['bodyId'] === '12345' && $request['text'] === "654321\nلغو11");
    }

    public function test_send_uses_api_key_as_password_and_parses_ids(): void
    {
        Http::fake(['*/Send' => Http::response(['Value' => '123,124', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);
        $result = app(PayamakPanelSmsService::class)->send(['+989121234567', '۰۹۱۲۱۲۳۴۵۶۸'], 'test');
        $this->assertTrue($result->success);
        $this->assertSame([123, 124], $result->messageIds);
        Http::assertSent(fn ($request) => $request['password'] === 'secret-key'
            && $request['to'] === '09121234567,09121234568'
            && $request['text'] === "test\nلغو11"
            && ! isset($request['fromSupportOne']));
    }

    public function test_provider_failure_is_returned_without_retry(): void
    {
        Http::fake(['*/Send' => Http::response(['Value' => '', 'RetStatus' => 5, 'StrRetStatus' => 'Bad sender'])]);
        $result = app(PayamakPanelSmsService::class)->send('09121234567', 'test');
        $this->assertFalse($result->success);
        $this->assertSame(5, $result->status);
        $this->assertSame('شماره فرستنده معتبر نیست.', $result->message);
        Http::assertSentCount(1);
    }

    public function test_opt_out_footer_is_not_duplicated(): void
    {
        Http::fake(['*/Send' => Http::response(['Value' => '125', 'RetStatus' => 1, 'StrRetStatus' => 'Ok'])]);

        app(PayamakPanelSmsService::class)->send('09121234567', "متن آزمایشی\nلغو11");

        Http::assertSent(fn ($request) => $request['text'] === "متن آزمایشی\nلغو11");
    }

    public function test_http_and_connection_failures_throw_provider_exception(): void
    {
        Http::fake(['*/Send' => Http::response([], 500)]);

        try {
            app(PayamakPanelSmsService::class)->send('09121234567', 'test');
            $this->fail('The provider exception was not thrown.');
        } catch (SmsProviderException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame(500, $exception->providerStatus);
        }
    }

    public function test_http_configuration_failure_is_not_retried(): void
    {
        Http::fake(['*/Send' => Http::response(['message' => 'Unauthorized'], 401)]);

        try {
            app(PayamakPanelSmsService::class)->send('09121234567', 'test');
            $this->fail('The provider exception was not thrown.');
        } catch (SmsProviderException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame(401, $exception->providerStatus);
        }
    }

    public function test_connection_failure_throws_provider_exception(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        try {
            app(PayamakPanelSmsService::class)->send('09121234567', 'test');
            $this->fail('The provider exception was not thrown.');
        } catch (SmsProviderException $exception) {
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_send_multiple_preserves_recipient_message_ids(): void
    {
        Http::fake(['*/SendMultiple' => Http::response(['ReqStatus' => '1', 'Message' => 'sent', 'Result' => [['Mobile' => '09121234567', 'ID' => 31], ['Mobile' => '09121234568', 'ID' => 32]]])]);
        $result = app(PayamakPanelSmsService::class)->sendMultiple(['09121234567', '09121234568'], ['one', 'two']);
        $this->assertTrue($result->success);
        $this->assertSame([31, 32], $result->messageIds);
        $this->assertCount(2, $result->recipients);
        Http::assertSent(fn ($request) => $request['text'] === ["one\nلغو11", "two\nلغو11"]);
    }

    public function test_more_than_one_hundred_recipients_is_rejected_before_request(): void
    {
        Http::fake();
        $this->expectException(InvalidArgumentException::class);
        app(PayamakPanelSmsService::class)->send(array_fill(0, 101, '09121234567'), 'test');
    }

    public function test_limits_and_multiple_message_counts_are_validated(): void
    {
        $service = app(PayamakPanelSmsService::class);
        $this->expectException(InvalidArgumentException::class);
        $service->sendMultiple(['09121234567'], ['one', 'two']);
    }

    public function test_delivery_status_is_mapped(): void
    {
        Http::fake(['*/GetDeliveries2' => Http::response(['Value' => 1])]);
        $result = app(PayamakPanelSmsService::class)->getDelivery(123);
        $this->assertTrue($result->isDelivered);
        $this->assertTrue($result->isFinal);
        $this->assertSame(1, $result->code);
    }
}
