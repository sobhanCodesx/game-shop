<?php

namespace App\Services;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class VideoPageCache
{
    private const VERSION_KEY = 'video-page-data:version:v1';

    private const KEY_PREFIX = 'video-page-data:v1';

    public function remember(int $videoId, ?string $playlistSlug, Closure $resolver): array
    {
        $store = $this->store();
        $version = $store->get(self::VERSION_KEY);

        if (! is_string($version) || $version === '') {
            $version = (string) Str::uuid();
            $store->forever(self::VERSION_KEY, $version);
        }

        $context = trim((string) $playlistSlug);
        $contextHash = sha1($context === '' ? 'default' : $context);
        $key = self::KEY_PREFIX.":{$version}:{$videoId}:{$contextHash}";
        $ttl = max(60, (int) config('cache.video_page_ttl_seconds', 21600));

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
