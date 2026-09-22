<?php

namespace App\Observers;

use App\Models\SocialContentMedia;
use App\Services\SitemapCacheService;
use App\Services\StorefrontPageCache;

class SocialContentMediaObserver
{
    public function created(SocialContentMedia $media): void
    {
        $this->invalidate($media);
    }

    public function updated(SocialContentMedia $media): void
    {
        if ($media->wasChanged(['social_content_id', 'type', 'path', 'thumbnail', 'mime', 'width', 'height', 'duration', 'alt', 'sort_order'])) {
            $this->invalidate($media);
        }
    }

    public function deleted(SocialContentMedia $media): void
    {
        $this->invalidate($media);
    }

    private function invalidate(SocialContentMedia $media): void
    {
        $type = $media->content()->value('type');

        if ($type === 'video') {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel');
            app(SitemapCacheService::class)->invalidate();
        } elseif ($type === 'post') {
            app(StorefrontPageCache::class)->invalidate('channel');
        } elseif ($type === 'short') {
            app(StorefrontPageCache::class)->invalidate('short');
            app(SitemapCacheService::class)->invalidate();
        }
    }
}
