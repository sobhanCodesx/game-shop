<?php

namespace App\Observers;

use App\Jobs\BroadcastContentPublished;
use App\Models\Product;
use App\Services\GameEventService;
use Illuminate\Support\Carbon;

class ProductObserver
{
    public function created(Product $product): void
    {
        if ($this->isPublished($product)) {
            $this->dispatch($product);
        }
    }

    public function updated(Product $product): void
    {
        if (! $this->wasPublished($product) && $this->isPublished($product)) {
            $this->dispatch($product);
        }

        if ($product->wasChanged(['price', 'discount_price', 'expires_at', 'status', 'visibility'])) {
            app(GameEventService::class)->syncFromProduct($product);
        }
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
