<?php

namespace App\Services\Telegram;

use App\Services\ContentAgentMediaService;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class TelegramMediaTransferService
{
    public function __construct(
        private readonly TelegramApiClient $telegram,
        private readonly TelegramBotSettings $settings,
        private readonly ContentAgentMediaService $media,
        private readonly TelegramMtProtoService $mtproto,
    ) {}

    public function attach(
        array $telegramFile,
        string $resource,
        int $id,
        string $slot,
        ?string $alt = null,
    ): array {
        $settings = $this->settings->resolved();
        if (! ($settings['write_enabled'] ?? false) || ! ($settings['media_enabled'] ?? false)) {
            throw new RuntimeException('Telegram media writes are disabled.');
        }

        $fileId = trim((string) ($telegramFile['file_id'] ?? ''));
        if ($fileId === '') {
            throw new RuntimeException('Telegram file_id is missing.');
        }

        $fileSize = isset($telegramFile['file_size'])
            ? max(0, (int) $telegramFile['file_size'])
            : null;
        $cloudLimit = (int) config('telegram_bot.max_download_bytes', 20 * 1024 * 1024);

        if ($fileSize !== null && $fileSize > $cloudLimit) {
            $download = $this->downloadLargeFile($telegramFile, $fileId, $fileSize);
        } else {
            try {
                $download = $this->telegram->downloadFile(
                    $fileId,
                    $telegramFile['file_name'] ?? null,
                    $telegramFile['mime'] ?? null,
                    $fileSize,
                );
            } catch (Throwable $exception) {
                if (! $this->isCloudSizeLimitError($exception)) {
                    throw $exception;
                }

                $download = $this->downloadLargeFile($telegramFile, $fileId, $fileSize);
            }
        }

        try {
            @set_time_limit(0);
            @ignore_user_abort(true);

            return $this->media->attachLocalFile([
                'resource' => $resource,
                'id' => $id,
                'slot' => $slot,
                'name' => (string) ($download['name'] ?? $telegramFile['file_name'] ?? 'telegram-file.bin'),
                'mime' => (string) ($download['mime'] ?? $telegramFile['mime'] ?? 'application/octet-stream'),
                'alt' => $alt,
                'duration' => isset($telegramFile['duration'])
                    ? (int) $telegramFile['duration']
                    : null,
            ], (string) $download['path']);
        } finally {
            File::delete((string) ($download['path'] ?? ''));
        }
    }

    private function downloadLargeFile(
        array $telegramFile,
        string $fileId,
        ?int $fileSize,
    ): array {
        if (! $this->mtproto->isConfigured()) {
            throw new RuntimeException(
                'این فایل بزرگ‌تر از محدودیت Bot API است. '
                .'فقط یک‌بار API ID و API Hash را در پنل Telegram Bot وارد و MTProto را فعال کن؛ '
                .'بعد از آن فایل‌های بزرگ خودکار دریافت می‌شوند.',
            );
        }

        return $this->mtproto->download(
            $fileId,
            $telegramFile['file_name'] ?? null,
            $telegramFile['mime'] ?? null,
            $fileSize,
        );
    }

    private function isCloudSizeLimitError(Throwable $exception): bool
    {
        $message = mb_strtolower($exception->getMessage());

        return str_contains($message, 'file is too big')
            || str_contains($message, 'file too big')
            || str_contains($message, 'too large')
            || str_contains($message, 'larger than the configured download limit')
            || str_contains($message, 'exceeds the configured limit');
    }
}
