<?php

namespace App\Services\NexusAi;

use App\Models\NexusAiProviderSetting;
use App\Services\NexusAiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class NexusAiProviderSettings
{
    public const CLOUDFLARE_WORKERS_AI = 'cloudflare_workers_ai';
    public const GROQ = 'groq';
    public const GEMINI = 'gemini';
    public const CLOUDFLARE_AI_GATEWAY = 'cloudflare_ai_gateway';
    public const OPENAI = 'openai';
    public const OPENAI_COMPATIBLE = 'openai_compatible';
    public const LOCAL_RELAY = 'local_relay';

    public function __construct(private readonly NexusAiSettings $uiSettings)
    {
    }

    public function definitions(): array
    {
        return [
            self::CLOUDFLARE_WORKERS_AI => [
                'label' => 'Cloudflare Workers AI',
                'short_label' => 'Workers AI',
                'description' => 'گزینه رایگان‌محور روی زیرساخت Cloudflare. برای شروع پیشنهاد اصلی PlayNexus است.',
                'free_tier' => true,
                'default_enabled' => true,
                'default_priority' => 10,
                'fields' => [
                    ['key' => 'account_id', 'label' => 'Cloudflare Account ID', 'type' => 'text', 'secret' => false],
                    ['key' => 'api_token', 'label' => 'Workers AI API Token', 'type' => 'password', 'secret' => true],
                    ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'secret' => false],
                    ['key' => 'gateway_id', 'label' => 'AI Gateway ID (اختیاری)', 'type' => 'text', 'secret' => false],
                ],
            ],
            self::GROQ => [
                'label' => 'Groq',
                'short_label' => 'Groq',
                'description' => 'OpenAI-compatible و بسیار سریع؛ برای Free Tier به‌عنوان fallback دوم مناسب است.',
                'free_tier' => true,
                'default_enabled' => true,
                'default_priority' => 20,
                'fields' => [
                    ['key' => 'api_key', 'label' => 'Groq API Key', 'type' => 'password', 'secret' => true],
                    ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'secret' => false],
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'url', 'secret' => false],
                ],
            ],
            self::GEMINI => [
                'label' => 'Google Gemini via Cloudflare',
                'short_label' => 'Gemini',
                'description' => 'Gemini فقط از مسیر رسمی Cloudflare AI Gateway فراخوانی می‌شود؛ درخواست مستقیم به Google از PlayNexus انجام نمی‌شود.',
                'free_tier' => false,
                'default_enabled' => false,
                'default_priority' => 30,
                'fields' => [
                    ['key' => 'account_id', 'label' => 'Cloudflare Account ID', 'type' => 'text', 'secret' => false],
                    ['key' => 'api_token', 'label' => 'Cloudflare API Token', 'type' => 'password', 'secret' => true],
                    ['key' => 'gateway_id', 'label' => 'AI Gateway ID', 'type' => 'text', 'secret' => false],
                    ['key' => 'model', 'label' => 'Gateway Model', 'type' => 'text', 'secret' => false],
                ],
            ],
            self::CLOUDFLARE_AI_GATEWAY => [
                'label' => 'Cloudflare AI Gateway',
                'short_label' => 'AI Gateway',
                'description' => 'مسیر عمومی Cloudflare برای مدل‌های شخص ثالث؛ مدل با قالب provider/model انتخاب می‌شود.',
                'free_tier' => false,
                'default_enabled' => false,
                'default_priority' => 40,
                'fields' => [
                    ['key' => 'account_id', 'label' => 'Cloudflare Account ID', 'type' => 'text', 'secret' => false],
                    ['key' => 'api_token', 'label' => 'Cloudflare API Token', 'type' => 'password', 'secret' => true],
                    ['key' => 'gateway_id', 'label' => 'AI Gateway ID', 'type' => 'text', 'secret' => false],
                    ['key' => 'model', 'label' => 'Gateway Model', 'type' => 'text', 'secret' => false],
                ],
            ],
            self::OPENAI => [
                'label' => 'OpenAI / GPT',
                'short_label' => 'OpenAI',
                'description' => 'پشتیبانی مستقیم از Responses API برای مدل‌های GPT. مدل از پنل قابل تغییر است.',
                'free_tier' => false,
                'default_enabled' => false,
                'default_priority' => 50,
                'fields' => [
                    ['key' => 'api_key', 'label' => 'OpenAI API Key', 'type' => 'password', 'secret' => true],
                    ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'secret' => false],
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'url', 'secret' => false],
                ],
            ],
            self::OPENAI_COMPATIBLE => [
                'label' => 'OpenAI-compatible API',
                'short_label' => 'Compatible',
                'description' => 'برای هر سرویس آینده که Chat Completions سازگار با OpenAI ارائه کند، بدون افزودن کد جدید.',
                'free_tier' => false,
                'default_enabled' => false,
                'default_priority' => 60,
                'fields' => [
                    ['key' => 'base_url', 'label' => 'Base URL', 'type' => 'url', 'secret' => false],
                    ['key' => 'api_key', 'label' => 'API Key (اختیاری)', 'type' => 'password', 'secret' => true],
                    ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'secret' => false],
                ],
            ],
            self::LOCAL_RELAY => [
                'label' => 'PlayNexus Local Model',
                'short_label' => 'Local',
                'description' => 'مدل اختصاصی فعلی PlayNexus از طریق Relay موجود؛ fallback نهایی و پیش‌فرض سازگار با وضعیت فعلی.',
                'free_tier' => true,
                'default_enabled' => true,
                'default_priority' => 100,
                'fields' => [
                    ['key' => 'base_url', 'label' => 'Relay URL', 'type' => 'url', 'secret' => false],
                ],
            ],
        ];
    }

    public function adminPayload(): array
    {
        return collect($this->definitions())->map(function (array $definition, string $provider): array {
            $resolved = $this->resolved($provider);
            $secretConfigured = [];

            foreach ($definition['fields'] as $field) {
                if (! ($field['secret'] ?? false)) {
                    continue;
                }

                $key = $field['key'];
                $secretConfigured[$key] = filled($resolved[$key] ?? null);
                $resolved[$key] = '';
            }

            return [
                'key' => $provider,
                'label' => $definition['label'],
                'short_label' => $definition['short_label'],
                'description' => $definition['description'],
                'free_tier' => (bool) $definition['free_tier'],
                'enabled' => $this->enabled($provider),
                'priority' => $this->priority($provider),
                'configured' => $this->isConfigured($provider),
                'fields' => $definition['fields'],
                'settings' => $resolved,
                'secret_configured' => $secretConfigured,
            ];
        })->values()->all();
    }

    public function ordered(bool $freeFirst, bool $autopilot = false): array
    {
        $autoProviders = [
            self::CLOUDFLARE_WORKERS_AI,
            self::GROQ,
            self::LOCAL_RELAY,
        ];

        $providers = collect(array_keys($this->definitions()))
            ->filter(fn (string $provider): bool =>
                $this->isConfigured($provider)
                && ($this->enabled($provider) || ($autopilot && in_array($provider, $autoProviders, true)))
            )
            ->map(fn (string $provider): array => [
                'key' => $provider,
                'settings' => $this->resolved($provider),
                'priority' => $this->priority($provider),
                'free_tier' => (bool) $this->definitions()[$provider]['free_tier'],
            ]);

        return $providers
            ->sortBy(function (array $item) use ($freeFirst): string {
                $group = $item['key'] === self::LOCAL_RELAY
                    ? 2
                    : ($freeFirst ? ($item['free_tier'] ? 0 : 1) : 0);

                return sprintf('%d-%04d', $group, $item['priority']);
            })
            ->values()
            ->all();
    }

    public function saveMany(array $providers, ?int $userId): void
    {
        if (! $this->tableExists()) {
            throw new RuntimeException('جدول تنظیمات Providerهای Nexus AI هنوز migrate نشده است.');
        }

        $definitions = $this->definitions();

        DB::transaction(function () use ($providers, $userId, $definitions): void {
            foreach ($providers as $input) {
                $provider = (string) ($input['key'] ?? '');
                if (! isset($definitions[$provider])) {
                    continue;
                }

                $existing = NexusAiProviderSetting::query()->where('provider', $provider)->first();
                $current = is_array($existing?->settings) ? $existing->settings : $this->defaults($provider);
                $incoming = is_array($input['settings'] ?? null) ? $input['settings'] : [];
                $settings = [];

                foreach ($definitions[$provider]['fields'] as $field) {
                    $key = $field['key'];
                    $value = array_key_exists($key, $incoming) ? trim((string) $incoming[$key]) : '';
                    if (($field['secret'] ?? false) && $value === '') {
                        $value = (string) ($current[$key] ?? '');
                    }
                    $settings[$key] = $value;
                }

                NexusAiProviderSetting::query()->updateOrCreate(
                    ['provider' => $provider],
                    [
                        'settings' => $settings,
                        'enabled' => (bool) ($input['enabled'] ?? false),
                        'priority' => max(1, min(999, (int) ($input['priority'] ?? $definitions[$provider]['default_priority']))),
                        'updated_by' => $userId,
                    ],
                );
            }
        });
    }

    public function resolved(string $provider): array
    {
        $this->guardProvider($provider);
        $stored = $this->tableExists()
            ? NexusAiProviderSetting::query()->where('provider', $provider)->first()?->settings
            : null;

        return is_array($stored)
            ? array_replace($this->defaults($provider), $stored)
            : $this->defaults($provider);
    }

    public function enabled(string $provider): bool
    {
        $this->guardProvider($provider);
        if (! $this->tableExists()) {
            return (bool) $this->definitions()[$provider]['default_enabled'];
        }

        $value = NexusAiProviderSetting::query()->where('provider', $provider)->value('enabled');

        return $value === null
            ? (bool) $this->definitions()[$provider]['default_enabled']
            : (bool) $value;
    }

    public function priority(string $provider): int
    {
        $this->guardProvider($provider);
        if (! $this->tableExists()) {
            return (int) $this->definitions()[$provider]['default_priority'];
        }

        return (int) (NexusAiProviderSetting::query()->where('provider', $provider)->value('priority')
            ?? $this->definitions()[$provider]['default_priority']);
    }

    public function isConfigured(string $provider): bool
    {
        $settings = $this->resolved($provider);

        return match ($provider) {
            self::CLOUDFLARE_WORKERS_AI => filled($settings['account_id'] ?? null)
                && filled($settings['api_token'] ?? null)
                && filled($settings['model'] ?? null),
            self::GROQ, self::OPENAI => filled($settings['api_key'] ?? null)
                && filled($settings['model'] ?? null)
                && filled($settings['base_url'] ?? null),
            self::GEMINI, self::CLOUDFLARE_AI_GATEWAY => filled($settings['account_id'] ?? null)
                && filled($settings['api_token'] ?? null)
                && filled($settings['gateway_id'] ?? null)
                && filled($settings['model'] ?? null),
            self::OPENAI_COMPATIBLE => filled($settings['base_url'] ?? null)
                && filled($settings['model'] ?? null),
            self::LOCAL_RELAY => filled($settings['base_url'] ?? null),
            default => false,
        };
    }

    private function defaults(string $provider): array
    {
        return match ($provider) {
            self::CLOUDFLARE_WORKERS_AI => [
                'account_id' => (string) config('services.cloudflare_ai.account_id', ''),
                'api_token' => (string) config('services.cloudflare_ai.api_token', ''),
                'model' => (string) config('services.cloudflare_ai.model', '@cf/openai/gpt-oss-120b'),
                'gateway_id' => (string) config('services.cloudflare_ai.gateway_id', ''),
            ],
            self::GROQ => [
                'api_key' => (string) config('services.groq.api_key', ''),
                'model' => (string) config('services.groq.model', 'openai/gpt-oss-120b'),
                'base_url' => (string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'),
            ],
            self::GEMINI => [
                'account_id' => (string) config('services.cloudflare_ai.account_id', ''),
                'api_token' => (string) config('services.cloudflare_ai.api_token', ''),
                'gateway_id' => (string) config('services.cloudflare_ai.gateway_id', ''),
                'model' => (string) config('services.gemini.cloudflare_model', 'google/gemini-3-flash'),
            ],
            self::CLOUDFLARE_AI_GATEWAY => [
                'account_id' => (string) config('services.cloudflare_ai.account_id', ''),
                'api_token' => (string) config('services.cloudflare_ai.api_token', ''),
                'gateway_id' => (string) config('services.cloudflare_ai.gateway_id', ''),
                'model' => (string) config('services.cloudflare_ai.gateway_model', ''),
            ],
            self::OPENAI => [
                'api_key' => (string) config('services.openai.api_key', ''),
                'model' => (string) config('services.openai.model', ''),
                'base_url' => (string) config('services.openai.base_url', 'https://api.openai.com/v1'),
            ],
            self::OPENAI_COMPATIBLE => [
                'base_url' => (string) config('services.nexus_ai_compatible.base_url', ''),
                'api_key' => (string) config('services.nexus_ai_compatible.api_key', ''),
                'model' => (string) config('services.nexus_ai_compatible.model', ''),
            ],
            self::LOCAL_RELAY => [
                'base_url' => rtrim((string) ($this->uiSettings->all()['nexus_ai_worker_url'] ?? ''), '/'),
            ],
            default => [],
        };
    }

    private function tableExists(): bool
    {
        try {
            return Schema::hasTable('nexus_ai_provider_settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function guardProvider(string $provider): void
    {
        if (! isset($this->definitions()[$provider])) {
            throw new RuntimeException('Provider ناشناخته Nexus AI.');
        }
    }
}
