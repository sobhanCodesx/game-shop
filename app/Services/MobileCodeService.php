<?php

namespace App\Services;

use App\Models\MobileVerificationCode;
use App\Services\Sms\PayamakPanelSmsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileCodeService
{
    public function __construct(private readonly PayamakPanelSmsService $sms) {}

    public function send(string $phone, string $purpose): void
    {
        $existing = MobileVerificationCode::query()->where(compact('phone', 'purpose'))->first();
        if ($existing?->sent_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages(['phone' => 'برای ارسال دوباره کد، کمی صبر کنید.']);
        }
        $code = (string) random_int(100000, 999999);
        $record = MobileVerificationCode::query()->updateOrCreate(compact('phone', 'purpose'), [
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
            'sent_at' => now(),
        ]);

        try {
            $result = $this->sms->send($phone, $this->message($purpose, $code));
            if (! $result->success) {
                throw ValidationException::withMessages(['phone' => $result->message]);
            }
        } catch (Throwable $exception) {
            $record->delete();
            throw $exception;
        }
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

    private function message(string $purpose, string $code): string
    {
        $title = match ($purpose) {
            'reset_password' => 'کد بازیابی رمز عبور NEXUS PLAY',
            'passwordless_login' => 'کد ورود NEXUS PLAY',
            default => 'کد تأیید شماره موبایل NEXUS PLAY',
        };

        return "{$title}\n{$code}\nلغو11";
    }
}
