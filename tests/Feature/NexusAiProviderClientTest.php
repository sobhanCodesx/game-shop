<?php

namespace Tests\Feature;

use App\Services\NexusAi\NexusAiProviderClient;
use App\Services\NexusAi\NexusAiProviderSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NexusAiProviderClientTest extends TestCase
{
    private array $messages = [
        ['role' => 'system', 'content' => 'You are Nexus AI.'],
        ['role' => 'user', 'content' => 'سلام'],
    ];

    public function test_workers_ai_uses_cloudflare_rest_api(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'پاسخ کلادفلر']]],
            ]),
        ]);

        $answer = (new NexusAiProviderClient())->chat(
            NexusAiProviderSettings::CLOUDFLARE_WORKERS_AI,
            [
                'account_id' => 'account-1',
                'api_token' => 'cf-token',
                'model' => '@cf/openai/gpt-oss-20b',
                'gateway_id' => '',
            ],
            $this->messages,
            'سلام',
            [],
            10,
            512,
            0.6,
        );

        $this->assertSame('پاسخ کلادفلر', $answer);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer cf-token')
            && $request['model'] === '@cf/openai/gpt-oss-20b'
            && $request['max_tokens'] === 512
        );
    }

    public function test_groq_uses_openai_compatible_endpoint_and_completion_token_limit(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'پاسخ Groq']]],
            ]),
        ]);

        $answer = (new NexusAiProviderClient())->chat(
            NexusAiProviderSettings::GROQ,
            [
                'api_key' => 'groq-token',
                'model' => 'openai/gpt-oss-120b',
                'base_url' => 'https://api.groq.com/openai/v1',
            ],
            $this->messages,
            'سلام',
            [],
            10,
            700,
            0.7,
        );

        $this->assertSame('پاسخ Groq', $answer);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request['max_completion_tokens'] === 700
            && ! isset($request['max_tokens'])
        );
    }

    public function test_gemini_is_routed_only_through_cloudflare_ai_gateway_rest_api(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'پاسخ Gemini']]],
            ]),
        ]);

        $answer = (new NexusAiProviderClient())->chat(
            NexusAiProviderSettings::GEMINI,
            [
                'account_id' => 'account-1',
                'api_token' => 'cf-token',
                'gateway_id' => 'playnexus-ai',
                'model' => 'google/gemini-3-flash',
            ],
            $this->messages,
            'سلام',
            [],
            10,
            800,
            0.5,
        );

        $this->assertSame('پاسخ Gemini', $answer);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.cloudflare.com/client/v4/accounts/account-1/ai/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer cf-token')
            && $request->hasHeader('cf-aig-gateway-id', 'playnexus-ai')
            && $request['model'] === 'google/gemini-3-flash'
        );
    }

    public function test_openai_uses_responses_api_without_forcing_temperature(): void
    {
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => 'پاسخ GPT',
            ]),
        ]);

        $answer = (new NexusAiProviderClient())->chat(
            NexusAiProviderSettings::OPENAI,
            [
                'api_key' => 'openai-token',
                'model' => 'gpt-test',
                'base_url' => 'https://api.openai.com/v1',
            ],
            $this->messages,
            'سلام',
            [],
            10,
            900,
            0.9,
        );

        $this->assertSame('پاسخ GPT', $answer);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.openai.com/v1/responses'
            && $request['max_output_tokens'] === 900
            && ! isset($request['temperature'])
        );
    }
}
