<?php

namespace App\Services\Sms;

final class SmsMessageFormatter
{
    public const OPT_OUT_FOOTER = 'لغو11';

    public static function withOptOutFooter(string $message): string
    {
        $message = rtrim($message);
        if (preg_match('/(?:^|\R)'.preg_quote(self::OPT_OUT_FOOTER, '/').'$/u', $message) === 1) {
            return $message;
        }

        return $message."\n".self::OPT_OUT_FOOTER;
    }

    public static function appendToLastVariable(array $variables): array
    {
        $lastKey = array_key_last($variables);
        if ($lastKey !== null) {
            $variables[$lastKey] = self::withOptOutFooter((string) $variables[$lastKey]);
        }

        return $variables;
    }
}
