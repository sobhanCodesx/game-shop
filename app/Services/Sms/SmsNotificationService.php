<?php

namespace App\Services\Sms;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsNotificationService
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(User $recipient, SmsPattern $pattern, array $variables, ?string $idempotencyKey = null): void
    {
        if (! filled($recipient->phone)) {
            return;
        }
        try {
            $this->sms->enqueue($pattern, $recipient->phone, $variables, $idempotencyKey);
        } catch (Throwable $exception) {
            Log::error('Notification SMS could not be enqueued', ['user_id' => $recipient->id, 'pattern' => $pattern->value, 'exception' => $exception::class]);
        }
    }
}
