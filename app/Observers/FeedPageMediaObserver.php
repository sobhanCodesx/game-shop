<?php

namespace App\Observers;

use App\Models\SocialContentMedia;
use App\Services\FeedPageCache;

class FeedPageMediaObserver
{
    public function created(SocialContentMedia $media): void
    {
        $this->invalidateIfRelevant($media);
    }

    public function updated(SocialContentMedia $media): void
    {
        if ($media->wasChanged(['social_content_id', 'type', 'path', 'thumbnail', 'mime', 'width', 'height', 'duration', 'alt', 'sort_order'])) {
            $this->invalidateIfRelevant($media);
        }
    }

    public function deleted(SocialContentMedia $media): void
    {
        $this->invalidateIfRelevant($media);
    }

    private function invalidateIfRelevant(SocialContentMedia $media): void
    {
        $content = $media->content()->first(['id', 'type']);

        if (in_array($content?->type, ['post', 'video'], true)) {
            app(FeedPageCache::class)->invalidate();
        }
    }
}
