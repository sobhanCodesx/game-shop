<?php

namespace App\Observers;

use App\Models\Studio;
use App\Services\SitemapCacheService;
use App\Services\StorefrontPageCache;

class StudioObserver
{
    public function created(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home', 'studio', 'video');
        app(SitemapCacheService::class)->invalidate();
    }

    public function updated(Studio $studio): void
    {
        if ($studio->wasChanged(['name', 'slug', 'logo', 'background', 'description', 'website', 'status'])) {
            app(StorefrontPageCache::class)->invalidate('channel', 'home', 'studio', 'video');
            app(SitemapCacheService::class)->invalidate();
        }
    }

    public function deleted(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home', 'studio', 'video');
        app(SitemapCacheService::class)->invalidate();
    }

    public function restored(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home', 'studio', 'video');
        app(SitemapCacheService::class)->invalidate();
    }
}
