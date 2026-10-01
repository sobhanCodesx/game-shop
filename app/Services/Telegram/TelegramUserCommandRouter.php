<?php

namespace App\Services\Telegram;

use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TelegramUserCommandRouter
{
    public function __construct(
        private readonly TelegramApiClient $telegram,
        private readonly TelegramUserLinkService $links,
        private readonly TelegramBotSessionStore $sessions,
        private readonly TicketService $tickets,
    ) {}

    public function handle(array $update): array
    {
        $callback = is_array($update['callback_query'] ?? null)
            ? $update['callback_query']
            : [];
        $message = $callback !== []
            ? (is_array($callback['message'] ?? null) ? $callback['message'] : [])
            : (is_array($update['message'] ?? null) ? $update['message'] : []);
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = $callback !== []
            ? (is_array($callback['from'] ?? null) ? $callback['from'] : [])
            : (is_array($message['from'] ?? null) ? $message['from'] : []);

        $chatId = isset($chat['id']) ? (string) $chat['id'] : '';
        $userId = isset($from['id']) ? (string) $from['id'] : '';
        $chatType = isset($chat['type']) ? (string) $chat['type'] : '';

        if ($chatId === '' || $userId === '' || $chatType !== 'private') {
            return ['action' => 'user_ignored'];
        }

        $text = trim((string) ($message['text'] ?? ''));
        [$command, $payload] = $this->split($text);

        try {
            if ($callback !== []) {
                return $this->handleCallback($callback, $userId, $chatId);
            }
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

            if ($command === '/cancel' && $user) {
                $this->sessions->clear($userId, $chatId);
                $this->telegram->sendMessage(
                    $chatId,
                    'لغو شد. برای پاسخ دوباره از دکمه همان تیکت استفاده کن.',
                );

                return ['action' => 'seller_ticket_reply_cancelled'];
            }

            $session = $user ? $this->sessions->get($userId, $chatId) : null;
            if (
                $user
                && $session?->state === 'seller_ticket_reply'
                && $text !== ''
                && ! str_starts_with($command, '/')
            ) {
                $context = is_array($session->context) ? $session->context : [];
                $ticket = Ticket::query()->find((int) ($context['ticket_id'] ?? 0));
                if (! $ticket) {
                    $this->sessions->clear($userId, $chatId);
                    throw new RuntimeException('تیکت پیدا نشد یا دیگر در دسترس نیست.');
                }

                $this->tickets->replyAsDigitalSeller($ticket, $user, $text);
                $this->sessions->clear($userId, $chatId);

                $this->telegram->sendMessage(
                    $chatId,
                    "✅ <b>پاسخ داخل تیکت ثبت شد</b>\n"
                    ."مشتری همین حالا از داخل PlayNexus پاسخ شما را می‌بیند.",
                    [
                        'inline_keyboard' => [[[
                            'text' => '💬 ارسال پاسخ دیگر',
                            'callback_data' => 'seller_ticket_reply:'.$ticket->id,
                        ]]],
                    ],
                );

                return [
                    'action' => 'seller_ticket_replied',
                    'resource' => 'ticket',
                    'resource_id' => $ticket->id,
                ];
            }

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

    private function handleCallback(
        array $callback,
        string $userId,
        string $chatId,
    ): array {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = trim((string) ($callback['data'] ?? ''));

        if (! preg_match('/^seller_ticket_reply:(\d+)$/', $data, $matches)) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, 'این دکمه معتبر نیست.');
            }

            return ['action' => 'user_callback_ignored'];
        }

        $user = $this->links->byTelegramUserId($userId);
        $ticket = Ticket::query()
            ->with('digitalProduct:id,title,slug')
            ->find((int) $matches[1]);

        if (
            ! $user
            || ! $ticket
            || $ticket->type !== 'digital_price'
            || (int) $ticket->assigned_user_id !== (int) $user->id
        ) {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery(
                    $callbackId,
                    'این تیکت به حساب فروشندگی شما اختصاص ندارد.',
                );
            }

            return ['action' => 'seller_ticket_reply_denied'];
        }

        if ($ticket->status === 'closed') {
            if ($callbackId !== '') {
                $this->telegram->answerCallbackQuery($callbackId, 'این تیکت بسته شده است.');
            }

            return ['action' => 'seller_ticket_reply_closed'];
        }

        $this->sessions->put(
            $userId,
            $chatId,
            'seller_ticket_reply',
            ['ticket_id' => $ticket->id],
            1800,
        );

        if ($callbackId !== '') {
            $this->telegram->answerCallbackQuery($callbackId, 'پاسخت را همین‌جا بفرست.');
        }

        $this->telegram->sendMessage(
            $chatId,
            "✍️ <b>پاسخ به استعلام قیمت</b>\n"
            ."محصول: <b>".$this->escape((string) ($ticket->digitalProduct?->title ?: $ticket->subject))."</b>\n"
            ."تیکت: <code>".$this->escape((string) $ticket->number)."</code>\n\n"
            ."قیمت یا پیام خودت را در یک پیام متنی بفرست. همان متن مستقیماً داخل تیکت مشتری ثبت می‌شود.\n"
            ."برای لغو: <code>/cancel</code>",
        );

        return [
            'action' => 'seller_ticket_reply_started',
            'resource' => 'ticket',
            'resource_id' => $ticket->id,
        ];
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
