<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Services\Sms\SmsPattern;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket, private readonly string $title, private readonly string $message, private readonly bool $adminTarget = false) {}

    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class, ExpoPushChannel::class, TelegramChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->adminTarget ? route('admin.tickets.show', $this->ticket, false) : route('account.tickets.show', $this->ticket, false), 'ticket_id' => $this->ticket->id];
    }

    public function toSms(object $notifiable): array
    {
        return ['pattern' => SmsPattern::TicketActivity, 'variables' => ['title' => $this->title, 'message' => $this->message], 'idempotency_key' => 'ticket-activity:'.$this->ticket->id.':'.sha1($this->title.'|'.$this->message)];
    }
}
