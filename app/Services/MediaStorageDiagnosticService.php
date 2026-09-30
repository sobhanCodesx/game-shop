<?php

namespace App\Services;

final class MediaStorageDiagnosticService
{
    public function report(): array
    {
        $host = trim((string) config('filesystems.disks.downloads.host'));
        $username = (string) config('filesystems.disks.downloads.username');
        $password = (string) config('filesystems.disks.downloads.password');
        $port = (int) config('filesystems.disks.downloads.port', 21);
        $root = (string) config('filesystems.disks.downloads.root', '');
        $ssl = (bool) config('filesystems.disks.downloads.ssl', false);
        $url = rtrim((string) config('filesystems.disks.downloads.url', ''), '/');
        $timeout = (int) config('filesystems.disks.downloads.timeout', 30);

        $base = [
            'media_disk' => (string) config('media.disk'),
            'product_media_disk' => (string) config('product_media.disk'),
            'digital_media_disk' => (string) config('digital_media.disk'),
            'downloads' => [
                'host' => $host !== '' ? $host : null,
                'port' => $port,
                'root' => $root,
                'ssl_configured' => $ssl,
                'url' => $url !== '' ? $url : null,
                'username_set' => $username !== '',
                'username_length' => strlen($username),
                'username_contains_at' => str_contains($username, '@'),
                'password_set' => $password !== '',
                'password_length' => strlen($password),
                'resolved_ip' => $host !== '' ? $this->resolvedIp($host) : null,
            ],
        ];

        if ($host === '' || $username === '' || $password === '') {
            return [
                ...$base,
                'plain_ftp' => ['connect_ok' => false, 'login_ok' => false, 'skipped' => true],
                'explicit_tls_ftp' => ['connect_ok' => false, 'login_ok' => false, 'skipped' => true],
            ];
        }

        return [
            ...$base,
            'plain_ftp' => $this->probe($host, $port, $username, $password, $timeout, false),
            'explicit_tls_ftp' => $this->probe($host, $port, $username, $password, $timeout, true),
        ];
    }

    private function resolvedIp(string $host): ?string
    {
        $ip = @gethostbyname($host);

        return is_string($ip) && $ip !== '' && $ip !== $host ? $ip : null;
    }

    private function probe(
        string $host,
        int $port,
        string $username,
        string $password,
        int $timeout,
        bool $ssl,
    ): array {
        if ($ssl && ! function_exists('ftp_ssl_connect')) {
            return ['connect_ok' => false, 'login_ok' => false, 'supported' => false];
        }

        $connection = $ssl
            ? @ftp_ssl_connect($host, $port, max(1, $timeout))
            : @ftp_connect($host, $port, max(1, $timeout));

        if ($connection === false) {
            return ['connect_ok' => false, 'login_ok' => false, 'supported' => true];
        }

        try {
            $login = @ftp_login($connection, $username, $password);
            if ($login) {
                @ftp_pasv($connection, true);
            }

            return [
                'connect_ok' => true,
                'login_ok' => (bool) $login,
                'supported' => true,
            ];
        } finally {
            @ftp_close($connection);
        }
    }
}
