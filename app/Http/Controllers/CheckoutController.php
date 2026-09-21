<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CommerceSettings;
use App\Services\MobileCodeService;
use App\Services\OrderService;
use App\Support\PhoneNumber;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(Request $request, OrderService $orders, CommerceSettings $settings): Response|RedirectResponse
    {
        if (empty($request->session()->get('cart', []))) {
            return to_route('cart.index')->with('error', 'سبد خرید خالی است.');
        }
        if ($this->requiresGooglePhoneVerification($request)) {
            return to_route('checkout.phone.show')
                ->with('error', 'برای ثبت سفارش ابتدا شماره موبایل خود را با کد پیامکی تأیید کنید.');
        }
        $cart = $request->session()->get('cart', []);
        $preview = $orders->preview($cart, $request->user());

        $productIds = collect($preview['items'])->pluck('product_id');
        $exchanges = Ticket::query()->where('type', 'exchange')->where('user_id', $request->user()->id)->where('exchange_status', 'accepted')->whereNull('exchange_order_id')->whereIn('target_product_id', $productIds)->where(fn ($q) => $q->whereNull('exchange_credit_expires_at')->orWhere('exchange_credit_expires_at', '>', now()))->with('targetProduct:id,title')->get()->map(fn (Ticket $ticket) => ['id' => $ticket->id, 'number' => $ticket->number, 'amount' => (int) $ticket->exchange_offer_amount, 'trade_item_title' => $ticket->trade_item_title, 'expires_at' => $ticket->exchange_credit_expires_at?->toIso8601String(), 'product' => $ticket->targetProduct]);
        $selectedExchangeId = $exchanges->contains('id', $request->integer('exchange_request_id'))
            ? $request->integer('exchange_request_id')
            : ($exchanges->count() === 1 ? (int) $exchanges->first()['id'] : null);
        if ($selectedExchangeId) {
            $preview = $orders->preview($cart, $request->user(), exchangeRequestId: $selectedExchangeId);
        }

        return Inertia::render('Checkout/Index', [
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->get(),
            'profile' => $request->user()->only(['name', 'phone']),
            'walletBalance' => (int) $request->user()->wallet_balance,
            'availableExchanges' => $exchanges,
            'selectedExchangeId' => $selectedExchangeId,
            'pickupAddress' => $settings->all()['pickup_address'],
            'summary' => collect($preview)->except('items'),
        ]);
    }

    public function phoneVerification(Request $request): Response|RedirectResponse
    {
        if (empty($request->session()->get('cart', []))) {
            return to_route('cart.index')->with('error', 'سبد خرید خالی است.');
        }
        if (! $this->requiresGooglePhoneVerification($request)) {
            return to_route('checkout.show');
        }

        $sessionUserId = (int) $request->session()->get('checkout_phone_verification_user_id', 0);
        $sessionPhone = (string) $request->session()->get('checkout_phone_verification_phone', '');
        $codeSent = $sessionUserId === $request->user()->id && $sessionPhone !== '';

        return Inertia::render('Checkout/VerifyPhone', [
            'phone' => $codeSent ? $sessionPhone : (string) ($request->user()->phone ?? ''),
            'codeSent' => $codeSent,
        ]);
    }

    public function sendPhoneVerification(Request $request, MobileCodeService $codes): RedirectResponse
    {
        if (! $this->requiresGooglePhoneVerification($request)) {
            return to_route('checkout.show');
        }

        $data = $request->validate(['phone' => ['required', 'string']]);
        $phone = PhoneNumber::normalize($data['phone']);
        validator(
            ['phone' => $phone],
            ['phone' => [Rule::unique('users', 'phone')->ignore($request->user()->id)]],
            ['phone.unique' => 'این شماره موبایل قبلاً برای حساب دیگری ثبت شده است.'],
        )->validate();

        $codes->send($phone, 'checkout_verify_mobile');

        $request->user()->forceFill([
            'phone' => $phone,
            'phone_verified_at' => null,
        ])->save();

        $request->session()->put([
            'checkout_phone_verification_user_id' => $request->user()->id,
            'checkout_phone_verification_phone' => $phone,
        ]);

        return to_route('checkout.phone.show')->with('success', 'کد تأیید پیامکی ارسال شد.');
    }

    public function confirmPhoneVerification(Request $request, MobileCodeService $codes): RedirectResponse
    {
        if (! $this->requiresGooglePhoneVerification($request)) {
            return to_route('checkout.show');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $phone = $this->pendingCheckoutVerificationPhone($request);

        $codes->verify($phone, 'checkout_verify_mobile', $data['code']);

        $request->user()->forceFill([
            'phone' => $phone,
            'phone_verified_at' => now(),
        ])->save();

        $request->session()->forget([
            'checkout_phone_verification_user_id',
            'checkout_phone_verification_phone',
        ]);

        return to_route('checkout.show')->with('success', 'شماره موبایل شما تأیید شد؛ اکنون می‌توانید سفارش را ثبت کنید.');
    }

    public function resendPhoneVerification(Request $request, MobileCodeService $codes): RedirectResponse
    {
        if (! $this->requiresGooglePhoneVerification($request)) {
            return to_route('checkout.show');
        }

        $phone = $this->pendingCheckoutVerificationPhone($request);
        $codes->send($phone, 'checkout_verify_mobile');

        return back()->with('success', 'کد جدید ارسال شد.');
    }

    public function preview(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'use_wallet' => ['boolean'],
            'exchange_request_id' => ['nullable', 'integer'],
            'delivery_method' => ['nullable', Rule::in(['courier', 'pickup'])],
        ]);
        $preview = $orders->preview(
            $request->session()->get('cart', []),
            $request->user(),
            $data['coupon_code'] ?? null,
            (bool) ($data['use_wallet'] ?? false),
            $data['exchange_request_id'] ?? null,
            $data['delivery_method'] ?? 'courier',
        );

        return response()->json(collect($preview)->except('items'));
    }

    public function store(CheckoutRequest $request, OrderService $orders): RedirectResponse
    {
        $data = $request->validated();
        $deliveryMethod = $data['delivery_method'];
        $address = [];

        if ($deliveryMethod === 'courier') {
            $address = $data['address_mode'] === 'saved'
                ? $request->user()->addresses()->findOrFail($data['address_id'])->only(['recipient_name', 'phone', 'province', 'city', 'postal_code', 'address_line', 'plaque', 'unit'])
                : $data['address'];

            if (($address['province'] ?? null) !== 'تهران' || ($address['city'] ?? null) !== 'تهران') {
                throw ValidationException::withMessages(['address.province' => 'در حال حاضر تحویل سفارش با پیک فقط برای ساکنان شهر تهران فعال است.']);
            }

            if ($data['address_mode'] === 'new' && ($data['save_address'] ?? false)) {
                $request->user()->addresses()->create([...$address, 'title' => 'آدرس سفارش']);
            }
        }

        $order = $orders->create(
            $request->session()->get('cart', []),
            $request->user(),
            $address,
            $data['coupon_code'] ?? null,
            (bool) ($data['use_wallet'] ?? false),
            $data['exchange_request_id'] ?? null,
            $deliveryMethod,
        );
        $request->session()->forget('cart');

        return to_route('orders.show', $order)->with('success', 'سفارش ثبت شد و در انتظار تأیید مدیر است.');
    }

    private function requiresGooglePhoneVerification(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user?->google_id && ! $user->phone_verified_at);
    }

    private function pendingCheckoutVerificationPhone(Request $request): string
    {
        $sessionUserId = (int) $request->session()->get('checkout_phone_verification_user_id', 0);
        $phone = (string) $request->session()->get('checkout_phone_verification_phone', '');

        if ($sessionUserId !== $request->user()->id || $phone === '' || $request->user()->phone !== $phone) {
            throw ValidationException::withMessages([
                'phone' => 'ابتدا شماره موبایل را وارد کنید و کد تأیید بگیرید.',
            ]);
        }

        return $phone;
    }

}
