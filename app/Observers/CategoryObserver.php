<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\SitemapCacheService;
use App\Services\StorefrontPageCache;

class CategoryObserver
{
    public function created(Category $category): void { $this->invalidate(); }
    public function updated(Category $category): void { $this->invalidate(); }
    public function deleted(Category $category): void { $this->invalidate(); }
    public function restored(Category $category): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('home', 'product');
        app(SitemapCacheService::class)->invalidate();
    }
}
