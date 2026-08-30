<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order, private readonly string $title, private readonly string $message, private readonly bool $adminTarget = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->adminTarget ? route('admin.orders.show', $this->order) : route('orders.show', $this->order), 'order_id' => $this->order->id];
    }
}
