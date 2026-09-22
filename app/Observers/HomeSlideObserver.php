<?php

namespace App\Observers;

use App\Models\HomeSlide;
use App\Services\StorefrontPageCache;

class HomeSlideObserver
{
    public function created(HomeSlide $slide): void { $this->invalidate(); }
    public function updated(HomeSlide $slide): void { $this->invalidate(); }
    public function deleted(HomeSlide $slide): void { $this->invalidate(); }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('home');
    }
}
