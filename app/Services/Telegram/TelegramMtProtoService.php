<?php

namespace App\Services\Telegram;

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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

        $startedAt = microtime(true);

        try {
            $result = $this->withClient(function (API $api): array {
                $me = $api->getSelf();

                if (! is_array($me) || ! ($me['bot'] ?? false)) {
                    throw new RuntimeException('MTProto session به Bot معتبر متصل نشد.');
                }

                $configuredBotId = trim((string) ($this->settings->resolved()['bot_id'] ?? ''));
                if (
                    $configuredBotId !== ''
                    && isset($me['id'])
                    && ! hash_equals($configuredBotId, (string) $me['id'])
                ) {
                    throw new RuntimeException('MTProto session به Bot دیگری متصل شده است.');
                }

                return [
                    'ok' => true,
                    'bot_id' => isset($me['id']) ? (string) $me['id'] : null,
                    'username' => $me['username'] ?? null,
                ];
            });

            $this->settings->markMtProtoHealthy();

            return [
                ...$result,
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'session' => basename($this->sessionPath($this->credentials())),
                'release' => defined(API::class.'::RELEASE') ? API::RELEASE : 'unknown',
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
                .'API ID و API Hash را فقط یک‌بار در پنل Telegram Bot وارد کن.',
            );
        }

        if ($fileId === '') {
            throw new RuntimeException('Telegram file_id برای دانلود فایل وجود ندارد.');
        }

        $max = $this->maxDownloadBytes();
        if ($expectedSize !== null && $expectedSize > $max) {
            throw new RuntimeException(
                'حجم فایل از سقف فعلی PlayNexus بیشتر است (حداکثر '
                .round($max / 1048576, 1).' MB).',
            );
        }

        $directory = storage_path('app/telegram-mtproto/tmp');
        File::ensureDirectoryExists($directory);

        $safeName = $this->safeFileName($fileName, $mime);
        $path = $directory.'/'.Str::uuid().'-'.$safeName;

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $this->withClient(function (API $api) use ($fileId, $path): void {
                // MadelineProto accepts Bot API file_id values directly.
                $api->downloadToFile($fileId, $path);
            });

            if (! File::isFile($path)) {
                throw new RuntimeException('MTProto فایل دانلودشده را روی دیسک ایجاد نکرد.');
            }

            $size = (int) File::size($path);
            if ($size < 1) {
                throw new RuntimeException('فایل دانلودشده از Telegram خالی است.');
            }
            if ($size > $max) {
                throw new RuntimeException('فایل دانلودشده از سقف مجاز PlayNexus بزرگ‌تر است.');
            }
            if ($expectedSize !== null && $expectedSize > 0 && $size !== $expectedSize) {
                throw new RuntimeException(
                    "حجم فایل MTProto با Telegram تطابق ندارد ({$size} / {$expectedSize}).",
                );
            }

            $actualMime = $mime ?: (File::mimeType($path) ?: 'application/octet-stream');
            $this->settings->markMtProtoHealthy();

            return [
                'path' => $path,
                'name' => $safeName,
                'mime' => $actualMime,
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

    public function maxDownloadBytes(): int
    {
        return min(
            (int) config('telegram_bot.mtproto.max_download_bytes', 2 * 1024 * 1024 * 1024),
            (int) config('content_agent.uploads.max_size', 300 * 1024 * 1024),
        );
    }

    private function withClient(callable $callback): mixed
    {
        $credentials = $this->credentials();
        File::ensureDirectoryExists($this->sessionDirectory());

        $lock = fopen($this->sessionDirectory().'/session.lock', 'c+');
        if (! is_resource($lock)) {
            throw new RuntimeException('قفل session MTProto ساخته نشد.');
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('قفل session MTProto قابل دریافت نیست.');
        }

        $api = null;

        try {
            $madelineSettings = new Settings();
            $madelineSettings->getAppInfo()
                ->setApiId((int) $credentials['api_id'])
                ->setApiHash((string) $credentials['api_hash'])
                ->setShowPrompt(false);

            $madelineSettings->getLogger()
                ->setType(Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram-mtproto.log'))
                ->setLevel(Logger::ERROR);

            $api = new API($this->sessionPath($credentials), $madelineSettings);
            $api->botLogin((string) $credentials['bot_token']);

            return $callback($api);
        } finally {
            $api = null;
            gc_collect_cycles();
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function credentials(): array
    {
        $settings = $this->settings->resolved();
        $apiId = (int) ($settings['mtproto_api_id'] ?? 0);
        $apiHash = trim((string) ($settings['mtproto_api_hash'] ?? ''));
        $token = trim((string) ($settings['bot_token'] ?? ''));

        if (! ($settings['mtproto_enabled'] ?? false)) {
            throw new RuntimeException('MTProto برای فایل‌های بزرگ خاموش است.');
        }
        if ($apiId < 1 || $apiHash === '' || $token === '') {
            throw new RuntimeException('MTProto API ID، API Hash یا Bot Token ناقص است.');
        }

        return [
            'api_id' => $apiId,
            'api_hash' => $apiHash,
            'bot_token' => $token,
        ];
    }

    private function sessionDirectory(): string
    {
        return storage_path('app/telegram-mtproto');
    }

    private function sessionPath(array $credentials): string
    {
        $fingerprint = substr(hash(
            'sha256',
            $credentials['api_id'].'|'.$credentials['api_hash'].'|'.$credentials['bot_token'],
        ), 0, 20);

        return $this->sessionDirectory().'/session-'.$fingerprint.'.madeline';
    }

    private function safeFileName(?string $fileName, ?string $mime): string
    {
        $name = trim((string) $fileName);
        if ($name === '') {
            $extension = match (strtolower(trim((string) $mime))) {
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
                'video/quicktime' => 'mov',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'bin',
            };
            $name = 'telegram-file.'.$extension;
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/u', '-', basename($name))
            ?: 'telegram-file.bin';

        return mb_substr($name, 0, 180);
    }
}
