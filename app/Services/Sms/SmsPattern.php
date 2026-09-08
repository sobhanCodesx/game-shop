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
    case ContentPublished = 'content_published';

    public function requiredVariables(): array
    {
        return match ($this) {
            self::OtpVerifyMobile, self::OtpPasswordlessLogin, self::OtpResetPassword => ['code'],
            self::OrderActivity => ['title', 'order', 'products', 'amount', 'message'],
            self::OrderCashback => ['order', 'products', 'amount'],
            self::TicketActivity => ['title', 'message'],
            self::ContentPublished => ['channel', 'type', 'title'],
        };
    }

    public function defaultProviderId(): string
    {
        return match ($this) {
            self::OtpVerifyMobile => 'CHANGE_ME_OTP_VERIFY_MOBILE',
            self::OtpPasswordlessLogin => 'CHANGE_ME_OTP_PASSWORDLESS_LOGIN',
            self::OtpResetPassword => 'CHANGE_ME_OTP_RESET_PASSWORD',
            self::OrderActivity => 'CHANGE_ME_ORDER_ACTIVITY',
            self::OrderCashback => 'CHANGE_ME_ORDER_CASHBACK',
            self::TicketActivity => 'CHANGE_ME_TICKET_ACTIVITY',
            self::ContentPublished => 'CHANGE_ME_CONTENT_PUBLISHED',
        };
    }

    public function defaultTemplate(): string
    {
        return match ($this) {
            self::OtpVerifyMobile => 'کد تأیید موبایل شما: {code}',
            self::OtpPasswordlessLogin => 'کد ورود یک‌بارمصرف شما: {code}',
            self::OtpResetPassword => 'کد بازیابی رمز عبور شما: {code}',
            self::OrderActivity => '{title} - سفارش {order} - {products} - مبلغ {amount} - {message}',
            self::OrderCashback => 'اعتبار سفارش {order} برای {products} به کیف پول شما افزوده شد - مبلغ {amount}',
            self::TicketActivity => '{title} - {message}',
            self::ContentPublished => '{type} جدید در کانال {channel}: {title}',
        };
    }

    public function hasRealProviderId(?string $providerId): bool
    {
        $providerId = trim((string) $providerId);

        return $providerId !== '' && $providerId !== $this->defaultProviderId();
    }

    public function render(array $variables): string
    {
        $variables = $this->validate($variables);

        return strtr($this->defaultTemplate(), collect($variables)
            ->mapWithKeys(fn (string $value, string $key) => ["{{$key}}" => $value])
            ->all());
    }

    public function label(): string
    {
        return match ($this) {
            self::OtpVerifyMobile => 'کد تأیید موبایل',
            self::OtpPasswordlessLogin => 'کد ورود بدون رمز',
            self::OtpResetPassword => 'کد بازیابی رمز',
            self::OrderActivity => 'اعلان سفارش',
            self::OrderCashback => 'اعتبار کیف پول',
            self::TicketActivity => 'اعلان تیکت',
            self::ContentPublished => 'انتشار محتوای جدید',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OtpVerifyMobile => 'ارسال کد تأیید هنگام ثبت یا تأیید شماره موبایل',
            self::OtpPasswordlessLogin => 'ارسال کد ورود یک‌بارمصرف بدون رمز عبور',
            self::OtpResetPassword => 'ارسال کد بازیابی و تغییر رمز عبور',
            self::OrderActivity => 'ثبت سفارش، تغییر وضعیت، لغو و اعلان‌های سفارش',
            self::OrderCashback => 'اطلاع‌رسانی ثبت اعتبار بازگشت وجه در کیف پول',
            self::TicketActivity => 'ایجاد تیکت، پاسخ و تغییرات پشتیبانی یا معاوضه',
            self::ContentPublished => 'اطلاع‌رسانی محصول، ویدیو یا محتوای جدید به اعضای کانال',
        };
    }

    public function validate(array $variables): array
    {
        $invalid = collect($this->requiredVariables())->filter(fn (string $key) => ! array_key_exists($key, $variables)
            || ! is_scalar($variables[$key])
            || trim((string) $variables[$key]) === '')->values()->all();
        if ($invalid !== []) {
            throw new InvalidArgumentException('Missing or invalid SMS pattern variables: '.implode(', ', $invalid));
        }

        return collect($this->requiredVariables())
            ->mapWithKeys(fn (string $key) => [$key => (string) $variables[$key]])
            ->all();
    }
}
