<?php

namespace App\Services;

use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\OrderActivityNotification;
use App\Notifications\OrderCashbackNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private readonly CartService $carts, private readonly CouponService $coupons, private readonly CommerceSettings $settings) {}

    public function preview(array $cart, User $user, ?string $couponCode = null, bool $useWallet = false, ?int $exchangeRequestId = null): array
    {
        $items = $this->carts->resolve($cart, $user);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'سبد خرید خالی است.']);
        }
        $summary = $this->carts->summary($items);
        [, $exchangeUsed] = $this->resolveExchange($exchangeRequestId, $user, $items);
        $afterExchange = max(0, $summary['subtotal'] - $exchangeUsed);
        $coupon = $this->coupons->validate($couponCode, $afterExchange, $user);
        $commerce = $this->settings->all();
        $delivery = $summary['requires_shipping'] ? $commerce['delivery_fee'] : 0;
        $eligible = max(0, $afterExchange - $coupon['discount']);
        $grand = $eligible + $delivery;
        $wallet = $useWallet ? min((int) $user->wallet_balance, $grand) : 0;

        return [...$summary, 'items' => $items, 'exchange_credit_used' => $exchangeUsed, 'coupon_discount' => $coupon['discount'], 'delivery_fee' => $delivery, 'grand_total' => $grand, 'wallet_used' => $wallet, 'payable_amount' => $grand - $wallet, 'cashback_percent' => $commerce['cashback_percent'], 'cashback_amount' => (int) floor($eligible * $commerce['cashback_percent'] / 100)];
    }

    public function create(array $cart, User $user, array $address, ?string $couponCode, bool $useWallet, ?int $exchangeRequestId = null): Order
    {
        $order = DB::transaction(function () use ($cart, $user, $address, $couponCode, $useWallet, $exchangeRequestId) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $items = $this->carts->resolve($cart, $lockedUser);
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'سبد خرید خالی است.']);
            }

            foreach ($items as $item) {
                if ($item['variant_id']) {
                    $stock = ProductVariant::query()->lockForUpdate()->findOrFail($item['variant_id']);
                } else {
                    $stock = Product::query()->lockForUpdate()->findOrFail($item['product_id']);
                }
                if ($stock->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['cart' => "موجودی «{$item['title']}» کافی نیست."]);
                }
                $stock->decrement('stock', $item['quantity']);
            }

            $summary = $this->carts->summary($items);
            [$exchange, $exchangeUsed, $exchangeItemKey] = $this->resolveExchange($exchangeRequestId, $lockedUser, $items, true);
            $afterExchange = max(0, $summary['subtotal'] - $exchangeUsed);
            $couponResult = $this->coupons->validate($couponCode, $afterExchange, $lockedUser, true);
            $commerce = $this->settings->all();
            $delivery = $summary['requires_shipping'] ? $commerce['delivery_fee'] : 0;
            $eligible = max(0, $afterExchange - $couponResult['discount']);
            $grand = $eligible + $delivery;
            $walletUsed = $useWallet ? min((int) $lockedUser->wallet_balance, $grand) : 0;

            if ($walletUsed > 0) {
                $lockedUser->decrement('wallet_balance', $walletUsed);
            }
            $order = Order::query()->create([
                'number' => 'NP-'.now()->format('ymd').'-'.strtoupper(str()->random(7)), 'user_id' => $lockedUser->id,
                'coupon_id' => $couponResult['coupon']?->id, 'coupon_code' => $couponResult['coupon']?->code,
                'shipping_address' => $address, 'status' => 'pending', ...$summary,
                'exchange_request_id' => $exchange?->id, 'exchange_credit_used' => $exchangeUsed,
                'coupon_discount' => $couponResult['discount'], 'delivery_fee' => $delivery, 'grand_total' => $grand,
                'wallet_used' => $walletUsed, 'payable_amount' => $grand - $walletUsed,
                'cashback_percent' => $commerce['cashback_percent'], 'cashback_eligible_amount' => $eligible,
                'cashback_amount' => (int) floor($eligible * $commerce['cashback_percent'] / 100),
            ]);
            foreach ($items as $key => $item) {
                $order->items()->create(['product_id' => $item['product_id'], 'product_variant_id' => $item['variant_id'], 'title' => $item['title'], 'variant_name' => $item['variant'], 'sku' => $item['sku'], 'quantity' => $item['quantity'], 'regular_unit_price' => $item['regular_unit_price'], 'unit_price' => $item['unit_price'], 'discount_amount' => $item['discount_amount'], 'line_total' => $item['line_total'], 'exchange_credit_used' => $key === $exchangeItemKey ? $exchangeUsed : 0, 'requires_shipping' => $item['requires_shipping']]);
            }
            if ($exchange) $exchange->update(['exchange_status' => 'attached_to_order', 'exchange_order_id' => $order->id, 'exchange_credit_applied' => $exchangeUsed]);

            if ($walletUsed > 0) {
                WalletTransaction::query()->create(['user_id' => $lockedUser->id, 'order_id' => $order->id, 'type' => 'order_payment', 'amount' => -$walletUsed, 'balance_after' => $lockedUser->wallet_balance, 'description' => 'برداشت بابت سفارش '.$order->number]);
            }
            if ($couponResult['coupon']) {
                $couponResult['coupon']->increment('used_count');
                CouponRedemption::query()->create(['coupon_id' => $couponResult['coupon']->id, 'user_id' => $lockedUser->id, 'order_id' => $order->id, 'discount_amount' => $couponResult['discount']]);
            }

            return $order->load('items');
        }, 3);
        $order->user->notify(new OrderActivityNotification($order, 'سفارش شما ثبت شد', 'سفارش '.$order->number.' ثبت شد و در انتظار تأیید است.'));
        User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new OrderActivityNotification($order, 'سفارش جدید', $order->user->name.' سفارش '.$order->number.' را ثبت کرد.', true)));

        return $order;
    }

    public function cancelByCustomer(Order $order, User $user): Order
    {
        if ($order->user_id !== $user->id || $order->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'این سفارش دیگر توسط مشتری قابل لغو نیست.']);
        }

        return $this->transition($order, 'cancelled', $user);
    }

    public function transition(Order $order, string $status, User $actor, ?string $note = null): Order
    {
        $notify = false;
        $updated = DB::transaction(function () use ($order, $status, $actor, $note, &$notify) {
            $order = Order::query()->with(['items', 'coupon', 'exchangeRequest'])->lockForUpdate()->findOrFail($order->id);
            $allowed = [
                'pending' => ['approved', 'rejected', 'cancelled'],
                'approved' => ['processing', 'shipped', 'delivered', 'cancelled'],
                'processing' => ['shipped', 'delivered', 'cancelled'],
                'shipped' => ['delivered', 'cancelled'],
                'delivered' => ['cancelled'],
            ];
            if (! in_array($status, $allowed[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'تغییر وضعیت انتخاب‌شده برای این سفارش مجاز نیست.']);
            }
            $user = User::query()->lockForUpdate()->findOrFail($order->user_id);

            if ($status === 'approved' && $order->cashback_amount > 0 && ! $order->cashback_credited_at) {
                $user->increment('wallet_balance', $order->cashback_amount);
                WalletTransaction::query()->create(['user_id' => $user->id, 'order_id' => $order->id, 'type' => 'cashback', 'amount' => $order->cashback_amount, 'balance_after' => $user->wallet_balance, 'description' => 'بازگشت وجه سفارش '.$order->number]);
                $order->cashback_credited_at = now();
                $notify = true;
            }
            if (in_array($status, ['rejected', 'cancelled'], true)) {
                if ($order->cashback_credited_at && ! $order->cashback_reversed_at && $order->cashback_amount > 0) {
                    $user->decrement('wallet_balance', $order->cashback_amount);
                    WalletTransaction::query()->create(['user_id' => $user->id, 'order_id' => $order->id, 'type' => 'cashback_reversal', 'amount' => -$order->cashback_amount, 'balance_after' => $user->wallet_balance, 'description' => 'برگشت Cashback سفارش لغوشده '.$order->number]);
                    $order->cashback_reversed_at = now();
                }
                foreach ($order->items as $item) {
                    $stock = $item->product_variant_id ? ProductVariant::query()->lockForUpdate()->find($item->product_variant_id) : Product::query()->lockForUpdate()->find($item->product_id);
                    $stock?->increment('stock', $item->quantity);
                }
                if ($order->wallet_used > 0 && ! $order->wallet_refunded_at) {
                    $user->increment('wallet_balance', $order->wallet_used);
                    WalletTransaction::query()->create(['user_id' => $user->id, 'order_id' => $order->id, 'type' => 'order_refund', 'amount' => $order->wallet_used, 'balance_after' => $user->wallet_balance, 'description' => 'بازگشت برداشت سفارش '.$order->number]);
                    $order->wallet_refunded_at = now();
                }
                if ($order->coupon_id) {
                    $order->coupon?->decrement('used_count');
                    CouponRedemption::query()->where('order_id', $order->id)->delete();
                }
                if ($order->exchangeRequest && $order->exchangeRequest->exchange_order_id === $order->id && in_array($order->exchangeRequest->exchange_status, ['attached_to_order', 'received'], true)) {
                    $exchange = Ticket::query()->lockForUpdate()->find($order->exchange_request_id);
                    $expired = $exchange->exchange_credit_expires_at?->isPast();
                    $exchange->update(['exchange_status' => $expired ? 'expired' : 'accepted', 'exchange_order_id' => null, 'exchange_credit_applied' => 0, 'exchange_received_at' => null, 'exchange_expired_at' => $expired ? now() : null]);
                }
            }
            $order->fill(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'admin_note' => $note])->save();

            return $order->fresh(['user', 'items']);
        }, 3);
        if ($notify) {
            $updated->user->notify(new OrderCashbackNotification($updated));
        }
        $statusLabels = ['approved' => 'تأیید شد', 'rejected' => 'رد شد', 'processing' => 'در حال آماده‌سازی است', 'shipped' => 'ارسال شد', 'delivered' => 'تحویل شد', 'cancelled' => 'لغو شد'];
        if ($actor->is_admin) {
            $updated->user->notify(new OrderActivityNotification($updated, 'وضعیت سفارش تغییر کرد', 'سفارش '.$updated->number.' '.($statusLabels[$status] ?? 'به‌روزرسانی شد').'.'));
        } elseif ($status === 'cancelled') {
            User::query()->where('is_admin', true)->each(fn (User $admin) => $admin->notify(new OrderActivityNotification($updated, 'لغو سفارش توسط مشتری', 'سفارش '.$updated->number.' توسط مشتری لغو شد.', true)));
        }

        return $updated;
    }

    private function resolveExchange(?int $id, User $user, $items, bool $lock = false): array
    {
        if (! $id) return [null, 0, null];
        $query = Ticket::query()->whereKey($id);
        if ($lock) $query->lockForUpdate();
        $exchange = $query->firstOrFail();
        if ($exchange->type !== 'exchange' || $exchange->user_id !== $user->id || $exchange->exchange_status !== 'accepted' || $exchange->exchange_order_id || ! $exchange->target_product_id || ! $exchange->exchange_offer_amount) {
            throw ValidationException::withMessages(['exchange_request_id' => 'اعتبار معاوضه انتخاب‌شده معتبر یا قابل استفاده نیست.']);
        }
        if ($exchange->exchange_credit_expires_at?->isPast()) {
            if ($lock) $exchange->update(['exchange_status' => 'expired', 'exchange_expired_at' => now()]);
            throw ValidationException::withMessages(['exchange_request_id' => 'مهلت استفاده از این اعتبار معاوضه تمام شده است.']);
        }
        $itemKey = $items->search(fn ($item) => (int) $item['product_id'] === (int) $exchange->target_product_id);
        if ($itemKey === false) throw ValidationException::withMessages(['exchange_request_id' => 'این اعتبار فقط برای محصول هدف همان معاوضه قابل استفاده است.']);
        $used = min((int) $exchange->exchange_offer_amount, (int) $items[$itemKey]['line_total']);
        return [$exchange, $used, $itemKey];
    }
}
