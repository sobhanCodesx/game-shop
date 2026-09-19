<?php

namespace App\Observers;

use App\Jobs\BroadcastContentPublished;
use App\Models\SocialContent;
use App\Services\GameEventService;
use Illuminate\Support\Carbon;

class SocialContentObserver
{
    public function created(SocialContent $content): void
    {
        if ($this->isPublished($content)) {
            $this->dispatch($content);
        }
    }

    public function updated(SocialContent $content): void
    {
        $wasPublished = $this->wasPublished($content);
        $isPublished = $this->isPublished($content);

        if (! $wasPublished && $isPublished) {
            $this->dispatch($content);
        }

        if ($wasPublished && ! $isPublished) {
            app(GameEventService::class)->expireFromContent($content);
        }
    }

    private function dispatch(SocialContent $content): void
    {
        if ($content->game_id && ($content->notify_followers ?? true)) {
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
