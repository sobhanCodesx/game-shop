<?php

namespace App\Services\Sms;

final readonly class SmsDeliveryResult
{
    public function __construct(public int $messageId, public int $code, public string $label, public bool $isDelivered, public bool $isFinal, public array $rawResponse) {}
}
