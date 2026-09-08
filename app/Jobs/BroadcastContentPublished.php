<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;
use App\Notifications\ContentPublishedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class BroadcastContentPublished implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $contentType,
        public readonly int $contentId,
    ) {}

    public function handle(): void
    {
        $content = match ($this->contentType) {
            'product' => Product::query()->with('game:id,name')->find($this->contentId),
            default => SocialContent::query()->with('game:id,name')->find($this->contentId),
        };

        if (! $content || ! $content->game_id || ! $this->isStillPublished($content)) {
            return;
        }
        if ($content instanceof SocialContent && $content->notify_followers === false) {
            return;
        }

        User::query()
            ->whereHas('subscribedGames', fn ($query) => $query->whereKey($content->game_id))
            ->with('contentNotificationPreference')
            ->chunkById(200, fn ($users) => Notification::send($users, new ContentPublishedNotification($content)));
    }

    private function isStillPublished(Product|SocialContent $content): bool
    {
        if ($content instanceof Product) {
            return $content->status === 'published'
                && $content->visibility === 'public'
                && (! $content->published_at || $content->published_at->isPast());
        }

        return $content->status === 'published'
            && $content->published_at?->isPast();
    }
}
