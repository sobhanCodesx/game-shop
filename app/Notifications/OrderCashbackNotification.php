<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Services\MediaStorage;
use App\Services\Sms\SmsPattern;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderCashbackNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class, ExpoPushChannel::class, TelegramChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'اعتبار خرید به کیف پول اضافه شد',
            'message' => 'اعتبار سفارش '.$this->order->number.' به کیف پول اضافه شد.',
            'url' => route('orders.show', $this->order, false),
            'order_id' => $this->order->id,
            'amount' => $this->order->cashback_amount,
            'image_url' => $this->productImage(),
        ];
    }

    public function toSms(object $notifiable): array
    {
        return [
            'pattern' => SmsPattern::OrderCashback,
            'variables' => ['order' => $this->order->number, 'products' => $this->productSummary(), 'amount' => (string) (int) $this->order->cashback_amount],
            'idempotency_key' => 'order-cashback:'.$this->order->id,
        ];
    }

    private function productImage(): ?string
    {
        $item = $this->order->items()->with('product.coverMedia')->first();
        $path = $item?->product?->coverMedia?->path;

        return filled($path) ? MediaStorage::url((string) $path) : null;
    }

    private function productSummary(): string
    {
        $items = $this->order->relationLoaded('items')
            ? $this->order->items
            : $this->order->items()->get(['title', 'quantity']);

        return $items->take(3)->map(fn ($item) => $item->title.' × '.number_format((int) $item->quantity))->implode('، ');
    }
}
