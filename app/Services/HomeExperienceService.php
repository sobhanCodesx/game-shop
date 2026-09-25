<?php

namespace App\Services;

use App\Models\HomeSetting;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

class HomeExperienceService
{
    private const SETTINGS_CACHE_KEY = 'home-experience:settings:v1';

    public function settings(): array
    {
        return $this->cache()->rememberForever(
            self::SETTINGS_CACHE_KEY,
            fn () => HomeSetting::query()->find(1)?->content ?? [],
        );
    }

    public function templates(): array
    {
        return collect(config('home-experience.templates', []))
            ->map(fn (array $template, string $key) => [
                'key' => $key,
                ...$template,
            ])
            ->values()
            ->all();
    }

    public function resolve(array $settings, ?User $user = null): array
    {
        $templates = config('home-experience.templates', []);
        $systemTemplate = (string) ($settings['home_template'] ?? 'default');
        if (! ($templates[$systemTemplate]['available'] ?? false)) {
            $systemTemplate = 'default';
        }

        $preference = $user?->home_focus_preference;
        $preferredTemplate = $preference
            ? config("home-experience.preference_map.{$preference}")
            : null;

        $effectiveTemplate = $systemTemplate;
        $source = 'system';
        $systemTemplateLocksUserOverride = (bool) (
            $templates[$systemTemplate]['lock_user_override'] ?? false
        );

        if (
            ! $systemTemplateLocksUserOverride
            && $preferredTemplate
            && ($templates[$preferredTemplate]['available'] ?? false)
        ) {
            $effectiveTemplate = $preferredTemplate;
            $source = 'user';
        }

        $template = $templates[$effectiveTemplate] ?? $templates['default'];

        return [
            'system_template' => $systemTemplate,
            'effective_template' => $effectiveTemplate,
            'focus' => $template['focus'] ?? 'balanced',
            'source' => $source,
            'user_preference' => $preference,
        ];
    }

    public function invalidate(): void
    {
        $this->cache()->forget(self::SETTINGS_CACHE_KEY);
    }

    private function cache(): CacheRepository
    {
        $store = app()->environment('testing')
            ? 'array'
            : (string) config('home-experience.cache_store', 'file');

        return Cache::store($store);
    }
}
