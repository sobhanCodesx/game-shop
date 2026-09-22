<?php

namespace App\Observers;

use App\Models\Game;
use App\Services\FeedPageCache;
use App\Services\GameEventService;
use Illuminate\Support\Carbon;

class GameObserver
{
    public function updated(Game $game): void
    {
        if ($game->wasChanged(['name', 'slug', 'cover'])) {
            app(FeedPageCache::class)->invalidate();
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
        app(FeedPageCache::class)->invalidate();
    }

    public function restored(Game $game): void
    {
        app(FeedPageCache::class)->invalidate();
    }
}
