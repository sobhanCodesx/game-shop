<?php

namespace App\Services;

use App\Models\HomeSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NexusAiSettings
{
    private const CACHE_KEY = 'nexus-ai.settings.v1';

    public const DEFAULTS = [
        'nexus_ai_enabled' => true,
        'nexus_ai_show_in_nav' => true,
        'nexus_ai_title' => 'Nexus AI',
        'nexus_ai_description' => 'دستیار گیمینگ PlayNexus برای سؤال درباره بازی‌ها، اصطلاحات، راهنما و انتخاب بازی.',
        'nexus_ai_nav_label' => 'Nexus AI',
        'nexus_ai_iframe_url' => 'https://nexus-ai-relay.sobhankhorshidi1397.workers.dev',
        'nexus_ai_min_height' => 760,
        'nexus_ai_status_text' => 'نسخه آزمایشی',
    ];

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            $content = HomeSetting::query()->find(1)?->content ?? [];

            return [...self::DEFAULTS, ...array_intersect_key($content, self::DEFAULTS)];
        });
    }

    public function publicConfig(): array
    {
        $settings = $this->all();

        return [
            'enabled' => (bool) $settings['nexus_ai_enabled'],
            'show_in_nav' => (bool) $settings['nexus_ai_show_in_nav'],
            'title' => (string) $settings['nexus_ai_title'],
            'description' => (string) $settings['nexus_ai_description'],
            'nav_label' => (string) $settings['nexus_ai_nav_label'],
            'iframe_url' => (string) $settings['nexus_ai_iframe_url'],
            'min_height' => (int) $settings['nexus_ai_min_height'],
            'status_text' => (string) $settings['nexus_ai_status_text'],
        ];
    }

    public function update(array $data): void
    {
        DB::transaction(function () use ($data): void {
            HomeSetting::query()->firstOrCreate(['id' => 1], ['content' => []]);
            $model = HomeSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
            $model->update([
                'content' => [
                    ...($model->content ?? []),
                    ...$data,
                ],
            ]);
        });

        Cache::forget(self::CACHE_KEY);
    }
}
