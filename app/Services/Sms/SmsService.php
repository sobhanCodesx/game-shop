<?php

namespace App\Services\Sms;

use App\Models\SmsOutbox;
use App\Support\PhoneNumber;
use InvalidArgumentException;

class SmsService
{
    public const PLAIN_TEST_PATTERN = 'plain_test';

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
            return SmsOutbox::query()->createOrFirst(['idempotency_key' => $idempotencyKey], $attributes);
        }

        return SmsOutbox::query()->create($attributes);
    }

    public function enqueuePlainTest(string $mobile, string $message): SmsOutbox
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('Test SMS message cannot be empty.');
        }

        return SmsOutbox::query()->create([
            'mobile' => PhoneNumber::normalize($mobile),
            'pattern' => self::PLAIN_TEST_PATTERN,
            'payload' => ['message' => $message],
            'status' => 'pending',
            'idempotency_key' => 'admin-sms-test:'.str()->uuid(),
        ]);
    }
}
