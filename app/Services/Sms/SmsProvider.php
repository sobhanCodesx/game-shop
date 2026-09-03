<?php

namespace App\Services\Sms;

interface SmsProvider
{
    public function sendPattern(string $mobile, string $patternId, array $variables): SmsSendResult;
}
