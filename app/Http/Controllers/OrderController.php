<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MediaStorage;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('Orders/Show', ['order' => $order->load('items')]);
    }

    public function invoice(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        abort_unless($order->isInvoiceable(), 404);

        $order->load(['items.product.coverMedia', 'user:id,name,email,phone']);

        return Inertia::render('Orders/Invoice', [
            'invoice' => [
                ...$order->only([
                    'id', 'number', 'status', 'shipping_address', 'delivery_method', 'pickup_address', 'regular_subtotal',
                    'product_discount', 'subtotal', 'coupon_code', 'coupon_discount',
                    'delivery_fee', 'grand_total', 'wallet_used', 'payable_amount',
                    'cashback_amount', 'exchange_request_id', 'exchange_credit_used', 'trade_user_id',
                    'approved_product_id', 'approved_trade_value', 'trade_item_title',
                    'trade_item_description', 'trade_item_images', 'trade_item_metadata', 'created_at',
                ]),
                'customer' => $order->user->only(['name', 'email', 'phone']),
                'items' => $order->items->map(fn ($item) => [
                    ...$item->only([
                        'id', 'title', 'variant_name', 'sku', 'quantity',
                        'regular_unit_price', 'unit_price', 'discount_amount', 'line_total', 'exchange_credit_used',
                    ]),
                    'cover_url' => MediaStorage::url($item->product?->coverMedia?->path),
                ]),
            ],
        ]);
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $orders->cancelByCustomer($order, $request->user());

        return back()->with('success', 'سفارش لغو شد و موجودی‌ها و مبلغ کیف پول بازگردانده شدند.');
    }
}
