<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCashbackNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [...$this->toSms($notifiable), 'url' => route('orders.show', $this->order, false), 'order_id' => $this->order->id, 'amount' => $this->order->cashback_amount];
    }

    public function toSms(object $notifiable): array
    {
        return [
            'title' => 'اعتبار خرید به کیف پول اضافه شد',
            'message' => implode("\n", [
                'شماره سفارش: '.$this->order->number,
                'محصولات: '.$this->productSummary(),
                'مبلغ اعتبار: '.number_format((int) $this->order->cashback_amount).' تومان',
                'اعتبار به کیف پول شما اضافه شد.',
            ]),
        ];
    }

    private function productSummary(): string
    {
        $items = $this->order->relationLoaded('items')
            ? $this->order->items
            : $this->order->items()->get(['title', 'quantity']);

        return $items->take(3)->map(fn ($item) => $item->title.' × '.number_format((int) $item->quantity))->implode('، ');
    }
}
