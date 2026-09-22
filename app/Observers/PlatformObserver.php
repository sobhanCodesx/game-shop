<?php

namespace App\Observers;

use App\Models\Platform;
use App\Services\StorefrontPageCache;

class PlatformObserver
{
    public function created(Platform $platform): void { $this->invalidate(); }
    public function updated(Platform $platform): void { $this->invalidate(); }
    public function deleted(Platform $platform): void { $this->invalidate(); }
    public function restored(Platform $platform): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('product');
    }
}
