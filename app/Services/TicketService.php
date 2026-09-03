<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function create(User $customer, User $creator, ?OrderItem $item, ?string $subject, string $message, ?Product $product = null, string $type = 'support', array $attachments = [], ?string $tradeItemTitle = null): Ticket
    {
        $ticket = DB::transaction(function () use ($customer, $creator, $item, $subject, $message, $product, $type, $attachments, $tradeItemTitle) {
            $isExchange = $type === 'exchange';
            $ticket = Ticket::create(['number' => 'TK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)), 'user_id' => $customer->id, 'order_id' => $item?->order_id, 'order_item_id' => $item?->id, 'product_id' => $product?->id ?? $item?->product_id, 'target_product_id' => $isExchange ? $product?->id : null, 'subject' => $isExchange ? 'درخواست معاوضه: '.$product?->title : ($item ? 'پشتیبانی محصول: '.$item->title : trim((string) $subject)), 'type' => $type, 'status' => $creator->is_admin ? 'open' : 'pending', 'exchange_status' => $isExchange ? 'pending_review' : null, 'priority' => 'normal', 'last_replied_at' => now(), 'created_by' => $creator->id]);
            $reply = $ticket->replies()->create(['user_id' => $creator->id, 'message' => $message, 'is_admin' => (bool) $creator->is_admin]);
            $this->storeAttachments($reply, $creator, $attachments);
            if ($isExchange) {
                $ticket->update([
                    'target_product_id' => null,
                    'trade_item_title' => trim((string) $tradeItemTitle),
                    'trade_item_description' => $message,
                    'trade_item_images' => $reply->attachments()->pluck('path')->all(),
                ]);
            }

            return $ticket;
        });

        if ($creator->is_admin) {
            $customer->notify(new TicketActivityNotification($ticket, 'تیکت جدید برای شما ثبت شد', 'پشتیبانی تیکت '.$ticket->number.' را ایجاد کرد.'));
        } else {
            User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new TicketActivityNotification($ticket, 'تیکت پشتیبانی جدید', $customer->name.' تیکت '.$ticket->number.' را ثبت کرد.', true)));
        }

        return $ticket;
    }

    public function reply(Ticket $ticket, User $actor, string $message, array $attachments = []): void
    {
        DB::transaction(function () use ($ticket, $actor, $message, $attachments) {
            $reply = $ticket->replies()->create(['user_id' => $actor->id, 'message' => $message, 'is_admin' => (bool) $actor->is_admin]);
            $this->storeAttachments($reply, $actor, $attachments);
            $ticket->update(['status' => $actor->is_admin ? 'open' : $ticket->status, 'last_replied_at' => now()]);
        });
        if ($actor->is_admin) {
            $ticket->user->notify(new TicketActivityNotification($ticket, 'پاسخ جدید پشتیبانی', 'به تیکت '.$ticket->number.' پاسخ داده شد.'));
        } else {
            User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new TicketActivityNotification($ticket, 'پاسخ جدید مشتری', 'مشتری به تیکت '.$ticket->number.' پاسخ داد.', true)));
        }
    }

    private function storeAttachments(TicketReply $reply, User $actor, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $mime = (string) $file->getMimeType();
            $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
            $path = 'tickets/'.$reply->ticket_id.'/'.$reply->id.'/'.Str::uuid().'.'.$extension;
            MediaStorage::disk()->put($path, fopen($file->getRealPath(), 'rb'));
            $reply->attachments()->create(['user_id' => $actor->id, 'path' => $path, 'original_name' => $file->getClientOriginalName(), 'mime_type' => $mime, 'type' => str_starts_with($mime, 'video/') ? 'video' : 'image', 'size' => $file->getSize()]);
        }
    }

    public function purgeAttachments(Ticket $ticket): int
    {
        return DB::transaction(function () use ($ticket) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status !== 'closed') {
                throw ValidationException::withMessages(['attachments' => 'فایل‌ها فقط پس از بسته‌شدن تیکت قابل حذف هستند.']);
            }

            $attachments = TicketAttachment::query()
                ->whereHas('reply', fn ($query) => $query->where('ticket_id', $locked->id))
                ->lockForUpdate()
                ->get();

            if ($attachments->isEmpty()) {
                return 0;
            }

            MediaStorage::disk()->delete($attachments->pluck('path')->all());
            TicketAttachment::query()->whereKey($attachments->modelKeys())->delete();

            return $attachments->count();
        }, 3);
    }
}
