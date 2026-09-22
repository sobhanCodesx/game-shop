<?php

namespace App\Observers;

use App\Jobs\BroadcastContentPublished;
use App\Models\Product;
use App\Services\GameEventService;
use App\Services\SitemapCacheService;
use App\Services\StorefrontPageCache;
use Illuminate\Support\Carbon;

class ProductObserver
{
    public function created(Product $product): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home', 'product');
        app(SitemapCacheService::class)->invalidate();

        if ($this->isPublished($product)) {
            $this->dispatch($product);
        }
    }

    public function updated(Product $product): void
    {
        app(SitemapCacheService::class)->invalidate();

        if ($product->wasChanged([
            'category_id', 'brand_id', 'title', 'slug', 'sku', 'short_description', 'description',
            'release_date', 'seo_title', 'seo_description', 'status', 'visibility', 'published_at',
        ])) {
            app(StorefrontPageCache::class)->invalidate('channel', 'home', 'product');
        }

        if (! $this->wasPublished($product) && $this->isPublished($product)) {
            $this->dispatch($product);
        }

        if ($product->wasChanged(['price', 'discount_price', 'expires_at', 'status', 'visibility'])) {
            app(GameEventService::class)->syncFromProduct($product);
        }
    }

    public function deleted(Product $product): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home', 'product');
        app(SitemapCacheService::class)->invalidate();
    }

    public function restored(Product $product): void
    {
        app(StorefrontPageCache::class)->invalidate('channel', 'home', 'product');
        app(SitemapCacheService::class)->invalidate();
    }

    private function dispatch(Product $product): void
    {
        if ($product->game_id) {
            BroadcastContentPublished::dispatch('product', $product->id)->afterCommit();
        }
    }

    private function isPublished(Product $product): bool
    {
        return $product->status === 'published'
            && $product->visibility === 'public'
            && (! $product->published_at || $product->published_at->isPast());
    }

    private function wasPublished(Product $product): bool
    {
        $publishedAt = $product->getRawOriginal('published_at');

        return $product->getRawOriginal('status') === 'published'
            && $product->getRawOriginal('visibility') === 'public'
            && (! $publishedAt || Carbon::parse($publishedAt)->isPast());
    }
}
