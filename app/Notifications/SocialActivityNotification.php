<?php

namespace App\Notifications;

use App\Models\SocialContent;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SocialActivityNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SocialContent $content,
        private readonly User $actor,
        private readonly string $activity,
        private readonly ?int $commentId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        [$title, $message] = match ($this->activity) {
            'reaction' => ['واکنش جدید', $this->actor->name.' به محتوای شما واکنش نشان داد.'],
            'reply' => ['پاسخ جدید', $this->actor->name.' به نظر شما پاسخ داد.'],
            'mention' => ['از شما نام برده شد', $this->actor->name.' در یک نظر از شما نام برد.'],
            'comment_like' => ['پسندیدن نظر', $this->actor->name.' نظر شما را پسندید.'],
            default => ['نظر جدید', $this->actor->name.' برای محتوای شما نظر گذاشت.'],
        };

        $type = match ($this->content->type) {
            'post' => 'posts',
            'short' => 'shorts',
            default => 'videos',
        };

        $url = ($this->content->type === 'post'
            ? route('posts.show', $this->content->slug, false)
            : route('content.show', ['type' => $type, 'content' => $this->content->slug], false))
            .($this->commentId ? '#comments' : '');

        return [
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'activity' => $this->activity,
            'content_id' => $this->content->id,
            'comment_id' => $this->commentId,
            'actor_id' => $this->actor->id,
        ];
    }
}
