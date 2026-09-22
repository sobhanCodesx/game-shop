<?php

return [
    'enabled' => filter_var(env('TELEGRAM_BOT_ENABLED', false), FILTER_VALIDATE_BOOL),
    'bot_token' => trim((string) env('TELEGRAM_BOT_TOKEN', '')),
    'admin_user_id' => trim((string) env('TELEGRAM_ALLOWED_USER_ID', '')),
    'write_enabled' => filter_var(env('TELEGRAM_BOT_WRITE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'publish_enabled' => filter_var(env('TELEGRAM_BOT_PUBLISH_ENABLED', false), FILTER_VALIDATE_BOOL),
    'destructive_enabled' => filter_var(env('TELEGRAM_BOT_DESTRUCTIVE_ENABLED', false), FILTER_VALIDATE_BOOL),
    'media_enabled' => filter_var(env('TELEGRAM_BOT_MEDIA_ENABLED', true), FILTER_VALIDATE_BOOL),

    'api_base_url' => rtrim((string) env('TELEGRAM_BOT_API_BASE_URL', 'https://api.telegram.org'), '/'),
    'request_timeout' => max(5, (int) env('TELEGRAM_BOT_REQUEST_TIMEOUT', 30)),
    'connect_timeout' => max(2, (int) env('TELEGRAM_BOT_CONNECT_TIMEOUT', 10)),
    'max_download_bytes' => min(
        20 * 1024 * 1024,
        max(1024, (int) env('TELEGRAM_BOT_MAX_DOWNLOAD_BYTES', 20 * 1024 * 1024)),
    ),

    'proxy' => [
        'enabled' => filter_var(env('TELEGRAM_BOT_PROXY_ENABLED', false), FILTER_VALIDATE_BOOL),
        'type' => (string) env('TELEGRAM_BOT_PROXY_TYPE', 'socks5h'),
        'host' => trim((string) env('TELEGRAM_BOT_PROXY_HOST', '')),
        'port' => (int) env('TELEGRAM_BOT_PROXY_PORT', 1080),
        'username' => trim((string) env('TELEGRAM_BOT_PROXY_USERNAME', '')),
        'password' => (string) env('TELEGRAM_BOT_PROXY_PASSWORD', ''),
    ],

    'confirmation_ttl_seconds' => max(60, (int) env('TELEGRAM_BOT_CONFIRMATION_TTL', 300)),
    'session_ttl_seconds' => max(300, (int) env('TELEGRAM_BOT_SESSION_TTL', 1800)),
];
