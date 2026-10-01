<?php

namespace App\Services\Telegram;

use App\Models\Ticket;
use App\Models\User;
use Throwable;

final class TelegramSellerTicketNotificationService
{
    public function __construct(
        private readonly TelegramBotSettings $settings,
        private readonly TelegramApiClient $telegram,
    ) {}

    public function priceInquiry(Ticket $ticket, string $message): bool
    {
        $ticket->loadMissing([
            'user:id,name',
            'assignee:id,name,status,telegram_user_id,telegram_chat_id,telegram_linked_at',
            'digitalProduct:id,title,slug',
        ]);

        $seller = $ticket->assignee;
        if (! $this->canMessage($seller)) {
            return false;
        }

        $text = "💰 <b>استعلام قیمت جدید</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."تیکت: <code>".$this->escape((string) $ticket->number)."</code>\n"
            ."محصول: <b>".$this->escape((string) ($ticket->digitalProduct?->title ?: $ticket->subject))."</b>\n"
            ."کاربر: <b>".$this->escape((string) ($ticket->user?->name ?: 'بدون نام'))."</b>\n"
            ."درخواست: ".$this->escape($this->excerpt($message))."\n\n"
            ."برای اعلام آخرین قیمت، دکمه پاسخ مستقیم را بزن و پیام را همین‌جا ارسال کن.";

        return $this->send($seller, $ticket, $text);
    }

    public function customerReply(Ticket $ticket, string $message): bool
    {
        $ticket->loadMissing([
            'user:id,name',
            'assignee:id,name,status,telegram_user_id,telegram_chat_id,telegram_linked_at',
            'digitalProduct:id,title,slug',
        ]);

        $seller = $ticket->assignee;
        if (! $this->canMessage($seller)) {
            return false;
        }

        $text = "💬 <b>پیام جدید مشتری در استعلام قیمت</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."تیکت: <code>".$this->escape((string) $ticket->number)."</code>\n"
            ."محصول: <b>".$this->escape((string) ($ticket->digitalProduct?->title ?: $ticket->subject))."</b>\n"
            ."کاربر: <b>".$this->escape((string) ($ticket->user?->name ?: 'بدون نام'))."</b>\n"
            ."پیام: ".$this->escape($this->excerpt($message))."\n\n"
            ."برای ادامه گفت‌وگو، پاسخ مستقیم را بزن.";

        return $this->send($seller, $ticket, $text);
    }

    private function canMessage(?User $seller): bool
    {
        if (! $seller
            || $seller->status !== 'active'
            || blank($seller->telegram_user_id)
            || blank($seller->telegram_chat_id)
            || ! $seller->telegram_linked_at
        ) {
            return false;
        }

        $settings = $this->settings->resolved();

        return (bool) ($settings['enabled'] ?? false)
            && filled($settings['bot_token'] ?? null);
    }

    private function send(User $seller, Ticket $ticket, string $text): bool
    {
        try {
            $buttons = [[[
                'text' => '💬 پاسخ مستقیم در تیکت',
                'callback_data' => 'seller_ticket_reply:'.$ticket->id,
            ]]];

            if ($ticket->digitalProduct) {
                $buttons[] = [[
                    'text' => '🎮 مشاهده محصول',
                    'url' => route('digital.show', $ticket->digitalProduct),
                ]];
            }

            $this->telegram->sendMessage(
                (string) $seller->telegram_chat_id,
                $text,
                ['inline_keyboard' => $buttons],
            );

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function excerpt(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';

        return mb_strlen($value) > 260
            ? mb_substr($value, 0, 257).'…'
            : $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
