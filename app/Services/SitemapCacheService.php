<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class SitemapCacheService
{
    private const VERSION_KEY = 'sitemap-xml:version:v1';

    private const KEY_PREFIX = 'sitemap-xml:v1';

    public function remember(string $type, Closure $resolver, int $ttlSeconds = 3600): string
    {
        $store = $this->store();
        $version = $store->get(self::VERSION_KEY);

        if (! is_string($version) || $version === '') {
            $version = (string) Str::uuid();
            $store->forever(self::VERSION_KEY, $version);
        }

        return (string) $store->remember(
            self::KEY_PREFIX.":{$version}:{$type}",
            now()->addSeconds(max(60, $ttlSeconds)),
            $resolver,
        );
    }

    public function invalidate(): void
    {
        $this->store()->forever(self::VERSION_KEY, (string) Str::uuid());
    }

    private function store(): Repository
    {
        return Cache::store('file');
    }
}
