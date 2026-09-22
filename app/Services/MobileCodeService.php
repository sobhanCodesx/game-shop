<?php

namespace App\Services;

use App\Models\MobileVerificationCode;
use App\Models\User;
use App\Services\Sms\SmsPattern;
use App\Services\Sms\SmsService;
use App\Services\Telegram\TelegramApiClient;
use App\Services\Telegram\TelegramBotSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class MobileCodeService
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly TelegramApiClient $telegram,
        private readonly TelegramBotSettings $telegramSettings,
    ) {}

    public function send(string $phone, string $purpose): void
    {
        $existing = MobileVerificationCode::query()->where(compact('phone', 'purpose'))->first();
        if ($existing?->sent_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages(['phone' => 'برای ارسال دوباره کد، کمی صبر کنید.']);
        }

        DB::transaction(function () use ($phone, $purpose): void {
            [$record, $code, $sentAt] = $this->issue($phone, $purpose);

            $pattern = match ($purpose) {
                'reset_password' => SmsPattern::OtpResetPassword,
                'passwordless_login' => SmsPattern::OtpPasswordlessLogin,
                default => SmsPattern::OtpVerifyMobile,
            };

            $this->sms->enqueue(
                $pattern,
                $phone,
                ['code' => $code],
                "otp:{$record->id}:{$sentAt->getTimestamp()}",
            );
        });
    }

    public function sendViaTelegram(
        string $phone,
        string $purpose,
        bool $allowUnverifiedPhone = false,
    ): void {
        $query = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_linked_at');

        if (! $allowUnverifiedPhone) {
            $query->whereNotNull('phone_verified_at');
        }

        $user = $query->first();

        if (! $user || ! $this->telegramAvailable($phone, $allowUnverifiedPhone)) {
            throw ValidationException::withMessages([
                'telegram' => 'تلگرام هنوز به این حساب PlayNexus متصل نشده است.',
            ]);
        }

        $record = MobileVerificationCode::query()->where(compact('phone', 'purpose'))->first();
        if ($record?->telegram_sent_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages([
                'telegram' => 'کد تلگرام همین الان ارسال شده؛ کمی صبر کنید.',
            ]);
        }

        $code = null;
        if ($record && $record->expires_at?->isFuture() && filled($record->code_ciphertext)) {
            try {
                $code = Crypt::decryptString((string) $record->code_ciphertext);
            } catch (Throwable) {
                $code = null;
            }
        }

        if (! is_string($code) || ! preg_match('/^\d{6}$/', $code)) {
            [$record, $code] = $this->issue($phone, $purpose);
        }

        $title = match ($purpose) {
            'reset_password' => 'کد بازیابی رمز PlayNexus',
            'passwordless_login' => 'کد ورود PlayNexus',
            default => 'کد تأیید PlayNexus',
        };

        $this->telegram->sendMessage(
            (string) $user->telegram_chat_id,
            "🔐 <b>{$title}</b>\n"
            ."کد شما: <code>{$code}</code>\n\n"
            ."این کد را در اختیار هیچ‌کس قرار نده. اعتبار کد ۱۰ دقیقه است.",
        );

        $record->forceFill(['telegram_sent_at' => now()])->save();
    }

    public function telegramAvailable(string $phone, bool $allowUnverifiedPhone = false): bool
    {
        $settings = $this->telegramSettings->resolved();

        if (! ($settings['enabled'] ?? false) || blank($settings['bot_token'] ?? null)) {
            return false;
        }

        $query = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_linked_at');

        if (! $allowUnverifiedPhone) {
            $query->whereNotNull('phone_verified_at');
        }

        return $query->exists();
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

    /** @return array{0:MobileVerificationCode,1:string,2:\Illuminate\Support\Carbon} */
    private function issue(string $phone, string $purpose): array
    {
        $code = (string) random_int(100000, 999999);
        $sentAt = now();

        $record = MobileVerificationCode::query()->updateOrCreate(
            compact('phone', 'purpose'),
            [
                'code_hash' => Hash::make($code),
                'code_ciphertext' => Crypt::encryptString($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes(10),
                'sent_at' => $sentAt,
                'telegram_sent_at' => null,
            ],
        );

        return [$record, $code, $sentAt];
    }
}
