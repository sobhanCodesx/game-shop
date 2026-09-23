<?php

namespace App\Services\NexusAi;

use App\Services\NexusAiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class NexusAiRouter
{
    private const COOLDOWN_PREFIX = 'nexus-ai:provider-cooldown:';

    public function __construct(
        private readonly NexusAiProviderSettings $providers,
        private readonly NexusAiProviderClient $client,
        private readonly NexusAiContextService $context,
        private readonly NexusAiSettings $settings,
    ) {
    }

    public function chat(string $message, array $history): array
    {
        $config = $this->settings->all();
        $freeFirst = (bool) ($config['nexus_ai_free_first'] ?? true);
        $timeout = (int) ($config['nexus_ai_provider_timeout_seconds'] ?? 20);
        $maxTokens = (int) ($config['nexus_ai_max_output_tokens'] ?? 1000);
        $temperature = (float) ($config['nexus_ai_temperature'] ?? 0.65);
        $liveContext = $this->context->build($message)['context'] ?? '';
        $messages = $this->messages($message, $history, $liveContext);
        $attempted = [];

        foreach ($this->providers->ordered($freeFirst) as $provider) {
            $key = $provider['key'];
            if ($this->isCoolingDown($key)) {
                continue;
            }

            $attempted[] = $key;

            try {
                $answer = $this->client->chat(
                    $key,
                    $provider['settings'],
                    $messages,
                    $message,
                    $history,
                    $timeout,
                    $maxTokens,
                    $temperature,
                    $liveContext,
                );

                return [
                    'answer' => $answer,
                    'provider' => $key,
                    'model' => $provider['settings']['model'] ?? null,
                ];
            } catch (NexusAiProviderException $exception) {
                $this->coolDown($key, $exception);
                Log::warning('Nexus AI provider failed; falling back.', [
                    'provider' => $key,
                    'status' => $exception->status,
                ]);
            } catch (Throwable $exception) {
                $this->coolDown($key);
                report($exception);
            }
        }

        throw new RuntimeException(
            $attempted === []
                ? 'No configured Nexus AI provider is available.'
                : 'All configured Nexus AI providers failed.',
        );
    }

    public function health(): array
    {
        $config = $this->settings->all();
        $available = [];

        foreach ($this->providers->ordered((bool) ($config['nexus_ai_free_first'] ?? true)) as $provider) {
            if (! $this->isCoolingDown($provider['key'])) {
                $available[] = $provider['key'];
            }
        }

        return [
            'available' => $available !== [],
            'provider_count' => count($available),
        ];
    }

    private function messages(string $message, array $history, string $context): array
    {
        $system = <<<'PROMPT'
You are Nexus AI, the gaming assistant inside PlayNexus.
Answer in Persian when the user writes Persian, with natural friendly wording and clean Markdown.
Use PLAYNEXUS LIVE CONTEXT as the authoritative source for PlayNexus catalog, content, products, collections, radar and game relations.
Do not invent site prices, availability or facts. If the request is ambiguous, ask one focused clarification instead of giving a generic answer.
Be useful and reasonably detailed, but avoid unnecessary repetition.
PROMPT;

        if ($context !== '') {
            $system .= "\n\n".$context;
        }

        $messages = [['role' => 'system', 'content' => $system]];

        foreach (array_slice($history, -8) as $item) {
            $role = $item['role'] ?? null;
            $content = trim((string) ($item['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 3000)];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function coolDown(string $provider, ?NexusAiProviderException $exception = null): void
    {
        $seconds = match ($exception?->status) {
            401, 402, 403 => 300,
            429 => max(30, min(3600, $exception->retryAfter ?? 120)),
            500, 502, 503, 504 => 30,
            default => 15,
        };

        try {
            Cache::put(self::COOLDOWN_PREFIX.$provider, true, now()->addSeconds($seconds));
        } catch (Throwable) {
        }
    }

    private function isCoolingDown(string $provider): bool
    {
        try {
            return (bool) Cache::get(self::COOLDOWN_PREFIX.$provider, false);
        } catch (Throwable) {
            return false;
        }
    }
}
