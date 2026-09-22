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

    public function send(string $phone, string $purpose): string
    {
        return DB::transaction(function () use ($phone, $purpose): string {
            $record = MobileVerificationCode::query()
                ->where(compact('phone', 'purpose'))
                ->lockForUpdate()
                ->first();

            if ($record?->sent_at?->gt(now()->subSeconds(60))) {
                throw ValidationException::withMessages([
                    'phone' => 'برای ارسال دوباره کد، کمی صبر کنید.',
                ]);
            }

            $code = $this->reusableCode($record);
            $sentAt = now();

            if ($code !== null && $record) {
                // Resends keep the same still-valid OTP so SMS and Telegram
                // never show the user two competing codes.
                $record->forceFill(['sent_at' => $sentAt])->save();
            } else {
                [$record, $code, $sentAt] = $this->issue($phone, $purpose);
            }

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

            return $code;
        });
    }

    public function sendViaTelegram(
        string $phone,
        string $purpose,
        bool $allowUnverifiedPhone = false,
    ): void {
        $settings = $this->telegramSettings->resolved();

        if (! ($settings['enabled'] ?? false) || blank($settings['bot_token'] ?? null)) {
            throw ValidationException::withMessages([
                'telegram' => 'ارسال کد از Telegram موقتاً در دسترس نیست؛ از SMS یا رمز عبور استفاده کن.',
            ]);
        }

        $user = $this->telegramUser($phone, $allowUnverifiedPhone);

        if (! $user) {
            throw ValidationException::withMessages([
                'telegram' => 'برای این درخواست امکان ارسال کد در Telegram وجود ندارد؛ از SMS یا رمز عبور استفاده کن.',
            ]);
        }

        $record = MobileVerificationCode::query()->where(compact('phone', 'purpose'))->first();
        if ($record?->telegram_sent_at?->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages([
                'telegram' => 'کد Telegram همین الان ارسال شده؛ کمی صبر کن و همان کد را استفاده کن.',
            ]);
        }

        $code = $this->reusableCode($record);

        if ($code === null) {
            // A Telegram-first login must not block an immediate SMS fallback.
            // sent_at remains outside the SMS cooldown window until SMS is requested.
            [$record, $code] = $this->issue(
                $phone,
                $purpose,
                now()->subSeconds(61),
            );
        }

        $title = match ($purpose) {
            'reset_password' => 'کد بازیابی رمز PlayNexus',
            'passwordless_login' => 'کد ورود PlayNexus',
            default => 'کد تأیید PlayNexus',
        };

        try {
            $this->telegram->sendMessage(
                (string) $user->telegram_chat_id,
                "🔐 <b>{$title}</b>\n"
                ."کد شما: <code>{$code}</code>\n\n"
                ."این کد را در اختیار هیچ‌کس قرار نده. اعتبار کد ۱۰ دقیقه است.",
            );
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'telegram' => 'ارتباط با Telegram برقرار نشد؛ SMS یا رمز عبور را امتحان کن و کمی بعد دوباره تلاش کن.',
            ]);
        }

        $record->forceFill(['telegram_sent_at' => now()])->save();
    }

    public function telegramAvailable(string $phone, bool $allowUnverifiedPhone = false): bool
    {
        $settings = $this->telegramSettings->resolved();

        return ($settings['enabled'] ?? false)
            && filled($settings['bot_token'] ?? null)
            && $this->telegramUser($phone, $allowUnverifiedPhone) !== null;
    }

    private function telegramUser(string $phone, bool $allowUnverifiedPhone = false): ?User
    {
        $query = User::query()
            ->where('phone', $phone)
            ->where('status', 'active')
            ->whereNotNull('telegram_chat_id')
            ->whereNotNull('telegram_linked_at');

        if (! $allowUnverifiedPhone) {
            $query->whereNotNull('phone_verified_at');
        }

        return $query->first();
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

    private function reusableCode(?MobileVerificationCode $record): ?string
    {
        if (
            ! $record
            || $record->attempts >= 5
            || ! $record->expires_at?->isFuture()
            || blank($record->code_ciphertext)
        ) {
            return null;
        }

        try {
            $code = Crypt::decryptString((string) $record->code_ciphertext);
        } catch (Throwable) {
            return null;
        }

        return preg_match('/^\\d{6}$/', $code) ? $code : null;
    }

    /** @return array{0:MobileVerificationCode,1:string,2:\Illuminate\Support\Carbon} */
    private function issue(
        string $phone,
        string $purpose,
        ?\Illuminate\Support\Carbon $sentAt = null,
    ): array {
        $code = (string) random_int(100000, 999999);
        $sentAt ??= now();

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
