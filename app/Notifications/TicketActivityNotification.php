<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TicketActivityNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Ticket $ticket, private readonly string $title, private readonly string $message, private readonly bool $adminTarget = false) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'url' => $this->adminTarget ? route('admin.tickets.show', $this->ticket) : route('account.tickets.show', $this->ticket), 'ticket_id' => $this->ticket->id];
    }
}
