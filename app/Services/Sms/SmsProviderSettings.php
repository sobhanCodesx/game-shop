<?php

namespace App\Services\Sms;

use App\Models\SmsProviderSetting;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class SmsProviderSettings
{
    public const PAYAMAK_PANEL = 'payamak_panel';
    public const SMS_IR = 'sms_ir';

    private const ACTIVE_CACHE_KEY = 'sms-provider-settings:v2:active';
    private const CACHE_PREFIX = 'sms-provider-settings:v2:provider:';

    public function definitions(): array
    {
        return [
            self::PAYAMAK_PANEL => [
                'label' => 'ملی پیامک',
                'short_label' => 'Meli Payamak',
                'description' => 'پنل فعلی PlayNexus؛ مقادیر اولیه از ENV خوانده می‌شوند و بعد از اولین ذخیره، نسخه دیتابیس اولویت دارد.',
                'fields' => [
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'url', 'secret' => false],
                    ['key' => 'pattern_endpoint', 'label' => 'Pattern Endpoint', 'type' => 'url', 'secret' => false],
                    ['key' => 'username', 'label' => 'Username', 'type' => 'text', 'secret' => false],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'secret' => true],
                    ['key' => 'from', 'label' => 'شماره خط اصلی', 'type' => 'text', 'secret' => false],
                    ['key' => 'from_support_one', 'label' => 'خط پشتیبان ۱', 'type' => 'text', 'secret' => false],
                    ['key' => 'from_support_two', 'label' => 'خط پشتیبان ۲', 'type' => 'text', 'secret' => false],
                ],
            ],
            self::SMS_IR => [
                'label' => 'SMS.ir',
                'short_label' => 'SMS.ir',
                'description' => 'وب‌سرویس v1 با احراز هویت X-API-KEY؛ ارسال عادی و Verify Template پشتیبانی می‌شود.',
                'fields' => [
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'url', 'secret' => false],
                    ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password', 'secret' => true],
                    ['key' => 'line_number', 'label' => 'شماره خط ارسال', 'type' => 'text', 'secret' => false],
                ],
            ],
        ];
    }

    public function adminPayload(): array
    {
        $active = $this->activeProvider();

        return collect($this->definitions())->map(function (array $definition, string $provider) use ($active) {
            return [
                'key' => $provider,
                ...$definition,
                'settings' => $this->resolved($provider),
                'source' => $this->hasDatabaseOverride($provider) ? 'database' : 'environment',
                'active' => $active === $provider,
                'configured' => $this->isConfigured($provider),
            ];
        })->values()->all();
    }

    public function activeProvider(): string
    {
        return $this->remember(self::ACTIVE_CACHE_KEY, function (): string {
            if (! $this->tableExists()) {
                return self::PAYAMAK_PANEL;
            }

            $provider = SmsProviderSetting::query()->where('is_active', true)->value('provider');

            return array_key_exists((string) $provider, $this->definitions())
                ? (string) $provider
                : self::PAYAMAK_PANEL;
        });
    }

    public function label(string $provider): string
    {
        return (string) ($this->definitions()[$provider]['label'] ?? $provider);
    }

    public function resolved(?string $provider = null): array
    {
        $provider ??= $this->activeProvider();
        $this->guardProvider($provider);

        return $this->remember(self::CACHE_PREFIX.$provider, function () use ($provider): array {
            $defaults = $this->defaults($provider);
            if (! $this->tableExists()) {
                return $defaults;
            }

            $stored = SmsProviderSetting::query()->where('provider', $provider)->first()?->settings;

            return is_array($stored) ? array_replace($defaults, $stored) : $defaults;
        });
    }

    public function applyRuntimeConfig(?string $provider = null): void
    {
        $provider ??= $this->activeProvider();
        $settings = $this->resolved($provider);

        if ($provider === self::PAYAMAK_PANEL) {
            config()->set('services.payamak_panel', array_replace(
                (array) config('services.payamak_panel', []),
                $settings,
            ));

            return;
        }

        config()->set('services.sms_ir', array_replace(
            (array) config('services.sms_ir', []),
            $settings,
        ));
    }

    public function isConfigured(?string $provider = null): bool
    {
        $provider ??= $this->activeProvider();
        $settings = $this->resolved($provider);

        return match ($provider) {
            self::PAYAMAK_PANEL => filled($settings['base_url'] ?? null)
                && filled($settings['username'] ?? null)
                && filled($settings['api_key'] ?? null),
            self::SMS_IR => filled($settings['base_url'] ?? null)
                && filled($settings['api_key'] ?? null)
                && filled($settings['line_number'] ?? null),
            default => false,
        };
    }

    public function save(string $provider, array $settings, bool $activate, ?int $userId): void
    {
        $this->guardProvider($provider);
        if (! $this->tableExists()) {
            throw new RuntimeException('جدول تنظیمات پنل پیامکی هنوز migrate نشده است.');
        }

        DB::transaction(function () use ($provider, $settings, $activate, $userId): void {
            if ($activate) {
                SmsProviderSetting::query()->where('is_active', true)->update(['is_active' => false]);
            }

            $model = SmsProviderSetting::query()->firstOrNew(['provider' => $provider]);
            $model->settings = $settings;
            $model->updated_by = $userId;
            if ($activate) {
                $model->is_active = true;
            } elseif (! $model->exists) {
                $model->is_active = false;
            }
            $model->save();
        });

        $this->forget($provider);
    }

    public function reset(string $provider): void
    {
        $this->guardProvider($provider);
        if ($this->tableExists()) {
            SmsProviderSetting::query()->where('provider', $provider)->delete();
        }

        $this->forget($provider);
    }

    public function hasDatabaseOverride(string $provider): bool
    {
        $this->guardProvider($provider);

        return $this->tableExists()
            && SmsProviderSetting::query()->where('provider', $provider)->exists();
    }

    private function defaults(string $provider): array
    {
        return match ($provider) {
            self::PAYAMAK_PANEL => [
                'base_url' => (string) config('services.payamak_panel.base_url', 'https://rest.payamak-panel.com/api/SmartSMS'),
                'pattern_endpoint' => (string) config('services.payamak_panel.pattern_endpoint', 'https://rest.payamak-panel.com/api/SmartSMS/SendByBaseNumber'),
                'username' => (string) config('services.payamak_panel.username', ''),
                'api_key' => (string) config('services.payamak_panel.api_key', ''),
                'from' => (string) config('services.payamak_panel.from', ''),
                'from_support_one' => (string) config('services.payamak_panel.from_support_one', ''),
                'from_support_two' => (string) config('services.payamak_panel.from_support_two', ''),
            ],
            self::SMS_IR => [
                'base_url' => (string) config('services.sms_ir.base_url', 'https://api.sms.ir/v1'),
                'api_key' => (string) config('services.sms_ir.api_key', ''),
                'line_number' => (string) config('services.sms_ir.line_number', ''),
            ],
            default => [],
        };
    }

    private function forget(string $provider): void
    {
        try {
            Cache::forget(self::ACTIVE_CACHE_KEY);
            Cache::forget(self::CACHE_PREFIX.$provider);
        } catch (Throwable) {
        }
    }

    private function remember(string $key, Closure $resolver): mixed
    {
        try {
            return Cache::rememberForever($key, $resolver);
        } catch (Throwable) {
            return $resolver();
        }
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('sms_provider_settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function guardProvider(string $provider): void
    {
        if (! array_key_exists($provider, $this->definitions())) {
            throw new RuntimeException('پنل پیامکی ناشناخته است.');
        }
    }
}
