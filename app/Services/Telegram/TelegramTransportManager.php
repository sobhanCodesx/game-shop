<?php

namespace App\Services\Telegram;

use RuntimeException;

final class TelegramTransportManager
{
    public function __construct(
        private readonly TelegramBotSettings $settings,
    ) {}

    /**
     * @return list<array{name:string,base_url:string,headers:array<string,string>,options:array<string,mixed>}>
     */
    public function candidates(): array
    {
        $settings = $this->settings->resolved();
        $mode = (string) ($settings['transport_mode'] ?? 'auto');

        $direct = $this->directCandidate($settings);
        $proxy = $this->proxyCandidate($settings);
        $relay = $this->relayCandidate($settings);

        return match ($mode) {
            'direct' => [$direct],
            'proxy' => $proxy ? [$proxy] : throw new RuntimeException('Telegram proxy transport is selected but no proxy is configured.'),
            'relay' => $relay ? [$relay] : throw new RuntimeException('Telegram relay transport is selected but relay URL/key is not configured.'),
            'auto' => array_values(array_filter([$relay, $proxy, $direct])),
            default => throw new RuntimeException('Unknown Telegram transport mode.'),
        };
    }

    public function apiUrl(array $candidate, string $method, string $token): string
    {
        $method = trim($method);
        if ($method === '' || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,80}$/', $method)) {
            throw new RuntimeException('Invalid Telegram Bot API method name.');
        }

        if ($candidate['name'] === 'relay') {
            return rtrim($candidate['base_url'], '/').'/api/'.$method;
        }

        return rtrim($candidate['base_url'], '/').'/bot'.$token.'/'.$method;
    }

    public function fileUrl(array $candidate, string $path, string $token): string
    {
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            throw new RuntimeException('Invalid Telegram file path.');
        }

        $encodedPath = implode('/', array_map(
            static fn (string $part): string => rawurlencode($part),
            explode('/', $path),
        ));

        if ($candidate['name'] === 'relay') {
            return rtrim($candidate['base_url'], '/').'/file/'.$encodedPath;
        }

        return rtrim($candidate['base_url'], '/').'/file/bot'.$token.'/'.$encodedPath;
    }

    private function directCandidate(array $settings): array
    {
        return [
            'name' => 'direct',
            'base_url' => rtrim((string) ($settings['api_base_url'] ?? 'https://api.telegram.org'), '/'),
            'headers' => [],
            'options' => [],
        ];
    }

    private function proxyCandidate(array $settings): ?array
    {
        $proxy = $this->settings->proxyUrl();
        if ($proxy === null) {
            return null;
        }

        return [
            'name' => 'proxy',
            'base_url' => rtrim((string) ($settings['api_base_url'] ?? 'https://api.telegram.org'), '/'),
            'headers' => [],
            'options' => ['proxy' => $proxy],
        ];
    }

    private function relayCandidate(array $settings): ?array
    {
        $baseUrl = rtrim(trim((string) ($settings['relay_base_url'] ?? '')), '/');
        $relayKey = trim((string) ($settings['relay_key'] ?? ''));

        if ($baseUrl === '' || $relayKey === '') {
            return null;
        }

        return [
            'name' => 'relay',
            'base_url' => $baseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.trim((string) ($settings['bot_token'] ?? '')),
                'X-PlayNexus-Relay-Key' => $relayKey,
            ],
            'options' => [],
        ];
    }
}
