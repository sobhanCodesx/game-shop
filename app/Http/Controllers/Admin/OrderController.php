<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MediaStorage;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::query()->with(['user:id,name,email', 'items.product.coverMedia'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest()->paginate(20)->withQueryString();
        $orders->getCollection()->transform(fn (Order $order) => [...$order->toArray(), 'items' => $order->items->map(fn ($item) => [...$item->toArray(), 'cover_url' => MediaStorage::url($item->product?->coverMedia?->path)])]);

        return Inertia::render('Admin/Orders/Index', ['orders' => $orders, 'filters' => $request->only('status')]);
    }

    public function show(Order $order): Response
    {
        $order->load(['items.product.coverMedia', 'user:id,name,email,phone,wallet_balance']);

        return Inertia::render('Admin/Orders/Show', ['order' => [...$order->toArray(), 'items' => $order->items->map(fn ($item) => [...$item->toArray(), 'cover_url' => MediaStorage::url($item->product?->coverMedia?->path)])]]);
    }

    public function update(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected,processing,shipped,delivered,cancelled'], 'admin_note' => ['nullable', 'string', 'max:2000']], ['status.in' => 'وضعیت انتخاب‌شده معتبر نیست.']);
        $orders->transition($order, $data['status'], $request->user(), $data['admin_note'] ?? null);

        return back()->with('success', $data['status'] === 'cancelled' ? 'سفارش لغو و تمام آثار مالی آن بازگردانده شد.' : 'وضعیت سفارش با موفقیت تغییر کرد.');
    }
}
