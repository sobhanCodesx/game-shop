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

class OrderActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order, private readonly string $title, private readonly string $message, private readonly bool $adminTarget = false) {}

    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class, ExpoPushChannel::class, TelegramChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->adminTarget
                ? route('admin.orders.show', $this->order, false)
                : route('orders.show', $this->order, false),
            'order_id' => $this->order->id,
            'image_url' => $this->productImage(),
        ];
    }

    public function toSms(object $notifiable): array
    {
        return [
            'pattern' => SmsPattern::OrderActivity,
            'variables' => ['title' => $this->title, 'order' => $this->order->number, 'products' => $this->productSummary(), 'amount' => (string) (int) $this->order->grand_total, 'message' => $this->message],
            'idempotency_key' => 'order-activity:'.$this->order->id.':'.sha1($this->title.'|'.$this->message),
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
        $visible = $items->take(3)->map(fn ($item) => $item->title.' × '.number_format((int) $item->quantity))->implode('، ');
        $remaining = $items->count() - 3;

        return $visible.($remaining > 0 ? ' و '.number_format($remaining).' محصول دیگر' : '');
    }
}
