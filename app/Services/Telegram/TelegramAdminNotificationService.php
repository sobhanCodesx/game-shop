<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\Ticket;
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

        $this->send($text, [
            'inline_keyboard' => [[[
                'text' => '👥 مشاهده کاربران',
                'url' => route('admin.users.index'),
            ]]],
        ]);
    }

    public function newOrder(Order $order): void
    {
        $order->loadMissing(['user:id,name,email,phone', 'items:id,order_id,title,quantity']);
        $customer = $order->user;
        $items = $order->items
            ->take(4)
            ->map(fn ($item) => $this->escape($item->title).' × '.(int) $item->quantity)
            ->implode("\n• ");
        if ($order->items->count() > 4) {
            $items .= "\n• … +".($order->items->count() - 4).' مورد دیگر';
        }

        $text = "🛒 <b>سفارش جدید نیازمند بررسی</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."شماره: <code>".$this->escape((string) $order->number)."</code>\n"
            ."مشتری: <b>".$this->escape((string) ($customer?->name ?: 'بدون نام'))."</b>\n"
            ."مبلغ: <b>".number_format((int) $order->grand_total)." تومان</b>\n"
            ."پرداخت از کیف پول: <b>".number_format((int) $order->wallet_used)." تومان</b>\n"
            ."مبلغ قابل پرداخت: <b>".number_format((int) $order->payable_amount)." تومان</b>\n"
            ."روش تحویل: <b>".($order->delivery_method === 'pickup' ? 'حضوری' : 'پیک')."</b>\n"
            ."وضعیت: <b>".$this->escape((string) $order->status)."</b>\n"
            .($items !== '' ? "\n📦 <b>اقلام</b>\n• ".$items."\n" : '')
            ."\n⏱ منتظر اقدام مدیر";

        $this->send($text, [
            'inline_keyboard' => [
                [[
                    'text' => '🧾 باز کردن سفارش',
                    'url' => route('admin.orders.show', $order),
                ]],
                [[
                    'text' => '📦 همه سفارش‌ها',
                    'url' => route('admin.orders.index'),
                ]],
            ],
        ]);
    }

    public function orderCancelled(Order $order): void
    {
        $order->loadMissing('user:id,name');

        $text = "🚫 <b>لغو سفارش توسط مشتری</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."شماره: <code>".$this->escape((string) $order->number)."</code>\n"
            ."مشتری: <b>".$this->escape((string) ($order->user?->name ?: 'بدون نام'))."</b>\n"
            ."مبلغ سفارش: <b>".number_format((int) $order->grand_total)." تومان</b>\n"
            ."وضعیت فعلی: <b>".$this->escape((string) $order->status)."</b>";

        $this->send($text, [
            'inline_keyboard' => [[[
                'text' => '🧾 مشاهده سفارش',
                'url' => route('admin.orders.show', $order),
            ]]],
        ]);
    }

    public function newTicket(Ticket $ticket, string $message): void
    {
        $ticket->loadMissing('user:id,name,email,phone');
        $type = $ticket->type === 'exchange' ? 'معاوضه' : 'پشتیبانی';

        $text = "🎫 <b>تیکت جدید نیازمند پاسخ</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."شماره: <code>".$this->escape((string) $ticket->number)."</code>\n"
            ."نوع: <b>{$type}</b>\n"
            ."کاربر: <b>".$this->escape((string) ($ticket->user?->name ?: 'بدون نام'))."</b>\n"
            ."موضوع: <b>".$this->escape((string) $ticket->subject)."</b>\n"
            ."پیام: ".$this->escape($this->excerpt($message))."\n"
            ."وضعیت: <b>".$this->escape((string) $ticket->status)."</b>\n\n"
            ."⏱ منتظر اقدام پشتیبانی";

        $this->send($text, [
            'inline_keyboard' => [[[
                'text' => '💬 باز کردن تیکت',
                'url' => route('admin.tickets.show', $ticket),
            ]]],
        ]);
    }

    public function ticketReply(Ticket $ticket, string $message): void
    {
        $ticket->loadMissing('user:id,name');

        $text = "💬 <b>پاسخ جدید مشتری به تیکت</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."تیکت: <code>".$this->escape((string) $ticket->number)."</code>\n"
            ."کاربر: <b>".$this->escape((string) ($ticket->user?->name ?: 'بدون نام'))."</b>\n"
            ."موضوع: <b>".$this->escape((string) $ticket->subject)."</b>\n"
            ."پیام: ".$this->escape($this->excerpt($message))."\n\n"
            ."⏱ منتظر پاسخ پشتیبانی";

        $this->send($text, [
            'inline_keyboard' => [[[
                'text' => '↩️ پاسخ به تیکت',
                'url' => route('admin.tickets.show', $ticket),
            ]]],
        ]);
    }

    public function exchangeDecision(Ticket $ticket, string $decision): void
    {
        $ticket->loadMissing('user:id,name');
        $accepted = $decision === 'accepted';

        $text = ($accepted ? "✅" : "❌")." <b>پاسخ مشتری به پیشنهاد معاوضه</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."تیکت: <code>".$this->escape((string) $ticket->number)."</code>\n"
            ."کاربر: <b>".$this->escape((string) ($ticket->user?->name ?: 'بدون نام'))."</b>\n"
            ."تصمیم: <b>".($accepted ? 'پذیرفت' : 'رد کرد')."</b>\n"
            ."مبلغ پیشنهاد: <b>".number_format((int) $ticket->exchange_offer_amount)." تومان</b>\n"
            ."وضعیت: <b>".$this->escape((string) $ticket->exchange_status)."</b>";

        $this->send($text, [
            'inline_keyboard' => [[[
                'text' => '🔄 مدیریت معاوضه',
                'url' => route('admin.tickets.show', $ticket),
            ]]],
        ]);
    }

    private function send(string $text, array $replyMarkup = []): void
    {
        $settings = $this->settings->resolved();
        $chatId = trim((string) ($settings['admin_user_id'] ?? ''));

        if (
            ! ($settings['enabled'] ?? false)
            || blank($settings['bot_token'] ?? null)
            || $chatId === ''
        ) {
            return;
        }

        try {
            $this->telegram->sendMessage($chatId, $text, $replyMarkup);
        } catch (Throwable $exception) {
            report($exception);
            // Admin alerts are best-effort and must never break user flows.
        }
    }

    private function excerpt(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';

        return mb_strlen($value) > 220
            ? mb_substr($value, 0, 217).'…'
            : $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
