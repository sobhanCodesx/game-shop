<?php

namespace App\Services\Sms;

use App\Models\SmsPatternConfiguration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SmsPatternRegistry
{
    private ?Collection $configurations = null;

    public function __construct(
        private readonly SmsProviderSettings $providerSettings,
    ) {}

    public function all(): Collection
    {
        $configurations = $this->configurations();
        $provider = $this->provider();

        return collect(SmsPattern::cases())->map(function (SmsPattern $pattern) use ($configurations, $provider) {
            $configuration = $configurations->get($pattern->value);
            $providerId = $this->providerIdForConfiguration($configuration, $provider);

            return [
                'value' => $pattern->value,
                'label' => $pattern->label(),
                'description' => $pattern->description(),
                'variables' => $pattern->requiredVariables(),
                'default_template' => SmsMessageFormatter::withOptOutFooter($pattern->defaultTemplate()),
                'provider_id' => $providerId,
                'is_active' => (bool) ($configuration?->is_active ?? true),
                'configured' => (bool) ($configuration?->is_active ?? true),
                'provider_pattern_configured' => $pattern->hasRealProviderId($providerId),
                'provider' => $provider,
                'provider_label' => $this->providerLabel(),
            ];
        });
    }

    public function providerId(SmsPattern $pattern): string
    {
        $configuration = $this->configurations()->get($pattern->value);
        if (! $configuration || ! $configuration->is_active) {
            return '';
        }

        $providerId = $this->providerIdForConfiguration($configuration, $this->provider());

        return $pattern->hasRealProviderId($providerId) ? trim($providerId) : '';
    }

    public function isActive(SmsPattern $pattern): bool
    {
        return (bool) ($this->configurations()->get($pattern->value)?->is_active ?? true);
    }

    public function update(array $patterns, int $userId): void
    {
        $patterns = collect($patterns)->keyBy('code');
        $provider = $this->provider();

        DB::transaction(function () use ($patterns, $userId, $provider): void {
            foreach (SmsPattern::cases() as $pattern) {
                $data = $patterns->get($pattern->value, []);
                $configuration = SmsPatternConfiguration::query()->firstOrNew(['code' => $pattern->value]);
                $providerIds = is_array($configuration->provider_ids) ? $configuration->provider_ids : [];

                if ($providerIds === [] && filled($configuration->provider_id)) {
                    $providerIds[SmsProviderSettings::PAYAMAK_PANEL] = trim((string) $configuration->provider_id);
                }

                $providerId = filled($data['provider_id'] ?? null)
                    ? trim((string) $data['provider_id'])
                    : null;

                if ($providerId === null) {
                    unset($providerIds[$provider]);
                } else {
                    $providerIds[$provider] = $providerId;
                }

                $configuration->fill([
                    'provider_ids' => $providerIds,
                    'is_active' => (bool) ($data['is_active'] ?? false),
                    'updated_by' => $userId,
                ]);

                if ($provider === SmsProviderSettings::PAYAMAK_PANEL) {
                    $configuration->provider_id = $providerId;
                }

                $configuration->save();
            }
        });

        $this->configurations = null;
    }

    public function provider(): string
    {
        return $this->providerSettings->activeProvider();
    }

    public function providerLabel(): string
    {
        return $this->providerSettings->label($this->provider());
    }

    private function providerIdForConfiguration(?SmsPatternConfiguration $configuration, string $provider): string
    {
        if (! $configuration) {
            return '';
        }

        $ids = is_array($configuration->provider_ids) ? $configuration->provider_ids : [];
        if ($provider === SmsProviderSettings::PAYAMAK_PANEL) {
            return trim((string) ($ids[$provider] ?? $configuration->provider_id ?? ''));
        }

        return trim((string) ($ids[$provider] ?? ''));
    }

    private function configurations(): Collection
    {
        return $this->configurations ??= SmsPatternConfiguration::query()->get()->keyBy('code');
    }
}
