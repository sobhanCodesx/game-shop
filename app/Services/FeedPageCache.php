<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class FeedPageCache
{
    private const VERSION_KEY = 'feed-page-data:version:v1';

    private const KEY_PREFIX = 'feed-page-data:v1';

    public function remember(int $postId, Closure $resolver): array
    {
        $store = $this->store();
        $version = $store->get(self::VERSION_KEY);

        if (! is_string($version) || $version === '') {
            $version = (string) Str::uuid();
            $store->forever(self::VERSION_KEY, $version);
        }

        $ttl = max(60, (int) config('cache.feed_page_ttl_seconds', 21600));
        $key = self::KEY_PREFIX.":{$version}:{$postId}";

        return $store->remember($key, now()->addSeconds($ttl), $resolver);
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
