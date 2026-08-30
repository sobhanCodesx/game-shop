<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Sms\SmsNotificationService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsNotificationService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toSms')) {
            return;
        }

        $payload = $notification->toSms($notifiable);
        $this->sms->send($notifiable, (string) $payload['title'], (string) $payload['message']);
    }
}
