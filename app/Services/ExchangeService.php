<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExchangeService
{
    public function offer(Ticket $ticket, int $amount, ?CarbonInterface $expiresAt = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $amount, $expiresAt) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureExchange($locked);
            if (! in_array($locked->exchange_status, ['pending_review', 'offered'], true) || $locked->exchange_order_id || $locked->exchange_credited_at) {
                throw ValidationException::withMessages(['exchange_offer_amount' => 'در این وضعیت امکان ثبت پیشنهاد وجود ندارد.']);
            }
            $expiry = $expiresAt ?? now()->addDays((int) config('exchange.credit_expiration_days', 30));
            if ($expiry->isPast()) throw ValidationException::withMessages(['exchange_credit_expires_at' => 'تاریخ انقضا باید در آینده باشد.']);
            $locked->update(['exchange_offer_amount' => $amount, 'exchange_status' => 'offered', 'exchange_credit_expires_at' => $expiry, 'exchange_offer_responded_at' => null]);
            return $locked->fresh();
        }, 3);
    }

    public function respond(Ticket $ticket, User $user, string $decision): Ticket
    {
        return DB::transaction(function () use ($ticket, $user, $decision) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureExchange($locked);
            if ($locked->user_id !== $user->id || $locked->exchange_status !== 'offered') throw ValidationException::withMessages(['decision' => 'این پیشنهاد دیگر قابل پاسخ نیست.']);
            if ($locked->exchange_credit_expires_at?->isPast()) {
                $locked->update(['exchange_status' => 'expired', 'exchange_expired_at' => now()]);
                throw ValidationException::withMessages(['decision' => 'مهلت این پیشنهاد تمام شده است.']);
            }
            $locked->update(['exchange_status' => $decision, 'exchange_offer_responded_at' => now()]);
            return $locked->fresh();
        }, 3);
    }

    public function markReceived(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureExchange($locked);
            if ($locked->exchange_status !== 'attached_to_order' || ! $locked->exchange_order_id) throw ValidationException::withMessages(['exchange_status' => 'فقط معاوضه متصل به سفارش را می‌توان دریافت‌شده اعلام کرد.']);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->exchange_order_id);
            if (in_array($order->status, ['rejected', 'cancelled'], true)) throw ValidationException::withMessages(['exchange_status' => 'سفارش مرتبط لغو یا رد شده است.']);
            $locked->update(['exchange_status' => 'received', 'exchange_received_at' => now()]);
            return $locked->fresh();
        }, 3);
    }

    public function complete(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureExchange($locked);
            if ($locked->exchange_status === 'completed' && $locked->exchange_completed_at) return $locked;
            if ($locked->exchange_status !== 'received' || ! $locked->exchange_order_id || ! $locked->exchange_offer_amount) throw ValidationException::withMessages(['exchange_status' => 'ابتدا دریافت کالای مشتری را تأیید کنید.']);
            $order = Order::query()->lockForUpdate()->findOrFail($locked->exchange_order_id);
            $excess = max(0, (int) $locked->exchange_offer_amount - (int) $locked->exchange_credit_applied);
            if ($excess > 0 && ! $locked->exchange_credited_at) {
                $user = User::query()->lockForUpdate()->findOrFail($locked->user_id);
                $user->increment('wallet_balance', $excess);
                WalletTransaction::query()->create(['user_id' => $user->id, 'order_id' => $order->id, 'ticket_id' => $locked->id, 'type' => 'exchange_excess', 'amount' => $excess, 'balance_after' => $user->wallet_balance, 'description' => 'مازاد نهایی معاوضه '.$locked->number.' / سفارش '.$order->number]);
                $locked->exchange_credited_at = now();
            }
            $locked->fill(['exchange_status' => 'completed', 'exchange_completed_at' => now()])->save();
            return $locked->fresh();
        }, 3);
    }

    public function cancel(Ticket $ticket): Ticket
    {
        return DB::transaction(function () use ($ticket) {
            $locked = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureExchange($locked);
            if (! in_array($locked->exchange_status, ['pending_review', 'offered', 'accepted', 'expired'], true) || $locked->exchange_order_id) throw ValidationException::withMessages(['exchange_status' => 'معاوضه متصل به سفارش یا تکمیل‌شده از این بخش قابل لغو نیست.']);
            $locked->update(['exchange_status' => 'cancelled', 'exchange_cancelled_at' => now()]);
            return $locked->fresh();
        }, 3);
    }

    private function ensureExchange(Ticket $ticket): void
    {
        if ($ticket->type !== 'exchange') throw ValidationException::withMessages(['ticket' => 'این تیکت از نوع معاوضه نیست.']);
    }
}
