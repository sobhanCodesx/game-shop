<?php

namespace App\Services;

use App\Models\DigitalProduct;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketReply;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use App\Services\Telegram\TelegramAdminNotificationService;
use App\Services\Telegram\TelegramSellerTicketNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TicketService
{
    public function __construct(
        private readonly TelegramAdminNotificationService $telegramAdmin,
        private readonly TelegramSellerTicketNotificationService $telegramSeller,
    ) {}

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
            $this->telegramAdmin->newTicket($ticket, $message);
        }

        return $ticket;
    }

    public function createDigitalPriceInquiry(User $customer, DigitalProduct $product): Ticket
    {
        $message = 'سلام، برای «'.$product->title.'» لطفاً آخرین قیمت و موجودی ظرفیت‌ها را اعلام کنید.';

        $ticket = DB::transaction(function () use ($customer, $product, $message): Ticket {
            $ticket = Ticket::query()->create([
                'number' => 'TK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $customer->id,
                'digital_product_id' => $product->id,
                'assigned_user_id' => $product->seller_id,
                'subject' => 'استعلام آخرین قیمت: '.$product->title,
                'type' => 'digital_price',
                'status' => 'pending',
                'priority' => 'normal',
                'last_replied_at' => now(),
                'created_by' => $customer->id,
            ]);

            $ticket->replies()->create([
                'user_id' => $customer->id,
                'message' => $message,
                'is_admin' => false,
            ]);

            return $ticket;
        });

        if (! $this->telegramSeller->priceInquiry($ticket, $message)) {
            User::query()
                ->where('is_admin', true)
                ->each(fn (User $admin) => $admin->notify(new TicketActivityNotification(
                    $ticket,
                    'استعلام قیمت دیجیتال جدید',
                    $customer->name.' برای '.$product->title.' استعلام قیمت ثبت کرد.',
                    true,
                )));
            $this->telegramAdmin->newTicket($ticket, $message);
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

            return;
        }

        if ($ticket->type === 'digital_price' && (int) $ticket->user_id === (int) $actor->id) {
            if (! $this->telegramSeller->customerReply($ticket, $message)) {
                User::query()
                    ->where('is_admin', true)
                    ->each(fn (User $admin) => $admin->notify(new TicketActivityNotification(
                        $ticket,
                        'پاسخ جدید مشتری',
                        'مشتری به استعلام قیمت '.$ticket->number.' پاسخ داد.',
                        true,
                    )));
                $this->telegramAdmin->ticketReply($ticket, $message);
            }

            return;
        }

        User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new TicketActivityNotification($ticket, 'پاسخ جدید مشتری', 'مشتری به تیکت '.$ticket->number.' پاسخ داد.', true)));
        $this->telegramAdmin->ticketReply($ticket, $message);
    }

    public function replyAsTelegramAdmin(
        Ticket $ticket,
        User $admin,
        string $message,
    ): void {
        $message = trim($message);

        if (! $admin->is_admin || $admin->status !== 'active') {
            throw new RuntimeException('حساب مدیریتی فعال برای ثبت پاسخ پیدا نشد.');
        }

        if ($message === '' || mb_strlen($message) > 5000) {
            throw new RuntimeException('متن پاسخ باید بین ۱ تا ۵۰۰۰ کاراکتر باشد.');
        }

        DB::transaction(function () use ($ticket, $admin, $message): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if ($locked->status === 'closed') {
                throw new RuntimeException('این تیکت بسته شده است.');
            }

            $locked->replies()->create([
                'user_id' => $admin->id,
                'message' => $message,
                'is_admin' => true,
            ]);

            $locked->update([
                'status' => 'open',
                'last_replied_at' => now(),
            ]);
        }, 3);

        try {
            $ticket->refresh()->loadMissing('user');
            $ticket->user?->notify(new TicketActivityNotification(
                $ticket,
                'پاسخ جدید پشتیبانی',
                'به تیکت '.$ticket->number.' از طریق پشتیبانی PlayNexus پاسخ داده شد.',
            ));
        } catch (Throwable $exception) {
            report($exception);
            // The reply is already committed. Notification delivery must not
            // make Telegram retry the same message and create a duplicate reply.
        }
    }

    public function replyAsDigitalSeller(Ticket $ticket, User $seller, string $message): void
    {
        $message = trim($message);
        if ($message === '' || mb_strlen($message) > 5000) {
            throw new RuntimeException('متن پاسخ باید بین ۱ تا ۵۰۰۰ کاراکتر باشد.');
        }

        DB::transaction(function () use ($ticket, $seller, $message): void {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);

            if (
                $locked->type !== 'digital_price'
                || (int) $locked->assigned_user_id !== (int) $seller->id
            ) {
                throw new RuntimeException('این تیکت به حساب فروشندگی شما اختصاص ندارد.');
            }

            if ($locked->status === 'closed') {
                throw new RuntimeException('این تیکت بسته شده است.');
            }

            $locked->replies()->create([
                'user_id' => $seller->id,
                'message' => $message,
                'is_admin' => true,
            ]);

            $locked->update([
                'status' => 'open',
                'last_replied_at' => now(),
            ]);
        }, 3);

        try {
            $ticket->refresh()->loadMissing('user');
            $ticket->user->notify(new TicketActivityNotification(
                $ticket,
                'پاسخ جدید فروشنده',
                'فروشنده به استعلام قیمت '.$ticket->number.' پاسخ داد.',
            ));
        } catch (Throwable $exception) {
            report($exception);
            // Do not let a notification transport failure make Telegram retry
            // an already committed seller reply.
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
