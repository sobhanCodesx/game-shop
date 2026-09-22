<?php

namespace App\Observers;

use App\Models\Studio;
use App\Services\StorefrontPageCache;

class StudioObserver
{
    public function created(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel');
    }

    public function updated(Studio $studio): void
    {
        if ($studio->wasChanged(['name', 'slug', 'logo', 'status'])) {
            app(StorefrontPageCache::class)->invalidate('channel');
        }
    }

    public function deleted(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel');
    }

    public function restored(Studio $studio): void
    {
        app(StorefrontPageCache::class)->invalidate('channel');
    }
}
