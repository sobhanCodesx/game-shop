<?php

namespace Tests\Feature;

use App\Models\NexusAiProviderSetting;
use App\Services\NexusAi\NexusAiLiveWebSearchService;
use App\Services\NexusAi\NexusAiProviderSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NexusAiLiveWebSearchServiceTest extends TestCase
{
    public function test_it_uses_cloudflare_responses_web_search_for_fresh_queries(): void
    {
        $providers = $this->providersWith('playnexus-ai');

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/responses' => Http::response([
                'output' => [[
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Rockstar currently lists the launch date as November 19, 2026.',
                        'annotations' => [[
                            'type' => 'url_citation',
                            'title' => 'Rockstar Games',
                            'url' => 'https://www.rockstargames.com/example',
                        ]],
                    ]],
                ]],
            ]),
        ]);

        $result = (new NexusAiLiveWebSearchService($providers))
            ->resolve('تاریخ انتشار GTA VI الان کیه؟', 'release');

        $this->assertTrue($result['required']);
        $this->assertTrue($result['verified']);
        $this->assertStringContainsString('November 19, 2026', $result['context']);
        $this->assertSame('https://www.rockstargames.com/example', $result['sources'][0]['url']);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/responses'
            && $request->hasHeader('Authorization', 'Bearer cf-token')
            && $request->hasHeader('cf-aig-gateway-id', 'playnexus-ai')
            && $request['model'] === 'openai/gpt-5-mini'
            && data_get($request->data(), 'tools.0.type') === 'web_search_preview'
        );
    }

    public function test_it_fails_closed_instead_of_using_stale_memory_when_search_fails(): void
    {
        $providers = $this->providersWith('');

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/responses' => Http::response([
                'errors' => [['message' => 'Unified Billing unavailable']],
            ], 402),
        ]);

        $service = new NexusAiLiveWebSearchService($providers);
        $result = $service->resolve('آخرین پچ بازی چی بوده؟', 'news');

        $this->assertTrue($result['required']);
        $this->assertFalse($result['verified']);
        $this->assertSame('web_search_http_402', $result['error']);
        $this->assertStringContainsString('اطلاعات به‌روز', $service->unavailableAnswer('آخرین پچ بازی چی بوده؟'));
    }

    private function providersWith(string $gatewayId): NexusAiProviderSettings
    {
        config()->set('services.cloudflare_ai.account_id', 'account-1');
        config()->set('services.cloudflare_ai.api_token', 'cf-token');
        config()->set('services.cloudflare_ai.gateway_id', $gatewayId);
        config()->set('services.cloudflare_ai.model', '@cf/openai/gpt-oss-120b');

        if (Schema::hasTable('nexus_ai_provider_settings')) {
            NexusAiProviderSetting::query()->updateOrCreate(
                ['provider' => NexusAiProviderSettings::CLOUDFLARE_WORKERS_AI],
                [
                    'settings' => [
                        'account_id' => 'account-1',
                        'api_token' => 'cf-token',
                        'model' => '@cf/openai/gpt-oss-120b',
                        'gateway_id' => $gatewayId,
                    ],
                    'enabled' => true,
                    'priority' => 10,
                ],
            );
        }

        return app(NexusAiProviderSettings::class);
    }
}
