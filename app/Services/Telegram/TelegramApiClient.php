<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TelegramApiClient
{
    public function __construct(
        private readonly TelegramBotSettings $settings,
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
            'url' => route('telegram.webhook'),
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

        $url = rtrim((string) ($settings['api_base_url'] ?? 'https://api.telegram.org'), '/')
            .'/bot'.$token.'/'.$method;

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout((int) config('telegram_bot.connect_timeout', 10))
                ->timeout((int) config('telegram_bot.request_timeout', 30))
                ->retry(2, 250, throw: false)
                ->withOptions($this->httpOptions())
                ->post($url, $payload);
        } catch (Throwable $exception) {
            throw new RuntimeException('Telegram API transport failed: '.$exception->getMessage(), 0, $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Telegram API returned HTTP '.$response->status().'.');
        }

        $json = $response->json();
        if (! is_array($json) || ! ($json['ok'] ?? false)) {
            $description = is_array($json) ? (string) ($json['description'] ?? 'Unknown Telegram error') : 'Invalid Telegram response';
            throw new RuntimeException('Telegram API error: '.$description);
        }

        $result = $json['result'] ?? [];

        return is_array($result) ? $result : ['value' => $result];
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
        $apiBase = rtrim((string) ($settings['api_base_url'] ?? 'https://api.telegram.org'), '/');
        $fileBase = preg_replace('#/bot$#', '', $apiBase) ?: 'https://api.telegram.org';
        $url = $fileBase.'/file/bot'.$token.'/'.$path;

        $directory = storage_path('app/telegram-bot/tmp');
        File::ensureDirectoryExists($directory);

        $name = basename((string) ($originalName ?: basename($path)));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'telegram-file.bin';
        $target = $directory.'/'.Str::uuid().'-'.$name;

        try {
            $response = Http::connectTimeout((int) config('telegram_bot.connect_timeout', 10))
                ->timeout(max(60, (int) config('telegram_bot.request_timeout', 30)))
                ->retry(2, 300, throw: false)
                ->withOptions([...$this->httpOptions(), 'sink' => $target])
                ->get($url);

            if (! $response->successful() || ! File::isFile($target)) {
                File::delete($target);
                throw new RuntimeException('Telegram file download failed with HTTP '.$response->status().'.');
            }
        } catch (Throwable $exception) {
            File::delete($target);
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Telegram file download failed: '.$exception->getMessage(), 0, $exception);
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

    private function httpOptions(): array
    {
        $proxy = $this->settings->proxyUrl();

        return $proxy ? ['proxy' => $proxy] : [];
    }
}
