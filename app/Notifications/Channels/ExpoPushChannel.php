<?php

namespace App\Notifications\Channels;

use App\Jobs\SendExpoPushNotification;
use Illuminate\Notifications\Notification;

class ExpoPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! config('services.expo_push.enabled')) {
            return;
        }

        $deviceIds = $notifiable->mobileDevices()
            ->where('push_enabled', true)
            ->pluck('id')
            ->all();

        if ($deviceIds === []) {
            return;
        }

        $payload = method_exists($notification, 'toExpoPush')
            ? $notification->toExpoPush($notifiable)
            : $notification->toArray($notifiable);

        SendExpoPushNotification::dispatch($deviceIds, $payload)->afterCommit();
    }
}
