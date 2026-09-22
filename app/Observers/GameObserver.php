<?php

namespace App\Observers;

use App\Models\Game;
use App\Services\GameEventService;
use App\Services\StorefrontPageCache;
use Illuminate\Support\Carbon;

class GameObserver
{
    public function updated(Game $game): void
    {
        if ($game->wasChanged(['name', 'slug', 'cover', 'status'])) {
            app(StorefrontPageCache::class)->invalidate('playlist');
        }

        if (! $game->wasChanged('release_date')) {
            return;
        }

        $oldValue = $game->getRawOriginal('release_date');
        $oldDate = $oldValue ? Carbon::parse($oldValue)->toDateString() : null;

        app(GameEventService::class)->syncReleaseDateChange($game, $oldDate);
    }
}
