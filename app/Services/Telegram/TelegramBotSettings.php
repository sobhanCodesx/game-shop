<?php

namespace App\Services\Telegram;

use App\Models\TelegramBotSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TelegramBotSettings
{
    private const CACHE_KEY = 'telegram-bot:settings:v2';

    public function resolved(): array
    {
        return $this->remember(function (): array {
            $defaults = [
                'enabled' => (bool) config('telegram_bot.enabled', false),
                'bot_token' => (string) config('telegram_bot.bot_token', ''),
                'admin_user_id' => (string) config('telegram_bot.admin_user_id', ''),
                'write_enabled' => (bool) config('telegram_bot.write_enabled', false),
                'publish_enabled' => (bool) config('telegram_bot.publish_enabled', false),
                'destructive_enabled' => (bool) config('telegram_bot.destructive_enabled', false),
                'media_enabled' => (bool) config('telegram_bot.media_enabled', true),
                'transport_mode' => (string) config('telegram_bot.transport_mode', 'auto'),
                'api_base_url' => (string) config('telegram_bot.api_base_url', 'https://api.telegram.org'),
                'relay_base_url' => (string) config('telegram_bot.relay_base_url', ''),
                'relay_key' => (string) config('telegram_bot.relay_key', ''),
                'use_proxy' => (bool) config('telegram_bot.proxy.enabled', false),
                'proxy_type' => (string) config('telegram_bot.proxy.type', 'socks5h'),
                'proxy_host' => (string) config('telegram_bot.proxy.host', ''),
                'proxy_port' => (int) config('telegram_bot.proxy.port', 1080),
                'proxy_username' => (string) config('telegram_bot.proxy.username', ''),
                'proxy_password' => (string) config('telegram_bot.proxy.password', ''),
                'webhook_secret' => '',
                'bot_id' => null,
                'bot_username' => null,
                'webhook_registered_at' => null,
                'last_webhook_at' => null,
                'last_health_at' => null,
                'last_error' => null,
                'source' => 'environment',
            ];

            if (! $this->tableExists()) {
                return $defaults;
            }

            $stored = TelegramBotSetting::query()->first();
            if (! $stored) {
                return $defaults;
            }

            return [
                ...$defaults,
                'enabled' => (bool) $stored->enabled,
                'bot_token' => (string) ($stored->bot_token ?: $defaults['bot_token']),
                'admin_user_id' => (string) ($stored->admin_user_id ?: $defaults['admin_user_id']),
                'write_enabled' => (bool) $stored->write_enabled,
                'publish_enabled' => (bool) $stored->publish_enabled,
                'destructive_enabled' => (bool) $stored->destructive_enabled,
                'media_enabled' => (bool) $stored->media_enabled,
                'transport_mode' => (string) ($stored->transport_mode ?: $defaults['transport_mode']),
                'api_base_url' => (string) ($stored->api_base_url ?: $defaults['api_base_url']),
                'relay_base_url' => (string) ($stored->relay_base_url ?: ''),
                'relay_key' => (string) ($stored->relay_key ?: ''),
                'use_proxy' => (bool) $stored->use_proxy,
                'proxy_type' => (string) ($stored->proxy_type ?: 'socks5h'),
                'proxy_host' => (string) ($stored->proxy_host ?: ''),
                'proxy_port' => (int) ($stored->proxy_port ?: 1080),
                'proxy_username' => (string) ($stored->proxy_username ?: ''),
                'proxy_password' => (string) ($stored->proxy_password ?: ''),
                'webhook_secret' => (string) ($stored->webhook_secret ?: ''),
                'bot_id' => $stored->bot_id,
                'bot_username' => $stored->bot_username,
                'webhook_registered_at' => $stored->webhook_registered_at?->toISOString(),
                'last_webhook_at' => $stored->last_webhook_at?->toISOString(),
                'last_health_at' => $stored->last_health_at?->toISOString(),
                'last_error' => $stored->last_error,
                'source' => 'database',
            ];
        });
    }

    public function adminPayload(): array
    {
        $settings = $this->resolved();

        return [
            ...$settings,
            'bot_token' => '',
            'relay_key' => '',
            'proxy_password' => '',
            'webhook_secret' => '',
            'bot_token_configured' => filled($settings['bot_token']),
            'relay_key_configured' => filled($settings['relay_key']),
            'relay_configured' => filled($settings['relay_base_url']) && filled($settings['relay_key']),
            'proxy_password_configured' => filled($settings['proxy_password']),
            'webhook_secret_configured' => filled($settings['webhook_secret']),
            'configured' => filled($settings['bot_token']) && filled($settings['admin_user_id']),
            'webhook_url' => route('telegram.webhook'),
            'max_download_bytes' => (int) config('telegram_bot.max_download_bytes', 20 * 1024 * 1024),
        ];
    }

    public function save(array $data, ?int $updatedBy): array
    {
        if (! $this->tableExists()) {
            throw new RuntimeException('Telegram bot tables have not been migrated yet.');
        }

        $existing = TelegramBotSetting::query()->firstOrNew([]);
        $resolved = $this->resolved();

        foreach ([
            'enabled', 'write_enabled', 'publish_enabled', 'destructive_enabled',
            'media_enabled', 'use_proxy',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $existing->{$field} = (bool) $data[$field];
            }
        }

        foreach ([
            'admin_user_id', 'transport_mode', 'api_base_url', 'relay_base_url',
            'proxy_type', 'proxy_host', 'proxy_port', 'proxy_username',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $existing->{$field} = is_string($data[$field])
                    ? trim($data[$field])
                    : $data[$field];
            }
        }

        if (filled($data['bot_token'] ?? null)) {
            $existing->bot_token = trim((string) $data['bot_token']);
        } elseif (! $existing->exists && filled($resolved['bot_token'] ?? null)) {
            $existing->bot_token = (string) $resolved['bot_token'];
        }

        if (array_key_exists('relay_key', $data) && filled($data['relay_key'])) {
            $existing->relay_key = (string) $data['relay_key'];
        } elseif (! $existing->exists && filled($resolved['relay_key'] ?? null)) {
            $existing->relay_key = (string) $resolved['relay_key'];
        }

        if (array_key_exists('proxy_password', $data) && filled($data['proxy_password'])) {
            $existing->proxy_password = (string) $data['proxy_password'];
        } elseif (! $existing->exists && filled($resolved['proxy_password'] ?? null)) {
            $existing->proxy_password = (string) $resolved['proxy_password'];
        }

        if (! filled($existing->webhook_secret)) {
            $existing->webhook_secret = Str::random(64);
        }

        $existing->updated_by = $updatedBy;
        $existing->save();
        $this->forget();

        return $this->resolved();
    }

    public function rotateWebhookSecret(?int $updatedBy = null): string
    {
        $model = $this->model();
        $model->webhook_secret = Str::random(64);
        $model->updated_by = $updatedBy;
        $model->save();
        $this->forget();

        return (string) $model->webhook_secret;
    }

    public function updateBotIdentity(array $user): void
    {
        $model = $this->model();
        $model->bot_id = isset($user['id']) ? (string) $user['id'] : null;
        $model->bot_username = isset($user['username']) ? (string) $user['username'] : null;
        $model->last_health_at = now();
        $model->last_error = null;
        $model->save();
        $this->forget();
    }

    public function markWebhookRegistered(): void
    {
        $model = $this->model();
        $model->webhook_registered_at = now();
        $model->last_error = null;
        $model->save();
        $this->forget();
    }

    public function markWebhookUnregistered(): void
    {
        $model = $this->model();
        $model->webhook_registered_at = null;
        $model->last_error = null;
        $model->save();
        $this->forget();
    }

    public function markWebhookReceived(): void
    {
        if (! $this->tableExists()) {
            return;
        }

        TelegramBotSetting::query()->limit(1)->update([
            'last_webhook_at' => now(),
            'last_error' => null,
        ]);
        $this->forget();
    }

    public function rememberError(string $message): void
    {
        if (! $this->tableExists()) {
            return;
        }

        TelegramBotSetting::query()->limit(1)->update([
            'last_error' => Str::limit($message, 2000),
        ]);
        $this->forget();
    }

    public function acceptsUser(string|int|null $userId, ?string $chatType): bool
    {
        $settings = $this->resolved();

        return ($settings['enabled'] ?? false)
            && $chatType === 'private'
            && filled($settings['admin_user_id'])
            && hash_equals((string) $settings['admin_user_id'], (string) $userId);
    }

    public function proxyUrl(): ?string
    {
        $settings = $this->resolved();
        if (! ($settings['use_proxy'] ?? false) || blank($settings['proxy_host'] ?? null)) {
            return null;
        }

        $type = in_array($settings['proxy_type'] ?? '', ['socks5', 'socks5h', 'http', 'https'], true)
            ? $settings['proxy_type']
            : 'socks5h';

        $auth = '';
        if (filled($settings['proxy_username'] ?? null)) {
            $auth = rawurlencode((string) $settings['proxy_username']);
            if (filled($settings['proxy_password'] ?? null)) {
                $auth .= ':'.rawurlencode((string) $settings['proxy_password']);
            }
            $auth .= '@';
        }

        return sprintf(
            '%s://%s%s:%d',
            $type,
            $auth,
            (string) $settings['proxy_host'],
            (int) ($settings['proxy_port'] ?? 1080),
        );
    }

    public function isConfigured(): bool
    {
        $settings = $this->resolved();

        return filled($settings['bot_token'] ?? null)
            && filled($settings['admin_user_id'] ?? null);
    }

    private function model(): TelegramBotSetting
    {
        if (! $this->tableExists()) {
            throw new RuntimeException('Telegram bot tables have not been migrated yet.');
        }

        $model = TelegramBotSetting::query()->first();
        if ($model) {
            return $model;
        }

        $this->save([], null);

        return TelegramBotSetting::query()->firstOrFail();
    }

    private function remember(\Closure $resolver): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, now()->addMinutes(10), $resolver);
        } catch (Throwable) {
            return $resolver();
        }
    }

    private function forget(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('telegram_bot_settings');
        } catch (Throwable) {
            return false;
        }
    }
}
