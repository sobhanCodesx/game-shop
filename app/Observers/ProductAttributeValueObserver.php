<?php

namespace App\Observers;

use App\Models\ProductAttributeValue;
use App\Services\StorefrontPageCache;

class ProductAttributeValueObserver
{
    public function created(ProductAttributeValue $value): void { $this->invalidate(); }
    public function updated(ProductAttributeValue $value): void { $this->invalidate(); }
    public function deleted(ProductAttributeValue $value): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('product');
    }
}
