<?php

namespace App\Services\Telegram;

use App\Models\MobileVerificationCode;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

final class TelegramUserLinkService
{
    private const TTL_MINUTES = 10;

    public function __construct(
        private readonly TelegramBotSettings $settings,
    ) {}

    public function begin(User $user): string
    {
        $username = $this->botUsername();
        $token = Str::random(32);
        Cache::put($this->key($token), $user->id, now()->addMinutes(self::TTL_MINUTES));

        return "https://t.me/{$username}?start=connect_{$token}";
    }

    public function beginPhoneVerification(User $user): string
    {
        if (! filled($user->phone) || $user->phone_verified_at) {
            throw new RuntimeException('این حساب نیاز به تأیید شماره موبایل ندارد.');
        }

        $username = $this->botUsername();
        $phone = PhoneNumber::normalize((string) $user->phone);
        $token = Str::random(32);

        Cache::put(
            $this->phoneTokenKey($token),
            ['user_id' => $user->id, 'phone' => $phone],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return "https://t.me/{$username}?start=verifyphone_{$token}";
    }

    public function startPhoneVerification(
        string $token,
        string $telegramUserId,
        string $chatId,
    ): User {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            throw new RuntimeException('لینک تأیید شماره معتبر نیست.');
        }

        $payload = Cache::pull($this->phoneTokenKey($token));
        if (! is_array($payload) || empty($payload['user_id']) || empty($payload['phone'])) {
            throw new RuntimeException('لینک تأیید شماره منقضی شده؛ از صفحه ثبت‌نام یک لینک تازه بگیر.');
        }

        $user = User::query()->findOrFail((int) $payload['user_id']);
        $phone = PhoneNumber::normalize((string) $payload['phone']);

        if (
            $user->status !== 'active'
            || $user->phone_verified_at
            || PhoneNumber::normalize((string) $user->phone) !== $phone
        ) {
            throw new RuntimeException('درخواست تأیید شماره دیگر معتبر نیست.');
        }

        Cache::put(
            $this->phonePendingKey($telegramUserId, $chatId),
            ['user_id' => $user->id, 'phone' => $phone],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $user;
    }

    public function completePhoneVerification(
        string $telegramUserId,
        string $chatId,
        array $contact,
    ): User {
        $payload = Cache::get($this->phonePendingKey($telegramUserId, $chatId));
        if (! is_array($payload) || empty($payload['user_id']) || empty($payload['phone'])) {
            throw new RuntimeException('درخواست تأیید شماره پیدا نشد یا منقضی شده است.');
        }

        $contactUserId = isset($contact['user_id']) ? (string) $contact['user_id'] : '';
        if ($contactUserId === '' || ! hash_equals($telegramUserId, $contactUserId)) {
            throw new RuntimeException('فقط شماره متعلق به همان حساب Telegram قابل قبول است؛ مخاطب دیگری را ارسال نکن.');
        }

        $contactPhone = PhoneNumber::normalize((string) ($contact['phone_number'] ?? ''));
        $expectedPhone = PhoneNumber::normalize((string) $payload['phone']);

        if ($contactPhone === '' || ! hash_equals($expectedPhone, $contactPhone)) {
            throw new RuntimeException('شماره Telegram با شماره‌ای که در PlayNexus وارد کردی یکسان نیست.');
        }

        $user = User::query()->findOrFail((int) $payload['user_id']);
        if (
            $user->phone_verified_at
            || PhoneNumber::normalize((string) $user->phone) !== $expectedPhone
        ) {
            throw new RuntimeException('درخواست تأیید شماره دیگر معتبر نیست.');
        }

        $user = $this->linkUser($user, $telegramUserId, $chatId);
        $user->forceFill(['phone_verified_at' => now()])->save();

        // A matching Telegram request_contact is itself the verification proof.
        // Any previously issued SMS verification codes for this phone must stop
        // being usable once Telegram has verified the number.
        MobileVerificationCode::query()
            ->where('phone', $expectedPhone)
            ->whereIn('purpose', ['verify_mobile', 'checkout_verify_mobile'])
            ->delete();

        Cache::forget($this->phonePendingKey($telegramUserId, $chatId));
        Cache::put(
            $this->phoneProofKey($user),
            true,
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $user->fresh();
    }

    public function hasRecentPhoneProof(User $user): bool
    {
        return (bool) Cache::get($this->phoneProofKey($user), false);
    }

    public function consume(string $token, string $telegramUserId, string $chatId): User
    {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            throw new RuntimeException('لینک اتصال تلگرام معتبر نیست.');
        }

        $userId = Cache::pull($this->key($token));
        if (! $userId) {
            throw new RuntimeException('لینک اتصال تلگرام منقضی شده؛ از داشبورد یک لینک تازه بگیر.');
        }

        $user = User::query()->findOrFail((int) $userId);

        return $this->linkUser($user, $telegramUserId, $chatId);
    }

    public function disconnect(User $user): void
    {
        $user->forceFill([
            'telegram_user_id' => null,
            'telegram_chat_id' => null,
            'telegram_linked_at' => null,
        ])->save();

        $preference = $user->contentNotificationPreference()->firstOrNew();
        if (! $preference->exists) {
            $preference->fill([
                'sms_enabled' => true,
                'email_enabled' => false,
                'feed_enabled' => false,
            ]);
        }
        $preference->telegram_enabled = false;
        $preference->save();
    }

    public function byTelegramUserId(string $telegramUserId): ?User
    {
        return User::query()->where('telegram_user_id', $telegramUserId)->first();
    }

    private function linkUser(User $user, string $telegramUserId, string $chatId): User
    {
        $conflict = User::query()
            ->where('telegram_user_id', $telegramUserId)
            ->whereKeyNot($user->id)
            ->exists();

        if ($conflict) {
            throw new RuntimeException('این حساب تلگرام قبلاً به یک حساب PlayNexus دیگر متصل شده است.');
        }

        $user->forceFill([
            'telegram_user_id' => $telegramUserId,
            'telegram_chat_id' => $chatId,
            'telegram_linked_at' => now(),
        ])->save();

        $preference = $user->contentNotificationPreference()->firstOrNew();
        if (! $preference->exists) {
            $preference->fill([
                'sms_enabled' => true,
                'email_enabled' => false,
                'feed_enabled' => false,
            ]);
        }
        $preference->telegram_enabled = true;
        $preference->save();

        return $user->fresh();
    }

    private function botUsername(): string
    {
        $settings = $this->settings->resolved();
        $username = ltrim(trim((string) ($settings['bot_username'] ?? '')), '@');

        if (! ($settings['enabled'] ?? false) || $username === '') {
            throw new RuntimeException('ربات تلگرام هنوز برای اتصال کاربران آماده نیست.');
        }

        return $username;
    }

    private function key(string $token): string
    {
        return 'telegram-user-link:'.hash('sha256', $token);
    }

    private function phoneTokenKey(string $token): string
    {
        return 'telegram-phone-token:'.hash('sha256', $token);
    }

    private function phonePendingKey(string $telegramUserId, string $chatId): string
    {
        return 'telegram-phone-pending:'.hash('sha256', $telegramUserId.'|'.$chatId);
    }

    private function phoneProofKey(User $user): string
    {
        return 'telegram-phone-proof:'.$user->id.':'.hash('sha256', PhoneNumber::normalize((string) $user->phone));
    }
}
