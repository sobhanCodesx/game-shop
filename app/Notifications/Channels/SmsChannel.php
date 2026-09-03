<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Sms\SmsNotificationService;
use App\Services\Sms\SmsPattern;
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
        $this->sms->send(
            $notifiable,
            $payload['pattern'] instanceof SmsPattern ? $payload['pattern'] : SmsPattern::from((string) $payload['pattern']),
            (array) $payload['variables'],
            isset($payload['idempotency_key']) ? (string) $payload['idempotency_key'].':user:'.$notifiable->id : null,
        );
    }
}
