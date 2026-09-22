<?php

namespace App\Observers;

use App\Jobs\BroadcastContentPublished;
use App\Models\SocialContent;
use App\Services\GameEventService;
use App\Services\StorefrontPageCache;
use Illuminate\Support\Carbon;

class SocialContentObserver
{
    public function created(SocialContent $content): void
    {
        if ($content->type === 'video') {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel');
        } elseif ($content->type === 'post') {
            app(StorefrontPageCache::class)->invalidate('channel');
        } elseif ($content->type === 'short') {
            app(StorefrontPageCache::class)->invalidate('short');
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
                'title', 'slug', 'excerpt', 'body', 'thumbnail', 'video_path', 'duration',
                'allow_comments', 'featured', 'status', 'published_at',
            ])) {
                app(StorefrontPageCache::class)->invalidate('channel');

                if ($content->type === 'video' || $content->getRawOriginal('type') === 'video') {
                    app(StorefrontPageCache::class)->invalidate('playlist');
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
            app(StorefrontPageCache::class)->invalidate('short');
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
        if ($content->type === 'video') {
            app(StorefrontPageCache::class)->invalidate('playlist', 'channel');
        } elseif ($content->type === 'post') {
            app(StorefrontPageCache::class)->invalidate('channel');
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
