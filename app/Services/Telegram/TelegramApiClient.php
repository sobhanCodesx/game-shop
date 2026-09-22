<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TelegramApiClient
{
    private ?string $lastTransport = null;

    public function __construct(
        private readonly TelegramBotSettings $settings,
        private readonly TelegramTransportManager $transports,
    ) {}

    public function getMe(): array
    {
        return $this->request('getMe');
    }

    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo');
    }

    public function registerWebhook(): array
    {
        $settings = $this->settings->resolved();
        $secret = (string) ($settings['webhook_secret'] ?? '');
        if ($secret === '') {
            $secret = $this->settings->rotateWebhookSecret();
        }

        $result = $this->request('setWebhook', [
            'url' => $this->webhookUrl($settings),
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => false,
            'max_connections' => 10,
        ]);

        $this->request('setMyCommands', [
            'commands' => [
                ['command' => 'menu', 'description' => 'منوی مدیریت PlayNexus'],
                ['command' => 'status', 'description' => 'وضعیت بات و اتصال'],
                ['command' => 'search', 'description' => 'جستجو در محتوای PlayNexus'],
                ['command' => 'list', 'description' => 'فهرست منابع'],
                ['command' => 'get', 'description' => 'نمایش یک رکورد'],
                ['command' => 'assets', 'description' => 'مدیای یک رکورد'],
                ['command' => 'media', 'description' => 'آماده‌سازی دریافت مدیا'],
                ['command' => 'tool', 'description' => 'اجرای ابزار پیشرفته'],
                ['command' => 'help', 'description' => 'راهنمای کامل'],
                ['command' => 'cancel', 'description' => 'لغو عملیات جاری'],
            ],
        ]);

        $this->settings->markWebhookRegistered();

        return $result;
    }

    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook', ['drop_pending_updates' => false]);
    }

    public function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null): array
    {
        $payload = [
            'chat_id' => (string) $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_notification' => false,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('sendMessage', $payload);
    }

    public function editMessageText(
        string|int $chatId,
        int $messageId,
        string $text,
        ?array $replyMarkup = null,
    ): array {
        $payload = [
            'chat_id' => (string) $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        return $this->request('editMessageText', $payload);
    }

    public function answerCallbackQuery(string $callbackId, ?string $text = null): array
    {
        return $this->request('answerCallbackQuery', array_filter([
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => false,
        ], fn ($value) => $value !== null));
    }

    public function sendChatAction(string|int $chatId, string $action = 'typing'): array
    {
        return $this->request('sendChatAction', [
            'chat_id' => (string) $chatId,
            'action' => $action,
        ]);
    }

    public function request(string $method, array $payload = []): array
    {
        $settings = $this->settings->resolved();
        $token = trim((string) ($settings['bot_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('Telegram bot token is not configured.');
        }

        $failures = [];

        foreach ($this->transports->candidates() as $candidate) {
            try {
                $response = $this->http($candidate)
                    ->post($this->transports->apiUrl($candidate, $method, $token), $payload);
            } catch (Throwable $exception) {
                $failures[] = $candidate['name'].': transport failure';
                continue;
            }

            $telegramError = $this->telegramError($response);
            if ($telegramError !== null) {
                throw new RuntimeException('Telegram API error: '.$telegramError);
            }

            if (! $response->successful()) {
                $failures[] = $candidate['name'].': HTTP '.$response->status();
                continue;
            }

            $json = $response->json();
            if (! is_array($json) || ! ($json['ok'] ?? false)) {
                $failures[] = $candidate['name'].': invalid response';
                continue;
            }

            $this->lastTransport = (string) $candidate['name'];
            $result = $json['result'] ?? [];

            return is_array($result) ? $result : ['value' => $result];
        }

        throw new RuntimeException(
            'Telegram API is unavailable through the configured transports'
            .($failures ? ' ('.implode(', ', $failures).').' : '.'),
        );
    }

    public function downloadFile(
        string $fileId,
        ?string $originalName = null,
        ?string $mime = null,
        ?int $expectedSize = null,
    ): array {
        $file = $this->request('getFile', ['file_id' => $fileId]);
        $size = (int) ($file['file_size'] ?? $expectedSize ?? 0);
        $max = (int) config('telegram_bot.max_download_bytes', 20 * 1024 * 1024);

        if ($size > 0 && $size > $max) {
            throw new RuntimeException('Telegram file is larger than the configured download limit of '.round($max / 1048576, 1).' MB.');
        }

        $path = trim((string) ($file['file_path'] ?? ''));
        if ($path === '') {
            throw new RuntimeException('Telegram did not return a downloadable file path.');
        }

        $settings = $this->settings->resolved();
        $token = trim((string) ($settings['bot_token'] ?? ''));

        $directory = storage_path('app/telegram-bot/tmp');
        File::ensureDirectoryExists($directory);

        $name = basename((string) ($originalName ?: basename($path)));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'telegram-file.bin';
        $target = $directory.'/'.Str::uuid().'-'.$name;
        $failures = [];

        foreach ($this->transports->candidates() as $candidate) {
            File::delete($target);

            try {
                $options = $candidate['options'];
                $options['sink'] = $target;

                $response = Http::connectTimeout((int) config('telegram_bot.connect_timeout', 10))
                    ->timeout(max(60, (int) config('telegram_bot.request_timeout', 30)))
                    ->withHeaders($candidate['headers'])
                    ->withOptions($options)
                    ->get($this->transports->fileUrl($candidate, $path, $token));

                if (! $response->successful() || ! File::isFile($target)) {
                    $failures[] = $candidate['name'].': HTTP '.$response->status();
                    File::delete($target);
                    continue;
                }

                $this->lastTransport = (string) $candidate['name'];
                break;
            } catch (Throwable $exception) {
                $failures[] = $candidate['name'].': transport failure';
                File::delete($target);
            }
        }

        if (! File::isFile($target)) {
            throw new RuntimeException(
                'Telegram file download failed through the configured transports'
                .($failures ? ' ('.implode(', ', $failures).').' : '.'),
            );
        }

        $actualSize = File::size($target);
        if ($actualSize > $max) {
            File::delete($target);
            throw new RuntimeException('Downloaded Telegram file exceeds the configured limit.');
        }

        return [
            'path' => $target,
            'name' => $name,
            'mime' => $mime ?: 'application/octet-stream',
            'size' => $actualSize,
            'telegram_file' => $file,
        ];
    }

    public function configuredWebhookUrl(): string
    {
        return $this->webhookUrl($this->settings->resolved());
    }

    private function webhookUrl(array $settings): string
    {
        $mode = (string) ($settings['transport_mode'] ?? 'auto');
        $relayBaseUrl = rtrim(trim((string) ($settings['relay_base_url'] ?? '')), '/');
        $relayKey = trim((string) ($settings['relay_key'] ?? ''));

        if (
            in_array($mode, ['auto', 'relay'], true)
            && $relayBaseUrl !== ''
            && $relayKey !== ''
        ) {
            return $relayBaseUrl.'/webhook';
        }

        return route('telegram.webhook');
    }

    public function lastTransport(): ?string
    {
        return $this->lastTransport;
    }

    private function http(array $candidate): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout((int) config('telegram_bot.connect_timeout', 10))
            ->timeout((int) config('telegram_bot.request_timeout', 30))
            ->withHeaders($candidate['headers'])
            ->withOptions($candidate['options']);
    }

    private function telegramError(Response $response): ?string
    {
        $json = $response->json();

        if (! is_array($json) || ($json['ok'] ?? null) !== false || ! isset($json['description'])) {
            return null;
        }

        return Str::limit((string) $json['description'], 500);
    }
}
