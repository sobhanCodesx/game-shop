<?php

namespace App\Services\Telegram;

use App\Models\User;
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
        $settings = $this->settings->resolved();
        $username = ltrim(trim((string) ($settings['bot_username'] ?? '')), '@');

        if (! ($settings['enabled'] ?? false) || $username === '') {
            throw new RuntimeException('ربات تلگرام هنوز برای اتصال کاربران آماده نیست.');
        }

        $token = Str::random(32);
        Cache::put($this->key($token), $user->id, now()->addMinutes(self::TTL_MINUTES));

        return "https://t.me/{$username}?start=connect_{$token}";
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

        $conflict = User::query()
            ->where('telegram_user_id', $telegramUserId)
            ->whereKeyNot((int) $userId)
            ->exists();

        if ($conflict) {
            throw new RuntimeException('این حساب تلگرام قبلاً به یک حساب PlayNexus دیگر متصل شده است.');
        }

        $user = User::query()->findOrFail((int) $userId);
        $user->forceFill([
            'telegram_user_id' => $telegramUserId,
            'telegram_chat_id' => $chatId,
            'telegram_linked_at' => now(),
        ])->save();

        $user->contentNotificationPreference()->updateOrCreate([], [
            'telegram_enabled' => true,
        ]);

        return $user->fresh();
    }

    public function disconnect(User $user): void
    {
        $user->forceFill([
            'telegram_user_id' => null,
            'telegram_chat_id' => null,
            'telegram_linked_at' => null,
        ])->save();

        $user->contentNotificationPreference()->updateOrCreate([], [
            'telegram_enabled' => false,
        ]);
    }

    public function byTelegramUserId(string $telegramUserId): ?User
    {
        return User::query()->where('telegram_user_id', $telegramUserId)->first();
    }

    private function key(string $token): string
    {
        return 'telegram-user-link:'.hash('sha256', $token);
    }
}
