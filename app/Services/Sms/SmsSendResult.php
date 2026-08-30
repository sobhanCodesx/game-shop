<?php

namespace App\Services\Sms;

final readonly class SmsSendResult
{
    public function __construct(public bool $success, public array $messageIds, public int $status, public string $message, public array $rawResponse, public array $recipients = []) {}
}
