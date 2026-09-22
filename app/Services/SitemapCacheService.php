<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final class SitemapCacheService
{
    private const KEY_PREFIX = 'sitemap-xml:v2';

    private const KEYS = [
        'index', 'static', 'products', 'categories', 'feed',
        'videos', 'content', 'channels', 'studios', 'playlists',
    ];

    public function remember(string $type, Closure $resolver, int $ttlSeconds = 3600): string
    {
        return (string) $this->store()->remember(
            self::KEY_PREFIX.":{$type}",
            now()->addSeconds(max(60, $ttlSeconds)),
            $resolver,
        );
    }

    public function invalidate(): void
    {
        $store = $this->store();

        foreach (self::KEYS as $type) {
            $store->forget(self::KEY_PREFIX.":{$type}");
        }
    }

    private function store(): Repository
    {
        return Cache::store('file');
    }
}
