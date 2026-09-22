<?php

namespace App\Observers;

use App\Jobs\BroadcastContentPublished;
use App\Models\SocialContent;
use App\Services\FeedPageCache;
use App\Services\GameEventService;
use Illuminate\Support\Carbon;

class SocialContentObserver
{
    public function created(SocialContent $content): void
    {
        if (in_array($content->type, ['post', 'video'], true)) {
            app(FeedPageCache::class)->invalidate();
        }

        if ($this->isPublished($content)) {
            $this->dispatch($content);
        }
    }

    public function updated(SocialContent $content): void
    {
        if (
            in_array($content->type, ['post', 'video'], true)
            && $content->wasChanged([
                'game_id', 'related_product_id', 'related_content_id', 'type', 'feed_type', 'feed_badge',
                'title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'thumbnail',
                'video_path', 'video_mime', 'duration', 'allow_comments', 'featured',
                'sort_order', 'status', 'published_at',
            ])
        ) {
            app(FeedPageCache::class)->invalidate();
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
        if (in_array($content->type, ['post', 'video'], true)) {
            app(FeedPageCache::class)->invalidate();
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
