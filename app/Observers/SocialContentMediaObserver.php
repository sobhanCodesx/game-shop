<?php

namespace App\Observers;

use App\Models\SocialContentMedia;
use App\Services\VideoPageCache;

class SocialContentMediaObserver
{
    public function created(SocialContentMedia $media): void
    {
        $this->invalidateIfVideo($media);
    }

    public function updated(SocialContentMedia $media): void
    {
        if ($media->wasChanged(['type', 'path', 'thumbnail', 'mime', 'width', 'height', 'duration', 'alt', 'sort_order'])) {
            $this->invalidateIfVideo($media);
        }
    }

    public function deleted(SocialContentMedia $media): void
    {
        $this->invalidateIfVideo($media);
    }

    private function invalidateIfVideo(SocialContentMedia $media): void
    {
        $content = $media->content()->first(['id', 'type']);

        if ($content?->type === 'video') {
            app(VideoPageCache::class)->invalidate();
        }
    }
}
