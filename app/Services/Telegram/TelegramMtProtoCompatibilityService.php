<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\File;

final class TelegramMtProtoCompatibilityService
{
    public function report(): array
    {
        $required = [
            'php_version' => PHP_VERSION_ID >= 80200,
            'php_64bit' => PHP_INT_SIZE >= 8,
            'json' => extension_loaded('json'),
            'xml' => extension_loaded('xml'),
            'dom' => extension_loaded('dom'),
            'filter' => extension_loaded('filter'),
            'hash' => extension_loaded('hash'),
            'zlib' => extension_loaded('zlib'),
            'fileinfo' => extension_loaded('fileinfo'),
            'stream_socket_client' => function_exists('stream_socket_client'),
            'storage_writable' => $this->storageWritable(),
        ];

        $network = $this->telegramDcReachable();
        $required['telegram_dc_tcp'] = $network['ok'];

        return [
            'compatible' => collect($required)->every(fn (bool $ok): bool => $ok),
            'required' => $required,
            'recommended' => [
                'mbstring' => extension_loaded('mbstring'),
                'openssl' => extension_loaded('openssl'),
                'gmp' => extension_loaded('gmp'),
                'ffi' => extension_loaded('ffi'),
            ],
            'network' => $network['detail'],
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => (string) ini_get('memory_limit'),
            'max_execution_time' => (string) ini_get('max_execution_time'),
            'open_basedir' => (string) ini_get('open_basedir'),
            'disabled_functions' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) ini_get('disable_functions')),
            ))),
        ];
    }

    private function storageWritable(): bool
    {
        $directory = storage_path('app/telegram-mtproto');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/probe-'.bin2hex(random_bytes(6));

        try {
            return File::put($path, 'ok') === 2
                && File::isFile($path)
                && File::get($path) === 'ok';
        } finally {
            File::delete($path);
        }
    }

    private function telegramDcReachable(): array
    {
        if (! function_exists('stream_socket_client')) {
            return ['ok' => false, 'detail' => 'stream_socket_client unavailable'];
        }

        $targets = [
            '149.154.167.50:443',
            '91.108.56.130:443',
        ];
        $errors = [];

        foreach ($targets as $target) {
            $errno = 0;
            $error = '';
            $socket = @stream_socket_client(
                'tcp://'.$target,
                $errno,
                $error,
                3,
                STREAM_CLIENT_CONNECT,
            );

            if (is_resource($socket)) {
                fclose($socket);

                return ['ok' => true, 'detail' => $target.' reachable'];
            }

            $errors[] = $target.' '.$errno.' '.trim($error);
        }

        return ['ok' => false, 'detail' => implode('; ', $errors)];
    }
}
