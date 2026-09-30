<?php

namespace App\Services;

use App\Models\DigitalOffer;
use App\Models\DigitalOrder;
use App\Models\DigitalOrderMessage;
use App\Models\User;
use App\Notifications\DigitalOrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DigitalOrderService
{
    public function create(User $customer, DigitalOffer $offer): DigitalOrder
    {
        $this->releaseExpiredReservations();

        $order = DB::transaction(function () use ($customer, $offer): DigitalOrder {
            $lockedOffer = DigitalOffer::query()
                ->with('product')
                ->lockForUpdate()
                ->findOrFail($offer->id);

            if ($lockedOffer->product->status !== 'published') {
                throw ValidationException::withMessages(['offer_id' => 'این محصول در حال حاضر قابل سفارش نیست.']);
            }

            $lockedOffer->ensureAvailable();
            $lockedOffer->increment('reserved_stock');

            $order = DigitalOrder::query()->create([
                'number' => 'PN-D-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $customer->id,
                'seller_id' => $lockedOffer->product->seller_id,
                'digital_product_id' => $lockedOffer->digital_product_id,
                'digital_offer_id' => $lockedOffer->id,
                'sale_price' => $lockedOffer->price,
                'supplier_cost' => $lockedOffer->supplier_cost,
                'order_status' => 'new',
                'payment_status' => 'unpaid',
                'delivery_status' => 'waiting',
                'reservation_expires_at' => now()->addMinutes(30),
            ]);

            $order->messages()->create([
                'type' => 'system',
                'message' => 'سفارش ثبت شد. پشتیبانی از همین گفت‌وگو روند پرداخت و تحویل را با شما ادامه می‌دهد.',
            ]);

            return $order;
        }, 3);

        $seller = User::query()->find($order->seller_id);
        $seller?->notify(new DigitalOrderNotification(
            'سفارش دیجیتال جدید',
            "سفارش {$order->number} ثبت شد.",
            route('admin.digital-orders.show', $order),
        ));

        return $order;
    }

    public function markPaid(DigitalOrder $order, User $actor): DigitalOrder
    {
        $result = DB::transaction(function () use ($order, $actor): DigitalOrder {
            $locked = DigitalOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->order_status, ['cancelled', 'expired', 'completed'], true)) {
                return $locked;
            }

            if ($locked->payment_status === 'paid') {
                return $locked;
            }

            if ($locked->reservation_expires_at?->isPast()) {
                $offer = DigitalOffer::query()->lockForUpdate()->findOrFail($locked->digital_offer_id);
                if ($offer->reserved_stock > 0) {
                    $offer->decrement('reserved_stock');
                }
                $locked->update(['order_status' => 'expired', 'reservation_expires_at' => null]);
                $locked->messages()->create([
                    'type' => 'system',
                    'message' => 'زمان رزرو سفارش تمام شد و موجودی آزاد شد.',
                ]);

                return $locked->fresh();
            }

            $locked->update([
                'order_status' => 'active',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'reservation_expires_at' => null,
            ]);
            $locked->messages()->create([
                'user_id' => $actor->id,
                'type' => 'system',
                'message' => 'پرداخت سفارش تأیید شد ✅',
            ]);

            return $locked->fresh();
        }, 3);

        if ($result->order_status === 'expired') {
            throw ValidationException::withMessages([
                'status' => 'زمان رزرو این سفارش تمام شده است؛ مشتری باید سفارش جدید ثبت کند.',
            ]);
        }

        if (in_array($result->order_status, ['cancelled', 'completed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'این سفارش دیگر قابل تأیید پرداخت نیست.',
            ]);
        }

        return $result;
    }

    public function complete(DigitalOrder $order): DigitalOrder
    {
        return DB::transaction(function () use ($order): DigitalOrder {
            $locked = DigitalOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->order_status === 'completed') {
                return $locked;
            }
            if ($locked->order_status !== 'active' || $locked->payment_status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'این سفارش در وضعیت قابل تکمیل نیست.']);
            }
            if ($locked->delivery_status !== 'delivered') {
                throw ValidationException::withMessages(['status' => 'تا قبل از تحویل اطلاعات اکانت، سفارش قابل تکمیل نیست.']);
            }

            $offer = DigitalOffer::query()->lockForUpdate()->findOrFail($locked->digital_offer_id);
            if ($offer->stock < 1 || $offer->reserved_stock < 1) {
                throw ValidationException::withMessages(['stock' => 'موجودی رزروشده این سفارش ناسازگار شده است.']);
            }

            $offer->decrement('stock');
            $offer->decrement('reserved_stock');
            $locked->update([
                'order_status' => 'completed',
                'delivery_status' => 'resolved',
                'completed_at' => now(),
            ]);
            $locked->messages()->create([
                'type' => 'system',
                'message' => 'مشتری دریافت موفق اکانت را تأیید کرد. سفارش تکمیل شد.',
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function cancel(DigitalOrder $order, ?User $actor = null): DigitalOrder
    {
        return DB::transaction(function () use ($order, $actor): DigitalOrder {
            $locked = DigitalOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->order_status, ['completed', 'cancelled', 'expired'], true)) {
                return $locked;
            }
            if ($locked->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'سفارش پرداخت‌شده از مسیر لغو عادی قابل لغو نیست؛ ابتدا وضعیت مالی باید تعیین تکلیف شود.',
                ]);
            }
            if ($locked->delivery_status !== 'waiting') {
                throw ValidationException::withMessages([
                    'status' => 'سفارشی که وارد فرایند تحویل شده از مسیر لغو عادی قابل لغو نیست.',
                ]);
            }

            $offer = DigitalOffer::query()->lockForUpdate()->findOrFail($locked->digital_offer_id);
            if ($offer->reserved_stock > 0) {
                $offer->decrement('reserved_stock');
            }

            $locked->update([
                'order_status' => 'cancelled',
                'cancelled_at' => now(),
                'reservation_expires_at' => null,
            ]);
            $locked->messages()->create([
                'user_id' => $actor?->id,
                'type' => 'system',
                'message' => 'سفارش لغو شد و موجودی رزروشده آزاد شد.',
            ]);

            return $locked->fresh();
        }, 3);
    }

    public function releaseExpiredReservations(): int
    {
        $ids = DigitalOrder::query()
            ->where('order_status', 'new')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('reservation_expires_at')
            ->where('reservation_expires_at', '<=', now())
            ->limit(100)
            ->pluck('id');

        $released = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, &$released): void {
                $order = DigitalOrder::query()->lockForUpdate()->find($id);
                if (! $order || $order->order_status !== 'new' || $order->payment_status !== 'unpaid' || ! $order->reservation_expires_at?->isPast()) {
                    return;
                }

                $offer = DigitalOffer::query()->lockForUpdate()->find($order->digital_offer_id);
                if ($offer && $offer->reserved_stock > 0) {
                    $offer->decrement('reserved_stock');
                }

                $order->update(['order_status' => 'expired', 'reservation_expires_at' => null]);
                DigitalOrderMessage::query()->create([
                    'digital_order_id' => $order->id,
                    'type' => 'system',
                    'message' => 'زمان رزرو سفارش تمام شد. برای خرید دوباره، یک سفارش جدید ثبت کنید.',
                ]);
                $released++;
            }, 3);
        }

        return $released;
    }
}
