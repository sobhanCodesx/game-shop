<?php

namespace App\Services\Telegram;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class TelegramMtProtoService
{
    public function __construct(
        private readonly TelegramBotSettings $settings,
    ) {}

    public function isConfigured(): bool
    {
        $settings = $this->settings->resolved();

        return ($settings['mtproto_enabled'] ?? false)
            && (int) ($settings['mtproto_api_id'] ?? 0) > 0
            && filled($settings['mtproto_api_hash'] ?? null)
            && filled($settings['bot_token'] ?? null)
            && class_exists(API::class);
    }

    public function health(): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('MTProto هنوز کامل تنظیم نشده است.');
        }

        try {
            $api = $this->client();
            $me = $api->getSelf();

            if (! is_array($me) || ! ($me['bot'] ?? false)) {
                throw new RuntimeException('MTProto session به Bot معتبر متصل نشد.');
            }

            $configuredBotId = trim((string) ($this->settings->resolved()['bot_id'] ?? ''));
            if ($configuredBotId !== '' && isset($me['id']) && ! hash_equals($configuredBotId, (string) $me['id'])) {
                throw new RuntimeException('MTProto session به Bot دیگری متصل شده است.');
            }

            $this->settings->markMtProtoHealthy();

            return [
                'ok' => true,
                'bot_id' => isset($me['id']) ? (string) $me['id'] : null,
                'username' => $me['username'] ?? null,
                'session_path' => $this->sessionPath(),
            ];
        } catch (Throwable $exception) {
            $this->settings->markMtProtoError($exception->getMessage());
            throw $exception;
        }
    }

    public function download(
        string $fileId,
        ?string $fileName = null,
        ?string $mime = null,
        ?int $expectedSize = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'این فایل از محدودیت Bot API بزرگ‌تر است و MTProto هنوز فعال نشده. '
                .'API ID و API Hash را یک‌بار در پنل Telegram Bot وارد کن.',
            );
        }

        $directory = storage_path('app/telegram-bot/tmp');
        File::ensureDirectoryExists($directory);

        $safeName = $this->safeFileName($fileName, $mime);
        $path = $directory.'/mtproto-'.bin2hex(random_bytes(10)).'-'.$safeName;

        try {
            @set_time_limit(0);

            $api = $this->client();
            $api->downloadToFile($fileId, $path);

            if (! File::isFile($path)) {
                throw new RuntimeException('MTProto فایل دانلودشده را روی دیسک ایجاد نکرد.');
            }

            $size = (int) File::size($path);
            if ($size < 1) {
                throw new RuntimeException('فایل دانلودشده از Telegram خالی است.');
            }

            if ($expectedSize && $size !== $expectedSize) {
                throw new RuntimeException(
                    "حجم فایل MTProto با Telegram تطابق ندارد ({$size} / {$expectedSize}).",
                );
            }

            $this->settings->markMtProtoHealthy();

            return [
                'path' => $path,
                'name' => $safeName,
                'mime' => $mime ?: (File::mimeType($path) ?: 'application/octet-stream'),
                'size' => $size,
                'transport' => 'mtproto',
            ];
        } catch (Throwable $exception) {
            File::delete($path);
            $this->settings->markMtProtoError($exception->getMessage());
            throw new RuntimeException(
                'دانلود فایل بزرگ از Telegram با MTProto ناموفق بود: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function client(): API
    {
        $settings = $this->settings->resolved();
        $apiId = (int) ($settings['mtproto_api_id'] ?? 0);
        $apiHash = trim((string) ($settings['mtproto_api_hash'] ?? ''));
        $token = trim((string) ($settings['bot_token'] ?? ''));

        if ($apiId < 1 || $apiHash === '' || $token === '') {
            throw new RuntimeException('MTProto credentials ناقص است.');
        }

        File::ensureDirectoryExists($this->sessionDirectory());

        $madelineSettings = new Settings();
        $madelineSettings->getAppInfo()
            ->setApiId($apiId)
            ->setApiHash($apiHash)
            ->setShowPrompt(false);

        $api = new API($this->sessionPath(), $madelineSettings);
        $api->botLogin($token);

        return $api;
    }

    private function sessionDirectory(): string
    {
        return storage_path('app/telegram-mtproto');
    }

    private function sessionPath(): string
    {
        return $this->sessionDirectory().'/playnexus-bot.madeline';
    }

    private function safeFileName(?string $fileName, ?string $mime): string
    {
        $name = trim((string) $fileName);
        if ($name === '') {
            $extension = match (strtolower(trim((string) $mime))) {
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                default => 'bin',
            };
            $name = 'telegram-file.'.$extension;
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '-', basename($name)) ?: 'telegram-file.bin';

        return mb_substr($name, 0, 180);
    }
}
