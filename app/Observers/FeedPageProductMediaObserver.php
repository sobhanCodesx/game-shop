<?php

namespace App\Observers;

use App\Models\ProductMedia;
use App\Services\FeedPageCache;

class FeedPageProductMediaObserver
{
    public function created(ProductMedia $media): void
    {
        app(FeedPageCache::class)->invalidate();
    }

    public function updated(ProductMedia $media): void
    {
        if ($media->wasChanged(['product_id', 'type', 'path', 'alt', 'sort_order', 'is_primary'])) {
            app(FeedPageCache::class)->invalidate();
        }
    }

    public function deleted(ProductMedia $media): void
    {
        app(FeedPageCache::class)->invalidate();
    }
}
