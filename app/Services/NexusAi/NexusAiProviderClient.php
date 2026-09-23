<?php

namespace App\Services\NexusAi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class NexusAiProviderClient
{
    public function chat(
        string $provider,
        array $settings,
        array $messages,
        string $message,
        array $history,
        int $timeout,
        int $maxTokens,
        float $temperature,
        string $context = '',
    ): string {
        return match ($provider) {
            NexusAiProviderSettings::CLOUDFLARE_WORKERS_AI => $this->cloudflareWorkers(
                $settings,
                $messages,
                $timeout,
                $maxTokens,
                $temperature,
            ),
            NexusAiProviderSettings::GROQ => $this->chatCompletions(
                $settings,
                $messages,
                $timeout,
                $maxTokens,
                $temperature,
                true,
            ),
            NexusAiProviderSettings::OPENAI_COMPATIBLE => $this->chatCompletions(
                $settings,
                $messages,
                $timeout,
                $maxTokens,
                $temperature,
            ),
            NexusAiProviderSettings::GEMINI,
            NexusAiProviderSettings::CLOUDFLARE_AI_GATEWAY => $this->cloudflareGateway(
                $settings,
                $messages,
                $timeout,
                $maxTokens,
                $temperature,
            ),
            NexusAiProviderSettings::OPENAI => $this->openAiResponses(
                $settings,
                $messages,
                $timeout,
                $maxTokens,
                $temperature,
            ),
            NexusAiProviderSettings::LOCAL_RELAY => $this->localRelay(
                $settings,
                $message,
                $history,
                $context,
                $timeout,
            ),
            default => throw new NexusAiProviderException('Unsupported Nexus AI provider.'),
        };
    }

    private function cloudflareWorkers(
        array $settings,
        array $messages,
        int $timeout,
        int $maxTokens,
        float $temperature,
    ): string {
        $accountId = rawurlencode((string) $settings['account_id']);
        $model = ltrim((string) $settings['model'], '/');
        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/run/{$model}";

        $request = $this->jsonRequest($timeout)->withToken((string) $settings['api_token']);
        if (filled($settings['gateway_id'] ?? null)) {
            $request = $request->withHeaders(['cf-aig-gateway-id' => (string) $settings['gateway_id']]);
        }

        $response = $request->post($url, [
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ]);

        $this->guardResponse($response);
        $answer = data_get($response->json(), 'result.response');

        return $this->guardAnswer($answer);
    }

    private function cloudflareGateway(
        array $settings,
        array $messages,
        int $timeout,
        int $maxTokens,
        float $temperature,
    ): string {
        $accountId = rawurlencode((string) $settings['account_id']);
        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/ai/v1/chat/completions";

        $response = $this->jsonRequest($timeout)
            ->withToken((string) $settings['api_token'])
            ->withHeaders(['cf-aig-gateway-id' => (string) $settings['gateway_id']])
            ->post($url, [
                'model' => (string) $settings['model'],
                'messages' => $messages,
                'max_tokens' => $maxTokens,
                'temperature' => $temperature,
            ]);

        $this->guardResponse($response);

        return $this->guardAnswer(data_get($response->json(), 'choices.0.message.content'));
    }

    private function chatCompletions(
        array $settings,
        array $messages,
        int $timeout,
        int $maxTokens,
        float $temperature,
        bool $preferCompletionTokens = false,
    ): string {
        $baseUrl = rtrim((string) $settings['base_url'], '/');
        $request = $this->jsonRequest($timeout);

        if (filled($settings['api_key'] ?? null)) {
            $request = $request->withToken((string) $settings['api_key']);
        }

        $payload = [
            'model' => (string) $settings['model'],
            'messages' => $messages,
            'temperature' => $temperature,
        ];
        $payload[$preferCompletionTokens ? 'max_completion_tokens' : 'max_tokens'] = $maxTokens;

        $response = $request->post($baseUrl.'/chat/completions', $payload);

        $this->guardResponse($response);

        return $this->guardAnswer(data_get($response->json(), 'choices.0.message.content'));
    }

    private function openAiResponses(
        array $settings,
        array $messages,
        int $timeout,
        int $maxTokens,
        float $temperature,
    ): string {
        $response = $this->jsonRequest($timeout)
            ->withToken((string) $settings['api_key'])
            ->post(rtrim((string) $settings['base_url'], '/').'/responses', [
                'model' => (string) $settings['model'],
                'input' => $messages,
                'max_output_tokens' => $maxTokens,
            ]);

        $this->guardResponse($response);
        $json = $response->json();

        if (filled($json['output_text'] ?? null)) {
            return $this->guardAnswer($json['output_text']);
        }

        $parts = [];
        foreach ((array) ($json['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    $parts[] = trim((string) $content['text']);
                }
            }
        }

        return $this->guardAnswer(implode("\n", $parts));
    }

    private function localRelay(
        array $settings,
        string $message,
        array $history,
        string $context,
        int $timeout,
    ): string {
        $response = $this->jsonRequest($timeout)
            ->post(rtrim((string) $settings['base_url'], '/').'/api/chat', [
                'message' => $message,
                'history' => $history,
                'context' => $context,
            ]);

        $this->guardResponse($response);

        return $this->guardAnswer(data_get($response->json(), 'answer'));
    }

    private function jsonRequest(int $timeout): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(min(5, max(2, $timeout)))
            ->timeout(max(3, $timeout));
    }

    private function guardResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $retryAfter = is_numeric($response->header('Retry-After'))
            ? (int) $response->header('Retry-After')
            : null;

        throw new NexusAiProviderException(
            'Provider request failed.',
            $response->status(),
            $retryAfter,
        );
    }

    private function guardAnswer(mixed $answer): string
    {
        $answer = is_string($answer) ? trim($answer) : '';

        if ($answer === '') {
            throw new NexusAiProviderException('Provider returned an empty answer.');
        }

        return $answer;
    }
}
