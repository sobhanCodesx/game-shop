<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order, private readonly string $title, private readonly string $message, private readonly bool $adminTarget = false) {}

    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->adminTarget ? route('admin.orders.show', $this->order, false) : route('orders.show', $this->order, false), 'order_id' => $this->order->id];
    }

    public function toSms(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => implode("\n", [
                'شماره سفارش: '.$this->order->number,
                'محصولات: '.$this->productSummary(),
                'مبلغ نهایی: '.number_format((int) $this->order->grand_total).' تومان',
                $this->message,
            ]),
        ];
    }

    private function productSummary(): string
    {
        $items = $this->order->relationLoaded('items')
            ? $this->order->items
            : $this->order->items()->get(['title', 'quantity']);
        $visible = $items->take(3)->map(fn ($item) => $item->title.' × '.number_format((int) $item->quantity))->implode('، ');
        $remaining = $items->count() - 3;

        return $visible.($remaining > 0 ? ' و '.number_format($remaining).' محصول دیگر' : '');
    }
}
