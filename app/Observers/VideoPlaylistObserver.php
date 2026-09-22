<?php

namespace App\Observers;

use App\Models\VideoPlaylist;
use App\Services\StorefrontPageCache;

class VideoPlaylistObserver
{
    public function created(VideoPlaylist $playlist): void
    {
        $this->invalidate();
    }

    public function updated(VideoPlaylist $playlist): void
    {
        if ($playlist->wasChanged(['game_id', 'studio_id', 'title', 'slug', 'logo', 'description', 'visibility', 'sort_order'])) {
            $this->invalidate();
        }
    }

    public function deleted(VideoPlaylist $playlist): void
    {
        $this->invalidate();
    }

    private function invalidate(): void
    {
        app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'short', 'home');
    }
}
