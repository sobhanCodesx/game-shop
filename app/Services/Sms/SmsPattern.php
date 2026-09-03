<?php

namespace App\Services\Sms;

use InvalidArgumentException;

enum SmsPattern: string
{
    case OtpVerifyMobile = 'otp_verify_mobile';
    case OtpPasswordlessLogin = 'otp_passwordless_login';
    case OtpResetPassword = 'otp_reset_password';
    case OrderActivity = 'order_activity';
    case OrderCashback = 'order_cashback';
    case TicketActivity = 'ticket_activity';

    public function requiredVariables(): array
    {
        return match ($this) {
            self::OtpVerifyMobile, self::OtpPasswordlessLogin, self::OtpResetPassword => ['code'],
            self::OrderActivity => ['title', 'order', 'products', 'amount', 'message'],
            self::OrderCashback => ['order', 'products', 'amount'],
            self::TicketActivity => ['title', 'message'],
        };
    }

    public function providerId(): string
    {
        return trim((string) config("sms.patterns.{$this->value}"));
    }

    public function validate(array $variables): array
    {
        $missing = array_diff($this->requiredVariables(), array_keys($variables));
        if ($missing !== []) {
            throw new InvalidArgumentException('Missing SMS pattern variables: '.implode(', ', $missing));
        }

        return collect($this->requiredVariables())
            ->mapWithKeys(fn (string $key) => [$key => (string) $variables[$key]])
            ->all();
    }
}
