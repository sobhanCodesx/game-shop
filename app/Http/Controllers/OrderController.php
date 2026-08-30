<?php

namespace App\Http\Controllers;

use App\Models\Order;
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

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 404);
        $orders->cancelByCustomer($order, $request->user());

        return back()->with('success', 'سفارش لغو شد و موجودی‌ها و مبلغ کیف پول بازگردانده شدند.');
    }
}
