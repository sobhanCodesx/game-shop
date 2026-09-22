<?php

namespace App\Services\Telegram;

use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TelegramUserCommandRouter
{
    public function __construct(
        private readonly TelegramApiClient $telegram,
        private readonly TelegramUserLinkService $links,
    ) {}

    public function handle(array $update): array
    {
        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        $chatId = isset($chat['id']) ? (string) $chat['id'] : '';
        $userId = isset($from['id']) ? (string) $from['id'] : '';
        $chatType = isset($chat['type']) ? (string) $chat['type'] : '';

        if ($chatId === '' || $userId === '' || $chatType !== 'private') {
            return ['action' => 'user_ignored'];
        }

        $text = trim((string) ($message['text'] ?? ''));
        [$command, $payload] = $this->split($text);

        try {
            if ($command === '/start' && str_starts_with($payload, 'verifyphone_')) {
                $user = $this->links->startPhoneVerification(
                    substr($payload, 12),
                    $userId,
                    $chatId,
                );

                $this->telegram->sendMessage(
                    $chatId,
                    "📱 <b>تأیید امن شماره برای PlayNexus</b>\n"
                    ."برای اینکه هیچ‌کس نتواند شماره شخص دیگری را دور بزند، فقط دکمه زیر را بزن و <b>شماره خودت</b> را با قابلیت رسمی Telegram به اشتراک بگذار.\n\n"
                    ."شماره ثبت‌شده در PlayNexus: <code>".$this->escape((string) $user->phone)."</code>",
                    [
                        'keyboard' => [[[
                            'text' => '📱 اشتراک شماره خودم',
                            'request_contact' => true,
                        ]]],
                        'resize_keyboard' => true,
                        'one_time_keyboard' => true,
                        'input_field_placeholder' => 'فقط دکمه اشتراک شماره خودم را بزن',
                    ],
                );

                return [
                    'action' => 'phone_contact_requested',
                    'resource' => 'user',
                    'resource_id' => $user->id,
                ];
            }

            if (is_array($message['contact'] ?? null)) {
                $user = $this->links->completePhoneVerification(
                    $userId,
                    $chatId,
                    $message['contact'],
                );

                $this->telegram->sendMessage(
                    $chatId,
                    "✅ <b>شماره موبایل با موفقیت تأیید شد</b>\n"
                    ."شماره‌ای که Telegram به‌صورت رسمی از حساب خودت فرستاد دقیقاً با شماره PlayNexus یکی بود.\n\n"
                    ."برای این مسیر <b>دیگر هیچ کد ۶ رقمی لازم نیست</b>. به صفحه PlayNexus برگرد؛ ثبت‌نام یا ادامه خرید به‌صورت خودکار ادامه پیدا می‌کند.",
                    ['remove_keyboard' => true],
                );
                $this->telegram->sendMessage(
                    $chatId,
                    "حالا به PlayNexus برگرد 👇",
                    [
                        'inline_keyboard' => [
                            [[
                                'text' => '✅ بازگشت و تکمیل ثبت‌نام',
                                'url' => route('verification.notice'),
                            ]],
                            [[
                                'text' => '👤 بازگشت به حساب PlayNexus',
                                'url' => route('account.dashboard', ['tab' => 'profile']),
                            ]],
                        ],
                    ],
                );

                return [
                    'action' => 'phone_contact_verified',
                    'resource' => 'user',
                    'resource_id' => $user->id,
                ];
            }

            if ($command === '/start' && str_starts_with($payload, 'connect_')) {
                $user = $this->links->consume(substr($payload, 8), $userId, $chatId);

                try {
                    // Also refresh Telegram command scopes so regular users only
                    // see the user-safe command menu while the owner keeps the
                    // private admin command scope. Linking must still succeed
                    // if Telegram temporarily rejects this maintenance call.
                    $this->telegram->registerWebhook();
                } catch (Throwable $exception) {
                    report($exception);
                }

                if ($this->links->prepareLinkedPhoneVerification($user, $userId, $chatId)) {
                    $this->sendPhoneVerificationPrompt($chatId, $user);

                    return [
                        'action' => 'user_linked_phone_contact_requested',
                        'resource' => 'user',
                        'resource_id' => $user->id,
                    ];
                }

                $this->telegram->sendMessage(
                    $chatId,
                    "✅ <b>تلگرام با موفقیت وصل شد</b>\n"
                    ."حساب <b>".$this->escape((string) $user->name)."</b> از این به بعد می‌تواند اعلان‌های PlayNexus و کد ورود درخواستی را در همین چت دریافت کند.\n\n"
                    ."🔐 منو و دسترسی ادمین برای کاربران عادی کاملاً جداست.",
                    [
                        'inline_keyboard' => [[[
                            'text' => '↩️ بازگشت به حساب PlayNexus',
                            'url' => route('account.dashboard', [
                                'tab' => 'content-notifications',
                            ]),
                        ]]],
                    ],
                );

                return ['action' => 'user_linked', 'resource' => 'user', 'resource_id' => $user->id];
            }

            $user = $this->links->byTelegramUserId($userId);

            if (
                $command === '/start'
                && $user
                && $this->links->prepareLinkedPhoneVerification($user, $userId, $chatId)
            ) {
                $this->sendPhoneVerificationPrompt($chatId, $user);

                return [
                    'action' => 'phone_contact_requested_for_linked_user',
                    'resource' => 'user',
                    'resource_id' => $user->id,
                ];
            }

            if ($command === '/unlink' && $user) {
                $this->links->disconnect($user);
                $this->telegram->sendMessage(
                    $chatId,
                    "🔌 اتصال تلگرام از حساب PlayNexus قطع شد.\nبرای اتصال دوباره از داشبورد حساب کاربری اقدام کن.",
                );

                return ['action' => 'user_unlinked', 'resource' => 'user', 'resource_id' => $user->id];
            }

            if ($user) {
                $this->telegram->sendMessage(
                    $chatId,
                    "🎮 <b>PlayNexus</b>\n"
                    ."این چت برای اعلان‌های شخصی حساب <b>".$this->escape((string) $user->name)."</b> آماده است.\n\n"
                    ."• وضعیت سفارش و پرداخت\n"
                    ."• پیام‌های پشتیبانی\n"
                    ."• محتوای دنبال‌شده\n"
                    ."• کد ورود، فقط وقتی خودت درخواست بدهی\n\n"
                    ."برای قطع اتصال: <code>/unlink</code>",
                );

                return ['action' => 'user_home', 'resource' => 'user', 'resource_id' => $user->id];
            }

            $this->telegram->sendMessage(
                $chatId,
                "👋 <b>به PlayNexus خوش اومدی</b>\n"
                ."برای دریافت اعلان‌های شخصی، اول از داخل داشبورد حساب PlayNexus گزینه «اتصال تلگرام» را بزن.\n\n"
                ."این بخش هیچ دسترسی مدیریتی ندارد.",
            );

            return ['action' => 'user_unlinked_help'];
        } catch (RuntimeException $exception) {
            $this->telegram->sendMessage(
                $chatId,
                "⚠️ ".$this->escape(Str::limit($exception->getMessage(), 300)),
            );

            return ['action' => 'user_error'];
        }
    }

    private function sendPhoneVerificationPrompt(string $chatId, object $user): void
    {
        $this->telegram->sendMessage(
            $chatId,
            "✅ <b>تلگرام به حساب PlayNexus وصل است</b>\n"
            ."برای فعال شدن ورود با کد Telegram فقط یک مرحله مانده: شماره موبایل حسابت را با دکمه رسمی زیر تأیید کن.\n\n"
            ."شماره PlayNexus: <code>".$this->escape((string) $user->phone)."</code>\n"
            ."Telegram فقط شماره متعلق به خود همین حساب را قبول می‌کند.",
            [
                'keyboard' => [[[
                    'text' => '📱 اشتراک شماره خودم',
                    'request_contact' => true,
                ]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
                'input_field_placeholder' => 'دکمه اشتراک شماره خودم را بزن',
            ],
        );
    }

    private function split(string $text): array
    {
        if ($text === '') {
            return ['', ''];
        }

        [$command, $rest] = array_pad(preg_split('/\s+/', $text, 2) ?: [], 2, '');

        if (str_contains($command, '@')) {
            $command = Str::before($command, '@');
        }

        return [mb_strtolower($command), trim($rest)];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
