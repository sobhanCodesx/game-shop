<?php

namespace App\Observers;

use App\Models\VideoPlaylist;
use App\Services\VideoPageCache;

class VideoPlaylistObserver
{
    public function created(VideoPlaylist $playlist): void
    {
        app(VideoPageCache::class)->invalidate();
    }

    public function updated(VideoPlaylist $playlist): void
    {
        if ($playlist->wasChanged(['game_id', 'studio_id', 'title', 'slug', 'logo', 'description', 'visibility', 'sort_order'])) {
            app(VideoPageCache::class)->invalidate();
        }
    }

    public function deleted(VideoPlaylist $playlist): void
    {
        app(VideoPageCache::class)->invalidate();
    }
}
