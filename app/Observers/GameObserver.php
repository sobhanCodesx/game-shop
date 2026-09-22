<?php

namespace App\Observers;

use App\Models\Game;
use App\Services\GameEventService;
use App\Services\SitemapCacheService;
use App\Services\StorefrontPageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class GameObserver
{
    public function created(Game $game): void
    {
        app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'short', 'home', 'studio', 'video', 'feed');
        app(SitemapCacheService::class)->invalidate();
    }

    public function updated(Game $game): void
    {
        app(SitemapCacheService::class)->invalidate();
        if ($game->wasChanged(['studio_id', 'name', 'slug', 'description', 'cover', 'background', 'release_date', 'developer', 'publisher', 'age_rating', 'status'])) {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'short', 'home', 'studio', 'video', 'feed');

            if ($game->wasChanged(['name', 'cover', 'status'])) {
                Cache::forget('storefront.stories.v1');
            }
        }

        if (! $game->wasChanged('release_date')) {
            return;
        }

        $oldValue = $game->getRawOriginal('release_date');
        $oldDate = $oldValue ? Carbon::parse($oldValue)->toDateString() : null;

        app(GameEventService::class)->syncReleaseDateChange($game, $oldDate);
    }

    public function deleted(Game $game): void
    {
        app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'short', 'home', 'studio', 'video', 'feed');
        app(SitemapCacheService::class)->invalidate();
    }

    public function restored(Game $game): void
    {
        app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'short', 'home', 'studio', 'video', 'feed');
        app(SitemapCacheService::class)->invalidate();
    }
}
