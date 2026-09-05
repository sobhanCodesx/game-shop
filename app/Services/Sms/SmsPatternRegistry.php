<?php

namespace App\Services\Sms;

use App\Models\SmsPatternConfiguration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SmsPatternRegistry
{
    private ?Collection $configurations = null;

    public function all(): Collection
    {
        $configurations = $this->configurations();

        return collect(SmsPattern::cases())->map(function (SmsPattern $pattern) use ($configurations) {
            $configuration = $configurations->get($pattern->value);

            return [
                'value' => $pattern->value,
                'label' => $pattern->label(),
                'description' => $pattern->description(),
                'variables' => $pattern->requiredVariables(),
                'default_template' => SmsMessageFormatter::withOptOutFooter($pattern->defaultTemplate()),
                'provider_id' => $configuration?->provider_id ?? $pattern->defaultProviderId(),
                'is_active' => (bool) ($configuration?->is_active ?? true),
                'configured' => (bool) ($configuration?->is_active ?? true),
                'provider_pattern_configured' => $configuration !== null
                    && $pattern->hasRealProviderId($configuration->provider_id),
            ];
        });
    }

    public function providerId(SmsPattern $pattern): string
    {
        $configuration = $this->configurations()->get($pattern->value);

        return $configuration && $configuration->is_active && $pattern->hasRealProviderId($configuration->provider_id)
            ? trim((string) $configuration->provider_id)
            : '';
    }

    public function isActive(SmsPattern $pattern): bool
    {
        return (bool) ($this->configurations()->get($pattern->value)?->is_active ?? true);
    }

    public function update(array $patterns, int $userId): void
    {
        $patterns = collect($patterns)->keyBy('code');
        DB::transaction(function () use ($patterns, $userId): void {
            foreach (SmsPattern::cases() as $pattern) {
                $data = $patterns->get($pattern->value, []);
                SmsPatternConfiguration::query()->updateOrCreate(
                    ['code' => $pattern->value],
                    [
                        'provider_id' => filled($data['provider_id'] ?? null) ? trim((string) $data['provider_id']) : null,
                        'is_active' => (bool) ($data['is_active'] ?? false),
                        'updated_by' => $userId,
                    ],
                );
            }
        });

        $this->configurations = null;
    }

    private function configurations(): Collection
    {
        return $this->configurations ??= SmsPatternConfiguration::query()->get()->keyBy('code');
    }
}
