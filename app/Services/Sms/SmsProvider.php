<?php

namespace App\Services\Sms;

interface SmsProvider
{
    public function send(string|array $to, string $text): SmsSendResult;

    public function sendPattern(string $mobile, string $patternId, array $variables): SmsSendResult;
}
