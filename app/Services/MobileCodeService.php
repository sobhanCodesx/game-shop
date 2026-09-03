<?php

namespace App\Services;

use App\Models\MobileVerificationCode;
use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileCodeService
{
    public function __construct(private readonly SmsService $sms) {}

    public function send(string $phone, string $purpose): void
    {
        $existing = MobileVerificationCode::query()->where(compact('phone', 'purpose'))->first();
        if ($existing?->sent_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages(['phone' => 'برای ارسال دوباره کد، کمی صبر کنید.']);
        }
        DB::transaction(function () use ($phone, $purpose): void {
            $code = (string) random_int(100000, 999999);
            $sentAt = now();
            $record = MobileVerificationCode::query()->updateOrCreate(compact('phone', 'purpose'), [
                'code_hash' => Hash::make($code), 'attempts' => 0,
                'expires_at' => now()->addMinutes(10), 'sent_at' => $sentAt,
            ]);
            $pattern = match ($purpose) {
                'reset_password' => SmsPattern::OtpResetPassword,
                'passwordless_login' => SmsPattern::OtpPasswordlessLogin,
                default => SmsPattern::OtpVerifyMobile,
            };
            $this->sms->enqueue($pattern, $phone, ['code' => $code], "otp:{$record->id}:{$sentAt->getTimestamp()}");
        });
    }

    public function verify(string $phone, string $purpose, string $code): void
    {
        $record = MobileVerificationCode::query()->where(compact('phone', 'purpose'))->first();
        if (! $record || $record->expires_at->isPast() || $record->attempts >= 5 || ! Hash::check($code, $record->code_hash)) {
            if ($record && $record->attempts < 5) {
                $record->increment('attempts');
            }
            throw ValidationException::withMessages(['code' => 'کد تأیید اشتباه، منقضی یا بیش از حد استفاده شده است.']);
        }
        $record->delete();
    }
}
