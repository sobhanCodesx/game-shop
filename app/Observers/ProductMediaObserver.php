<?php

namespace App\Observers;

use App\Models\ProductMedia;
use App\Services\StorefrontPageCache;

class ProductMediaObserver
{
    public function created(ProductMedia $media): void { $this->invalidate(); }
    public function updated(ProductMedia $media): void { $this->invalidate(); }
    public function deleted(ProductMedia $media): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('home', 'product', 'feed');
    }
}
