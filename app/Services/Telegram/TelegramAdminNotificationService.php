<?php

namespace App\Services\Telegram;

use App\Models\User;
use Throwable;

final class TelegramAdminNotificationService
{
    public function __construct(
        private readonly TelegramApiClient $telegram,
        private readonly TelegramBotSettings $settings,
    ) {}

    public function newUser(User $user, string $source): void
    {
        if ($user->role !== 'user') {
            return;
        }

        $settings = $this->settings->resolved();
        $chatId = trim((string) ($settings['admin_user_id'] ?? ''));

        if (
            ! ($settings['enabled'] ?? false)
            || blank($settings['bot_token'] ?? null)
            || $chatId === ''
        ) {
            return;
        }

        $sourceLabel = match ($source) {
            'google' => 'Google',
            'mobile-email' => 'اپ موبایل • ایمیل',
            'mobile-phone' => 'اپ موبایل • شماره موبایل',
            'web-email' => 'وب • ایمیل',
            'web-phone' => 'وب • شماره موبایل',
            default => 'PlayNexus',
        };

        $contact = filled($user->email)
            ? (string) $user->email
            : (filled($user->phone) ? (string) $user->phone : '—');

        $text = "👤 <b>عضو جدید PlayNexus</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."نام: <b>".$this->escape((string) ($user->name ?: 'بدون نام'))."</b>\n"
            ."راه ارتباطی: <code>".$this->escape($contact)."</code>\n"
            ."روش عضویت: <b>".$this->escape($sourceLabel)."</b>\n"
            ."شناسه کاربر: <b>#".(int) $user->id."</b>\n"
            ."زمان: <b>".now()->format('Y-m-d H:i')."</b>";

        try {
            $this->telegram->sendMessage($chatId, $text, [
                'inline_keyboard' => [[[
                    'text' => '👥 مشاهده کاربران',
                    'url' => route('admin.users.index'),
                ]]],
            ]);
        } catch (Throwable $exception) {
            report($exception);
            // Registration must never fail because Telegram is unavailable.
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
