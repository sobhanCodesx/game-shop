<?php

namespace App\Observers;

use App\Models\CategoryAttribute;
use App\Services\StorefrontPageCache;

class CategoryAttributeObserver
{
    public function created(CategoryAttribute $attribute): void { $this->invalidate(); }
    public function updated(CategoryAttribute $attribute): void { $this->invalidate(); }
    public function deleted(CategoryAttribute $attribute): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('product');
    }
}
