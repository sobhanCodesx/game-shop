<?php

namespace App\Notifications;

use App\Notifications\Channels\ExpoPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminPushTestNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return [ExpoPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'تست نوتیفیکیشن PlayNexus',
            'message' => 'ارسال Push موبایل با موفقیت تا صف Expo رسید.',
            'url' => '/account',
            'activity' => 'admin_push_test',
        ];
    }
}
