<?php

namespace App\Services\Telegram;

use App\Services\ContentAgentMediaService;
use Illuminate\Http\UploadedFile;
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

        $fileId = (string) ($telegramFile['file_id'] ?? '');
        if ($fileId === '') {
            throw new RuntimeException('Telegram file_id is missing.');
        }

        $fileSize = isset($telegramFile['file_size']) ? (int) $telegramFile['file_size'] : null;
        $maxBotApiBytes = (int) config('telegram_bot.max_download_bytes', 20 * 1024 * 1024);

        if ($fileSize !== null && $fileSize > $maxBotApiBytes) {
            $download = $this->mtproto->download(
                $fileId,
                $telegramFile['file_name'] ?? null,
                $telegramFile['mime'] ?? null,
                $fileSize,
            );
        } else {
            try {
                $download = $this->telegram->downloadFile(
                    $fileId,
                    $telegramFile['file_name'] ?? null,
                    $telegramFile['mime'] ?? null,
                    $fileSize,
                );
            } catch (Throwable $exception) {
                $message = mb_strtolower($exception->getMessage());
                $tooBig = str_contains($message, 'file is too big')
                    || str_contains($message, 'file too big')
                    || str_contains($message, 'too large');

                if (! $tooBig) {
                    throw $exception;
                }

                $download = $this->mtproto->download(
                    $fileId,
                    $telegramFile['file_name'] ?? null,
                    $telegramFile['mime'] ?? null,
                    $fileSize,
                );
            }
        }

        $uploadId = null;
        try {
            $size = (int) $download['size'];
            $chunkSize = min(
                max(64 * 1024, (int) config('content_agent.uploads.max_chunk_size', 2 * 1024 * 1024)),
                2 * 1024 * 1024,
            );
            $totalChunks = (int) ceil($size / $chunkSize);
            $sha256 = hash_file('sha256', (string) $download['path']);

            $upload = $this->media->startUpload([
                'resource' => $resource,
                'id' => $id,
                'slot' => $slot,
                'name' => (string) $download['name'],
                'mime' => (string) $download['mime'],
                'size' => $size,
                'chunk_size' => $chunkSize,
                'total_chunks' => $totalChunks,
                'sha256' => $sha256,
                'alt' => $alt,
                'duration' => isset($telegramFile['duration']) ? (int) $telegramFile['duration'] : null,
            ]);
            $uploadId = (string) $upload['upload_id'];

            $source = fopen((string) $download['path'], 'rb');
            if (! is_resource($source)) {
                throw new RuntimeException('Could not open the downloaded Telegram file.');
            }

            try {
                for ($index = 0; $index < $totalChunks; $index++) {
                    $bytes = fread($source, $chunkSize);
                    if ($bytes === false || $bytes === '') {
                        throw new RuntimeException("Could not read Telegram file chunk {$index}.");
                    }

                    $chunkPath = tempnam(storage_path('app/telegram-bot/tmp'), 'chunk-');
                    if ($chunkPath === false) {
                        throw new RuntimeException('Could not allocate temporary chunk storage.');
                    }

                    try {
                        File::put($chunkPath, $bytes);
                        $chunk = new UploadedFile(
                            $chunkPath,
                            'telegram-chunk-'.$index.'.bin',
                            'application/octet-stream',
                            null,
                            true,
                        );
                        $this->media->uploadChunkFile([
                            'upload_id' => $uploadId,
                            'chunk_index' => $index,
                        ], $chunk);
                    } finally {
                        File::delete($chunkPath);
                    }
                }
            } finally {
                fclose($source);
            }

            return $this->media->completeUpload(['upload_id' => $uploadId]);
        } catch (Throwable $exception) {
            if ($uploadId) {
                try {
                    $this->media->abortUpload(['upload_id' => $uploadId]);
                } catch (Throwable) {
                }
            }

            throw $exception;
        } finally {
            File::delete((string) ($download['path'] ?? ''));
        }
    }
}
