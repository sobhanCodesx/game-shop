<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketService
{
    public function create(User $customer, User $creator, ?OrderItem $item, ?string $subject, string $message): Ticket
    {
        $ticket = DB::transaction(function () use ($customer, $creator, $item, $subject, $message) {
            $ticket = Ticket::create(['number' => 'TK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)), 'user_id' => $customer->id, 'order_id' => $item?->order_id, 'order_item_id' => $item?->id, 'product_id' => $item?->product_id, 'subject' => $item ? 'پشتیبانی محصول: '.$item->title : trim((string) $subject), 'status' => $creator->is_admin ? 'open' : 'pending', 'priority' => 'normal', 'last_replied_at' => now(), 'created_by' => $creator->id]);
            $ticket->replies()->create(['user_id' => $creator->id, 'message' => $message, 'is_admin' => (bool) $creator->is_admin]);

            return $ticket;
        });

        if ($creator->is_admin) {
            $customer->notify(new TicketActivityNotification($ticket, 'تیکت جدید برای شما ثبت شد', 'پشتیبانی تیکت '.$ticket->number.' را ایجاد کرد.'));
        } else {
            User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new TicketActivityNotification($ticket, 'تیکت پشتیبانی جدید', $customer->name.' تیکت '.$ticket->number.' را ثبت کرد.', true)));
        }

        return $ticket;
    }

    public function reply(Ticket $ticket, User $actor, string $message): void
    {
        DB::transaction(function () use ($ticket, $actor, $message) {
            $ticket->replies()->create(['user_id' => $actor->id, 'message' => $message, 'is_admin' => (bool) $actor->is_admin]);
            $ticket->update(['status' => $actor->is_admin ? 'open' : $ticket->status, 'last_replied_at' => now()]);
        });
        if ($actor->is_admin) {
            $ticket->user->notify(new TicketActivityNotification($ticket, 'پاسخ جدید پشتیبانی', 'به تیکت '.$ticket->number.' پاسخ داده شد.'));
        } else {
            User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new TicketActivityNotification($ticket, 'پاسخ جدید مشتری', 'مشتری به تیکت '.$ticket->number.' پاسخ داد.', true)));
        }
    }
}
