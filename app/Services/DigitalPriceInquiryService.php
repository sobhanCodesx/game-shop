<?php

namespace App\Services;

use App\Models\DigitalOffer;
use App\Models\DigitalProduct;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketActivityNotification;
use App\Services\Telegram\TelegramAdminNotificationService;
use App\Services\Telegram\TelegramSellerTicketNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DigitalPriceInquiryService
{
    public function __construct(
        private readonly TelegramAdminNotificationService $telegramAdmin,
        private readonly TelegramSellerTicketNotificationService $telegramSeller,
    ) {}

    public function create(User $customer, DigitalProduct $product, DigitalOffer $offer): Ticket
    {
        if (
            (int) $offer->digital_product_id !== (int) $product->id
            || $offer->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'offer_id' => 'این ظرفیت برای این محصول قابل استعلام نیست.',
            ]);
        }

        $capacity = trim((string) $offer->label) ?: trim((string) $offer->code);
        $message = 'سلام، برای «'.$product->title.'» و '.$capacity.' لطفاً آخرین قیمت و موجودی همین ظرفیت را اعلام کنید.';

        $ticket = DB::transaction(function () use ($customer, $product, $capacity, $message): Ticket {
            $ticket = Ticket::query()->create([
                'number' => 'TK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $customer->id,
                'digital_product_id' => $product->id,
                'assigned_user_id' => $product->seller_id,
                'subject' => 'استعلام '.$capacity.': '.$product->title,
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
                    $customer->name.' برای '.$product->title.'، '.$capacity.' استعلام قیمت ثبت کرد.',
                    true,
                )));
            $this->telegramAdmin->newTicket($ticket, $message);
        }

        return $ticket;
    }
}
