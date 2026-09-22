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
        if ($game->wasChanged(['studio_id', 'name', 'slug', 'description', 'cover', 'background', 'release_date', 'developer', 'publisher', 'age_rating', 'status'])) {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel');
        }

        if (! $game->wasChanged('release_date')) {
            return;
        }

        $oldValue = $game->getRawOriginal('release_date');
        $oldDate = $oldValue ? Carbon::parse($oldValue)->toDateString() : null;

        app(GameEventService::class)->syncReleaseDateChange($game, $oldDate);
    }
}
