<?php

namespace App\Observers;

use App\Models\Studio;
use App\Services\VideoPageCache;

class StudioObserver
{
    public function updated(Studio $studio): void
    {
        if ($studio->wasChanged(['name', 'slug'])) {
            app(VideoPageCache::class)->invalidate();
        }
    }

    public function deleted(Studio $studio): void
    {
        app(VideoPageCache::class)->invalidate();
    }
}
