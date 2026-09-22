<?php

namespace App\Observers;

use App\Models\Studio;
use App\Services\StorefrontPageCache;

class StudioObserver
{
    public function created(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home');
    }

    public function updated(Studio $studio): void
    {
        if ($studio->wasChanged(['name', 'slug', 'logo', 'status'])) {
            app(StorefrontPageCache::class)->invalidate('channel', 'home');
        }
    }

    public function deleted(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home');
    }

    public function restored(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home');
    }
}
