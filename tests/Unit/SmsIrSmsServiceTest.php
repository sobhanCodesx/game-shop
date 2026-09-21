<?php

namespace Tests\Unit;

use App\Services\Sms\SmsIrSmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsIrSmsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sms_ir', [
            'base_url' => 'https://api.sms.ir/v1',
            'api_key' => 'secret-key',
            'line_number' => '3000123456',
            'connect_timeout' => 2,
            'timeout' => 5,
        ]);
    }

    public function test_bulk_send_uses_api_key_header_and_expected_payload(): void
    {
        Http::fake([
            'api.sms.ir/v1/send/bulk' => Http::response([
                'status' => 1,
                'message' => 'موفق',
                'data' => 12345,
            ]),
        ]);

        $result = app(SmsIrSmsService::class)->send('09121234567', 'پیام تست');

        $this->assertTrue($result->success);
        $this->assertSame([12345], $result->messageIds);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sms.ir/v1/send/bulk'
            && $request->hasHeader('X-API-KEY', 'secret-key')
            && $request['lineNumber'] === 3000123456
            && $request['mobiles'] === ['09121234567']
            && $request['messageText'] === "پیام تست\nلغو11");
    }

    public function test_verify_send_uses_template_parameters_without_opt_out_suffix(): void
    {
        Http::fake([
            'api.sms.ir/v1/send/verify' => Http::response([
                'status' => 1,
                'message' => 'موفق',
                'data' => ['messageId' => 9988],
            ]),
        ]);

        $result = app(SmsIrSmsService::class)->sendPattern(
            '09121234567',
            '123456',
            ['code' => "654321\nلغو11"],
        );

        $this->assertTrue($result->success);
        $this->assertSame([9988], $result->messageIds);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.sms.ir/v1/send/verify'
            && $request['mobile'] === '09121234567'
            && $request['templateId'] === 123456
            && $request['parameters'] === [['name' => 'code', 'value' => '654321']]);
    }
}
