<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Services\Sms\SmsPattern;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContentPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Product|SocialContent $content) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['database'];
        }

        $preference = $notifiable->contentNotificationPreference;
        $channels = [];

        if ($preference?->feed_enabled ?? true) {
            $channels[] = 'database';
            $channels[] = ExpoPushChannel::class;
        }

        if (($preference?->sms_enabled ?? true) && filled($notifiable->phone)) {
            $channels[] = SmsChannel::class;
        }
        if (($preference?->email_enabled ?? false) && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->typeLabel().' جدید در '.$this->channelName(),
            'message' => $this->content->title.' منتشر شد.',
            'url' => $this->url(),
            'activity' => 'content_published',
            'content_type' => $this->contentType(),
            'content_id' => $this->content->id,
            'game_id' => $this->content->game_id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->typeLabel().' جدید در '.$this->channelName())
            ->greeting('سلام '.$notifiable->name)
            ->line($this->content->title.' در کانالی که عضو آن هستید منتشر شد.')
            ->action('مشاهده '.$this->typeLabel(), url($this->url()))
            ->line('این پیام فقط براساس تنظیمات اطلاع‌رسانی محتوای حساب شما ارسال شده است.');
    }

    public function toSms(object $notifiable): array
    {
        return [
            'pattern' => SmsPattern::ContentPublished,
            'variables' => [
                'channel' => $this->channelName(),
                'type' => $this->typeLabel(),
                'title' => $this->content->title,
            ],
            'idempotency_key' => 'content-published:'.$this->contentType().':'.$this->content->id,
        ];
    }

    private function contentType(): string
    {
        return $this->content instanceof Product ? 'product' : $this->content->type;
    }

    private function typeLabel(): string
    {
        if ($this->content instanceof Product) {
            return 'محصول';
        }

        return match ($this->content->type) {
            'short' => 'ویدیوی کوتاه',
            'post' => 'پست فید',
            default => 'ویدیو',
        };
    }

    private function channelName(): string
    {
        return $this->content->relationLoaded('game')
            ? ($this->content->game?->name ?? 'PlayNexus')
            : ($this->content->game()->value('name') ?? 'PlayNexus');
    }

    private function url(): string
    {
        if ($this->content instanceof Product) {
            return route('products.show', $this->content->slug, false);
        }

        if ($this->content->type === 'post') {
            return route('posts.show', $this->content->slug, false);
        }

        $type = match ($this->content->type) {
            'post' => 'posts',
            'short' => 'shorts',
            default => 'videos',
        };

        return route('content.show', ['type' => $type, 'content' => $this->content->slug], false);
    }
}
