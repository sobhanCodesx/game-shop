<?php

namespace App\Services\Telegram;

use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TelegramMtProtoClient
{
    public function __construct(
        private readonly TelegramBotSettings $settings,
    ) {}

    public function configured(): bool
    {
        $settings = $this->settings->resolved();

        return ($settings['mtproto_enabled'] ?? false)
            && (int) ($settings['mtproto_api_id'] ?? 0) > 0
            && filled($settings['mtproto_api_hash'] ?? null)
            && filled($settings['bot_token'] ?? null);
    }

    public function testConnection(): array
    {
        $resolved = $this->credentials();
        $startedAt = microtime(true);

        return $this->withClient(function (API $api) use ($startedAt, $resolved): array {
            $self = $api->getSelf();
            if (! is_array($self) || ! ($self['bot'] ?? false)) {
                throw new RuntimeException('MTProto session did not authenticate as a Telegram bot.');
            }

            return [
                'ok' => true,
                'bot_id' => isset($self['id']) ? (string) $self['id'] : null,
                'bot_username' => isset($self['username']) ? (string) $self['username'] : null,
                'api_id' => (int) $resolved['api_id'],
                'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'session_path' => basename($this->sessionPath($resolved)),
            ];
        });
    }

    public function downloadFile(
        string $fileId,
        ?string $originalName,
        ?string $mime,
        ?int $expectedSize,
    ): array {
        if ($fileId === '') {
            throw new RuntimeException('Telegram file_id is missing.');
        }

        $max = min(
            (int) config('telegram_bot.mtproto.max_download_bytes', 2 * 1024 * 1024 * 1024),
            (int) config('content_agent.uploads.max_size', 300 * 1024 * 1024),
        );

        if ($expectedSize !== null && $expectedSize > $max) {
            throw new RuntimeException(
                'Telegram file exceeds the PlayNexus large-file limit of '
                .round($max / 1048576, 1).' MB.'
            );
        }

        $directory = storage_path('app/telegram-mtproto/tmp');
        File::ensureDirectoryExists($directory);

        $name = basename((string) ($originalName ?: 'telegram-large-file.bin'));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'telegram-large-file.bin';
        $target = $directory.'/'.Str::uuid().'-'.$name;

        @set_time_limit(0);
        @ignore_user_abort(true);

        try {
            $this->withClient(function (API $api) use ($fileId, $target): void {
                $api->downloadToFile($fileId, $target);
            });

            if (! File::isFile($target)) {
                throw new RuntimeException('MTProto download did not create the expected file.');
            }

            $size = (int) File::size($target);
            if ($size < 1) {
                throw new RuntimeException('MTProto downloaded an empty file.');
            }
            if ($size > $max) {
                throw new RuntimeException('Downloaded MTProto file exceeds the PlayNexus upload limit.');
            }
            if ($expectedSize !== null && $expectedSize > 0 && $size !== $expectedSize) {
                throw new RuntimeException(
                    "MTProto download size mismatch: expected {$expectedSize} bytes, received {$size}."
                );
            }

            return [
                'path' => $target,
                'name' => $name,
                'mime' => $mime ?: 'application/octet-stream',
                'size' => $size,
                'transport' => 'mtproto',
            ];
        } catch (Throwable $exception) {
            File::delete($target);
            throw new RuntimeException(
                'MTProto large-file download failed: '.$exception->getMessage(),
                previous: $exception,
            );
        }
    }

    private function withClient(callable $callback): mixed
    {
        $resolved = $this->credentials();
        $directory = storage_path('app/telegram-mtproto');
        File::ensureDirectoryExists($directory);

        $lockPath = $directory.'/session.lock';
        $lock = fopen($lockPath, 'c+');
        if (! is_resource($lock)) {
            throw new RuntimeException('Could not open MTProto session lock.');
        }

        if (! flock($lock, LOCK_EX)) {
            fclose($lock);
            throw new RuntimeException('Could not lock MTProto session.');
        }

        $api = null;

        try {
            $madelineSettings = new Settings();
            $madelineSettings->getAppInfo()
                ->setApiId((int) $resolved['api_id'])
                ->setApiHash((string) $resolved['api_hash'])
                ->setShowPrompt(false);
            $madelineSettings->getLogger()
                ->setType(Logger::FILE_LOGGER)
                ->setExtra(storage_path('logs/telegram-mtproto.log'))
                ->setLevel(Logger::ERROR);

            $api = new API($this->sessionPath($resolved), $madelineSettings);
            $api->botLogin((string) $resolved['bot_token']);

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
        $resolved = $this->settings->resolved();

        if (! ($resolved['mtproto_enabled'] ?? false)) {
            throw new RuntimeException('MTProto large-file mode is disabled.');
        }

        $apiId = (int) ($resolved['mtproto_api_id'] ?? 0);
        $apiHash = trim((string) ($resolved['mtproto_api_hash'] ?? ''));
        $botToken = trim((string) ($resolved['bot_token'] ?? ''));

        if ($apiId < 1 || $apiHash === '' || $botToken === '') {
            throw new RuntimeException('MTProto API ID, API Hash and Bot Token must be configured.');
        }

        return [
            'api_id' => $apiId,
            'api_hash' => $apiHash,
            'bot_token' => $botToken,
        ];
    }

    private function sessionPath(array $credentials): string
    {
        $fingerprint = substr(hash(
            'sha256',
            $credentials['api_id'].'|'.$credentials['api_hash'].'|'.$credentials['bot_token'],
        ), 0, 20);

        return storage_path('app/telegram-mtproto/session-'.$fingerprint.'.madeline');
    }
}
