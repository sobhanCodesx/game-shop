<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\OrderService;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(Request $request, OrderService $orders): Response|RedirectResponse
    {
        if (empty($request->session()->get('cart', []))) {
            return to_route('cart.index')->with('error', 'سبد خرید خالی است.');
        }
        $preview = $orders->preview($request->session()->get('cart', []), $request->user());

        $productIds = collect($preview['items'])->pluck('product_id');
        $exchanges = Ticket::query()->where('type', 'exchange')->where('user_id', $request->user()->id)->where('exchange_status', 'accepted')->whereNull('exchange_order_id')->whereIn('target_product_id', $productIds)->where(fn ($q) => $q->whereNull('exchange_credit_expires_at')->orWhere('exchange_credit_expires_at', '>', now()))->with('targetProduct:id,title')->get()->map(fn (Ticket $ticket) => ['id' => $ticket->id, 'number' => $ticket->number, 'amount' => (int) $ticket->exchange_offer_amount, 'expires_at' => $ticket->exchange_credit_expires_at?->toIso8601String(), 'product' => $ticket->targetProduct]);

        return Inertia::render('Checkout/Index', ['addresses' => $request->user()->addresses()->orderByDesc('is_default')->get(), 'profile' => $request->user()->only(['name', 'phone']), 'walletBalance' => (int) $request->user()->wallet_balance, 'availableExchanges' => $exchanges, 'summary' => collect($preview)->except('items')]);
    }

    public function preview(Request $request, OrderService $orders)
    {
        $data = $request->validate(['coupon_code' => ['nullable', 'string', 'max:50'], 'use_wallet' => ['boolean'], 'exchange_request_id' => ['nullable', 'integer']]);
        $preview = $orders->preview($request->session()->get('cart', []), $request->user(), $data['coupon_code'] ?? null, (bool) ($data['use_wallet'] ?? false), $data['exchange_request_id'] ?? null);

        return response()->json(collect($preview)->except('items'));
    }

    public function store(CheckoutRequest $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validated();
        $address = $data['address_mode'] === 'saved'
            ? $request->user()->addresses()->findOrFail($data['address_id'])->only(['recipient_name', 'phone', 'province', 'city', 'postal_code', 'address_line', 'plaque', 'unit'])
            : $data['address'];
        if (($address['province'] ?? null) !== 'تهران' || ($address['city'] ?? null) !== 'تهران') {
            throw ValidationException::withMessages(['address.province' => 'در حال حاضر تحویل سفارش فقط برای ساکنان شهر تهران فعال است.']);
        }
        if ($data['address_mode'] === 'new' && ($data['save_address'] ?? false)) {
            $request->user()->addresses()->create([...$address, 'title' => 'آدرس سفارش']);
        }
        $order = $orders->create($request->session()->get('cart', []), $request->user(), $address, $data['coupon_code'] ?? null, (bool) ($data['use_wallet'] ?? false), $data['exchange_request_id'] ?? null);
        $request->session()->forget('cart');

        return to_route('orders.show', $order)->with('success', 'سفارش ثبت شد و در انتظار تأیید مدیر است.');
    }
}
