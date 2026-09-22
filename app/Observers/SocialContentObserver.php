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
            app(StorefrontPageCache::class)->invalidate('playlist');
        }

        if ($this->isPublished($content)) {
            $this->dispatch($content);
        }
    }

    public function updated(SocialContent $content): void
    {
        if (
            ($content->type === 'video' || $content->getRawOriginal('type') === 'video')
            && $content->wasChanged([
                'game_id', 'type', 'title', 'slug', 'excerpt', 'thumbnail', 'video_path',
                'duration', 'status', 'published_at',
            ])
        ) {
            app(StorefrontPageCache::class)->invalidate('playlist');
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
            app(StorefrontPageCache::class)->invalidate('playlist');
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
