<?php

namespace App\Services\Sms;

use App\Models\SmsOutbox;
use App\Support\PhoneNumber;

class SmsService
{
    public function enqueue(SmsPattern $pattern, string $mobile, array $variables, ?string $idempotencyKey = null): SmsOutbox
    {
        $attributes = [
            'mobile' => PhoneNumber::normalize($mobile),
            'pattern' => $pattern->value,
            'payload' => $pattern->validate($variables),
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey,
        ];

        if ($idempotencyKey !== null) {
            return SmsOutbox::query()->firstOrCreate(['idempotency_key' => $idempotencyKey], $attributes);
        }

        return SmsOutbox::query()->create($attributes);
    }
}
