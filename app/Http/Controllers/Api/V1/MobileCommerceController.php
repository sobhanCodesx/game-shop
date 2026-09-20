<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\CartService;
use App\Services\CommerceSettings;
use App\Services\MediaStorage;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MobileCommerceController extends Controller
{
    public function cart(
        Request $request,
        CartService $carts,
        CommerceSettings $settings,
    ): JsonResponse {
        $cart = $this->cartFromRequest($request);
        $items = $carts->resolve($cart, $request->user());

        if ($items->count() !== count($cart)) {
            throw ValidationException::withMessages([
                'items' => 'یک یا چند آیتم سبد خرید دیگر معتبر یا قابل فروش نیست.',
            ]);
        }

        $summary = $carts->summary($items);
        $commerce = $settings->all();

        return response()->json([
            'items' => $items,
            'summary' => $summary,
            'cashback_percent' => $commerce['cashback_percent'],
            'estimated_cashback' => (int) floor(
                $summary['subtotal'] * $commerce['cashback_percent'] / 100,
            ),
        ]);
    }

    public function checkout(
        Request $request,
        OrderService $orders,
    ): JsonResponse {
        $cart = $this->cartFromRequest($request);
        $selectedExchangeId = $request->integer('exchange_request_id') ?: null;
        $preview = $orders->preview(
            $cart,
            $request->user(),
            exchangeRequestId: $selectedExchangeId,
        );

        $productIds = collect($preview['items'])->pluck('product_id');
        $exchanges = Ticket::query()
            ->where('type', 'exchange')
            ->where('user_id', $request->user()->id)
            ->where('exchange_status', 'accepted')
            ->whereNull('exchange_order_id')
            ->whereIn('target_product_id', $productIds)
            ->where(fn ($query) => $query
                ->whereNull('exchange_credit_expires_at')
                ->orWhere('exchange_credit_expires_at', '>', now()))
            ->with('targetProduct:id,title,slug')
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'amount' => (int) $ticket->exchange_offer_amount,
                'trade_item_title' => $ticket->trade_item_title,
                'expires_at' => $ticket->exchange_credit_expires_at?->toIso8601String(),
                'product' => $ticket->targetProduct,
            ])
            ->values();

        if ($selectedExchangeId && ! $exchanges->contains('id', $selectedExchangeId)) {
            $selectedExchangeId = null;
            $preview = $orders->preview($cart, $request->user());
        }

        return response()->json([
            'addresses' => $request->user()->addresses()->orderByDesc('is_default')->get(),
            'profile' => $request->user()->only(['name', 'phone']),
            'wallet_balance' => (int) $request->user()->wallet_balance,
            'available_exchanges' => $exchanges,
            'selected_exchange_id' => $selectedExchangeId,
            'items' => $preview['items'],
            'summary' => collect($preview)->except('items'),
        ]);
    }

    public function preview(Request $request, OrderService $orders): JsonResponse
    {
        $data = $request->validate([
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'use_wallet' => ['boolean'],
            'exchange_request_id' => ['nullable', 'integer'],
        ]);

        $preview = $orders->preview(
            $this->cartFromRequest($request),
            $request->user(),
            $data['coupon_code'] ?? null,
            (bool) ($data['use_wallet'] ?? false),
            $data['exchange_request_id'] ?? null,
        );

        return response()->json($preview);
    }

    public function store(CheckoutRequest $request, OrderService $orders): JsonResponse
    {
        $cart = $this->cartFromRequest($request);
        $data = $request->validated();

        $address = $data['address_mode'] === 'saved'
            ? $request->user()
                ->addresses()
                ->findOrFail($data['address_id'])
                ->only([
                    'recipient_name', 'phone', 'province', 'city', 'postal_code',
                    'address_line', 'plaque', 'unit',
                ])
            : $data['address'];

        if (($address['province'] ?? null) !== 'تهران' || ($address['city'] ?? null) !== 'تهران') {
            throw ValidationException::withMessages([
                'address.province' => 'در حال حاضر تحویل سفارش فقط برای ساکنان شهر تهران فعال است.',
            ]);
        }

        if ($data['address_mode'] === 'new' && ($data['save_address'] ?? false)) {
            $request->user()->addresses()->create([
                ...$address,
                'title' => 'آدرس سفارش',
            ]);
        }

        $order = $orders->create(
            $cart,
            $request->user(),
            $address,
            $data['coupon_code'] ?? null,
            (bool) ($data['use_wallet'] ?? false),
            $data['exchange_request_id'] ?? null,
        );

        return response()->json([
            'message' => 'سفارش ثبت شد و در انتظار تأیید مدیر است.',
            'order' => $this->orderPayload($order),
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load('items');

        return response()->json(['order' => $this->orderPayload($order)]);
    }

    public function invoice(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->isInvoiceable(), 404);

        $order->load(['items.product.coverMedia', 'user:id,name,email,phone']);

        return response()->json([
            'invoice' => [
                ...$order->only([
                    'id', 'number', 'status', 'shipping_address', 'regular_subtotal',
                    'product_discount', 'subtotal', 'coupon_code', 'coupon_discount',
                    'delivery_fee', 'grand_total', 'wallet_used', 'payable_amount',
                    'cashback_amount', 'exchange_request_id', 'exchange_credit_used',
                    'trade_user_id', 'approved_product_id', 'approved_trade_value',
                    'trade_item_title', 'trade_item_description', 'trade_item_images',
                    'trade_item_metadata', 'created_at',
                ]),
                'customer' => $order->user->only(['name', 'email', 'phone']),
                'items' => $order->items->map(fn ($item) => [
                    ...$item->only([
                        'id', 'title', 'variant_name', 'sku', 'quantity',
                        'regular_unit_price', 'unit_price', 'discount_amount',
                        'line_total', 'exchange_credit_used',
                    ]),
                    'cover_url' => MediaStorage::url($item->product?->coverMedia?->path),
                ])->values(),
            ],
        ]);
    }

    public function cancel(
        Request $request,
        Order $order,
        OrderService $orders,
    ): JsonResponse {
        abort_unless($order->user_id === $request->user()->id, 404);

        $updated = $orders->cancelByCustomer($order, $request->user());

        return response()->json([
            'message' => 'سفارش لغو شد و موجودی‌ها و مبلغ کیف پول بازگردانده شدند.',
            'order' => $this->orderPayload($updated->loadMissing('items')),
        ]);
    }

    private function cartFromRequest(Request $request): array
    {
        $data = validator($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'between:1,10'],
        ])->validate();

        $cart = [];

        foreach ($data['items'] as $item) {
            $key = $item['product_id'].':'.($item['variant_id'] ?? 'base');
            $cart[$key] = [
                'product_id' => (int) $item['product_id'],
                'variant_id' => isset($item['variant_id']) ? (int) $item['variant_id'] : null,
                'quantity' => (int) $item['quantity'],
            ];
        }

        return $cart;
    }

    private function orderPayload(Order $order): array
    {
        return [
            ...$order->only([
                'id', 'number', 'status', 'shipping_address', 'regular_subtotal',
                'product_discount', 'subtotal', 'coupon_code', 'coupon_discount',
                'delivery_fee', 'grand_total', 'wallet_used', 'payable_amount',
                'cashback_amount', 'exchange_request_id', 'exchange_credit_used',
                'created_at', 'updated_at',
            ]),
            'items' => $order->relationLoaded('items')
                ? $order->items->map(fn ($item) => $item->only([
                    'id', 'product_id', 'product_variant_id', 'title', 'variant_name',
                    'sku', 'quantity', 'regular_unit_price', 'unit_price',
                    'discount_amount', 'line_total',
                ]))->values()
                : [],
        ];
    }
}
