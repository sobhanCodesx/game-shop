<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCashbackNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'اعتبار خرید به کیف پول اضافه شد', 'message' => number_format($this->order->cashback_amount).' تومان بابت سفارش '.$this->order->number.' به کیف پول شما اضافه شد.', 'url' => route('orders.show', $this->order), 'order_id' => $this->order->id, 'amount' => $this->order->cashback_amount];
    }
}
