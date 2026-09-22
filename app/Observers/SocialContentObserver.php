<?php

namespace App\Observers;

use App\Jobs\BroadcastContentPublished;
use App\Models\SocialContent;
use App\Services\GameEventService;
use App\Services\SitemapCacheService;
use App\Services\StorefrontPageCache;
use Illuminate\Support\Carbon;

class SocialContentObserver
{
    public function created(SocialContent $content): void
    {
        if (in_array($content->type, ['post', 'video', 'short'], true)) {
            app(SitemapCacheService::class)->invalidate();
        }
        if ($content->type === 'video') {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'home', 'studio', 'video', 'feed');
        } elseif ($content->type === 'post') {
            app(StorefrontPageCache::class)->invalidate('channel', 'feed');
        } elseif ($content->type === 'short') {
            app(StorefrontPageCache::class)->invalidate('short', 'feed');
        }

        if ($this->isPublished($content)) {
            $this->dispatch($content);
        }
    }

    public function updated(SocialContent $content): void
    {
        if (
            in_array($content->type, ['post', 'video'], true)
            || in_array($content->getRawOriginal('type'), ['post', 'video'], true)
        ) {
            if ($content->wasChanged([
                'game_id', 'related_product_id', 'related_content_id', 'type', 'feed_type', 'feed_badge',
                'title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description',
                'thumbnail', 'video_path', 'video_mime', 'duration',
                'allow_comments', 'featured', 'status', 'published_at',
            ])) {
                app(SitemapCacheService::class)->invalidate();
                app(StorefrontPageCache::class)->invalidate('channel', 'feed');

                if ($content->type === 'video' || $content->getRawOriginal('type') === 'video') {
                    app(StorefrontPageCache::class)->invalidate('playlist', 'home', 'studio', 'video', 'feed');
                }
            }
        }

        if (
            ($content->type === 'short' || $content->getRawOriginal('type') === 'short')
            && $content->wasChanged([
                'game_id', 'type', 'title', 'slug', 'excerpt', 'body', 'media_type',
                'thumbnail', 'video_path', 'video_mime', 'duration', 'link_url', 'link_label',
                'sort_order', 'status', 'published_at',
            ])
        ) {
            app(StorefrontPageCache::class)->invalidate('short', 'feed');
            app(SitemapCacheService::class)->invalidate();
        }

        $wasPublished = $this->wasPublished($content);
        $isPublished = $this->isPublished($content);

        if (! $wasPublished && $isPublished) {
            $this->dispatch($content);
        }

        if ($wasPublished && ! $isPublished) {
            app(GameEventService::class)->expireFromContent($content);
        }

        if (
            $wasPublished
            && $isPublished
            && $content->wasChanged(['title', 'excerpt', 'body', 'feed_type', 'feed_badge', 'game_id'])
        ) {
            app(GameEventService::class)->refreshFromContent($content);
        }
    }

    public function deleted(SocialContent $content): void
    {
        if (in_array($content->type, ['post', 'video', 'short'], true)) {
            app(SitemapCacheService::class)->invalidate();
        }
        if ($content->type === 'video') {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel', 'home', 'studio', 'video', 'feed');
        } elseif ($content->type === 'post') {
            app(StorefrontPageCache::class)->invalidate('channel', 'feed');
        } elseif ($content->type === 'short') {
            app(StorefrontPageCache::class)->invalidate('short');
        }
    }

    private function dispatch(SocialContent $content): void
    {
        if ($content->game_id) {
            BroadcastContentPublished::dispatch('social', $content->id)->afterCommit();
        }
    }

    private function isPublished(SocialContent $content): bool
    {
        return $content->status === 'published' && $content->published_at?->isPast();
    }

    private function wasPublished(SocialContent $content): bool
    {
        $publishedAt = $content->getRawOriginal('published_at');

        return $content->getRawOriginal('status') === 'published'
            && $publishedAt
            && Carbon::parse($publishedAt)->isPast();
    }
}
