<?php

namespace App\Observers;

use App\Models\Brand;
use App\Services\StorefrontPageCache;

class BrandObserver
{
    public function created(Brand $brand): void { $this->invalidate(); }
    public function updated(Brand $brand): void { $this->invalidate(); }
    public function deleted(Brand $brand): void { $this->invalidate(); }
    public function restored(Brand $brand): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('product');
    }
}
