<?php

namespace App\Services;

use App\Models\HomeSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NexusAiSettings
{
    private const CACHE_KEY = 'nexus-ai.settings.v2';

    public const DEFAULTS = [
        'nexus_ai_enabled' => true,
        'nexus_ai_page_enabled' => true,
        'nexus_ai_show_in_nav' => false,
        'nexus_ai_title' => 'Nexus AI',
        'nexus_ai_description' => 'دستیار گیمینگ PlayNexus برای سؤال درباره بازی‌ها، اصطلاحات، راهنما و انتخاب بازی.',
        'nexus_ai_nav_label' => 'Nexus AI',
        'nexus_ai_worker_url' => 'https://nexus-ai-relay.sobhankhorshidi1397.workers.dev',
        'nexus_ai_launcher_label' => 'سؤال گیم داری؟',
        'nexus_ai_welcome_title' => 'چی تو ذهنت داری؟',
        'nexus_ai_welcome_text' => 'درباره بازی‌ها، لور، انتخاب بازی، Build، باس‌ها و اصطلاحات گیم ازم بپرس.',
        'nexus_ai_status_text' => 'نسخه آزمایشی',
        'nexus_ai_free_first' => true,
        'nexus_ai_autopilot' => true,
        'nexus_ai_collect_analytics' => true,
        'nexus_ai_guest_daily_limit' => 10,
        'nexus_ai_user_daily_limit' => 30,
        'nexus_ai_spoiler_guard' => true,
        'nexus_ai_clarify_ambiguity' => true,
        'nexus_ai_response_style' => 'balanced',
        'nexus_ai_provider_timeout_seconds' => 20,
        'nexus_ai_max_output_tokens' => 1200,
        'nexus_ai_temperature' => 0.65,
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
            'page_enabled' => (bool) $settings['nexus_ai_page_enabled'],
            'show_in_nav' => (bool) $settings['nexus_ai_show_in_nav'],
            'title' => (string) $settings['nexus_ai_title'],
            'description' => (string) $settings['nexus_ai_description'],
            'nav_label' => (string) $settings['nexus_ai_nav_label'],
            'launcher_label' => (string) $settings['nexus_ai_launcher_label'],
            'welcome_title' => (string) $settings['nexus_ai_welcome_title'],
            'welcome_text' => (string) $settings['nexus_ai_welcome_text'],
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
        Cache::forget('nexus-ai.settings.v1');
        app(SitemapCacheService::class)->invalidate();
    }
}
