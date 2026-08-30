<?php

namespace App\Services\Sms;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Throwable;

class SmsNotificationService
{
    public function __construct(private readonly PayamakPanelSmsService $sms) {}

    public function send(User $recipient, string $title, string $message): void
    {
        if (! filled($recipient->phone)) {
            return;
        }

        try {
            $phone = PhoneNumber::normalize($recipient->phone);
            $text = trim($title)."\n".trim($message)."\nلغو11";
            $result = $this->sms->send($phone, $text);

            if (! $result->success) {
                Log::warning('Notification SMS rejected by provider', [
                    'provider' => 'payamak_panel',
                    'user_id' => $recipient->id,
                    'provider_status' => $result->status,
                ]);
            }
        } catch (Throwable $exception) {
            // An unavailable SMS provider must never roll back an order or ticket.
            Log::error('Notification SMS failed', [
                'provider' => 'payamak_panel',
                'user_id' => $recipient->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
