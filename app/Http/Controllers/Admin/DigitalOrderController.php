<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DigitalDelivery;
use App\Models\DigitalOrder;
use App\Models\DigitalOrderMessage;
use App\Models\User;
use App\Notifications\DigitalOrderNotification;
use App\Services\DigitalOrderService;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DigitalOrderController extends Controller
{
    public function index(Request $request, DigitalOrderService $service): Response
    {
        $service->releaseExpiredReservations();

        $query = DigitalOrder::query()
            ->with(['customer:id,name,phone,email', 'product:id,title,game_id,platform_id', 'product.game:id,name,cover', 'product.platform:id,name', 'offer:id,label'])
            ->latest('id');

        $this->scopeSeller($query, $request->user());

        return Inertia::render('Admin/Digital/Orders/Index', [
            'orders' => $query->paginate(30)->withQueryString()->through(fn (DigitalOrder $order) => [
                ...$order->only(['id', 'number', 'sale_price', 'supplier_cost', 'order_status', 'payment_status', 'delivery_status', 'created_at']),
                'customer' => $order->customer?->only(['id', 'name', 'phone', 'email']),
                'product_title' => $order->product->title,
                'offer_label' => $order->offer->label,
                'platform' => $order->product->platform?->name,
                'cover_url' => MediaStorage::url($order->product->game?->cover),
            ]),
        ]);
    }

    public function show(Request $request, DigitalOrder $digitalOrder): Response
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        $digitalOrder->load([
            'customer:id,name,email,phone',
            'product.game:id,name,cover',
            'product.platform:id,name',
            'offer:id,label,code',
            'messages.user:id,name,is_admin',
            'delivery',
        ]);

        $digitalOrder->messages()
            ->where('user_id', $digitalOrder->user_id)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return Inertia::render('Admin/Digital/Orders/Show', [
            'order' => $this->payload($digitalOrder->fresh([
                'customer:id,name,email,phone',
                'product.game:id,name,cover',
                'product.platform:id,name',
                'offer:id,label,code',
                'messages.user:id,name,is_admin',
                'delivery',
            ])),
        ]);
    }

    public function messages(Request $request, DigitalOrder $digitalOrder): JsonResponse
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        $digitalOrder->messages()->where('user_id', $digitalOrder->user_id)->whereNull('seen_at')->update(['seen_at' => now()]);

        return response()->json([
            'messages' => $digitalOrder->messages()->with('user:id,name,is_admin')->get()->map(fn ($message) => $this->message($message)),
            'order_status' => $digitalOrder->order_status,
            'payment_status' => $digitalOrder->payment_status,
            'delivery_status' => $digitalOrder->delivery_status,
        ]);
    }

    public function reply(Request $request, DigitalOrder $digitalOrder): RedirectResponse
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);

        $digitalOrder->messages()->create([
            'user_id' => $request->user()->id,
            'type' => 'message',
            'message' => trim($data['message']),
        ]);

        $digitalOrder->customer?->notify(new DigitalOrderNotification(
            'پاسخ جدید سفارش دیجیتال',
            "برای سفارش {$digitalOrder->number} پیام جدید دارید.",
            route('account.digital-orders.show', $digitalOrder),
        ));

        return back();
    }

    public function confirmPayment(Request $request, DigitalOrder $digitalOrder, DigitalOrderService $service): RedirectResponse
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        $service->markPaid($digitalOrder, $request->user());

        $digitalOrder->customer?->notify(new DigitalOrderNotification(
            'پرداخت تأیید شد',
            "پرداخت سفارش {$digitalOrder->number} تأیید شد و آماده‌سازی شروع می‌شود.",
            route('account.digital-orders.show', $digitalOrder),
        ));

        return back()->with('success', 'پرداخت تأیید شد.');
    }

    public function preparing(Request $request, DigitalOrder $digitalOrder): RedirectResponse
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        abort_unless(
            $digitalOrder->payment_status === 'paid'
            && $digitalOrder->order_status === 'active'
            && $digitalOrder->delivery_status === 'waiting',
            422,
        );

        $digitalOrder->update(['delivery_status' => 'preparing']);
        $digitalOrder->messages()->create([
            'user_id' => $request->user()->id,
            'type' => 'system',
            'message' => 'آماده‌سازی اکانت شروع شد.',
        ]);

        return back()->with('success', 'وضعیت آماده‌سازی ثبت شد.');
    }

    public function deliver(Request $request, DigitalOrder $digitalOrder): RedirectResponse
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        abort_unless(
            $digitalOrder->payment_status === 'paid'
            && $digitalOrder->order_status === 'active'
            && in_array($digitalOrder->delivery_status, ['waiting', 'preparing', 'problem', 'delivered'], true),
            422,
        );

        $data = $request->validate([
            'login' => ['required', 'string', 'max:1000'],
            'password' => ['required', 'string', 'max:1000'],
            'backup_code' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        DigitalDelivery::query()->updateOrCreate(
            ['digital_order_id' => $digitalOrder->id],
            [
                ...$data,
                'delivered_by' => $request->user()->id,
                'delivered_at' => now(),
                'customer_viewed_at' => null,
            ],
        );

        $digitalOrder->update([
            'order_status' => 'active',
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
        ]);
        $digitalOrder->messages()->create([
            'user_id' => $request->user()->id,
            'type' => 'delivery',
            'message' => 'اطلاعات اکانت آماده و به‌صورت امن در همین سفارش قرار گرفت.',
        ]);

        $digitalOrder->customer?->notify(new DigitalOrderNotification(
            'اکانت آماده تحویل است',
            "اطلاعات سفارش {$digitalOrder->number} آماده شده است.",
            route('account.digital-orders.show', $digitalOrder),
        ));

        return back()->with('success', 'اطلاعات اکانت به‌صورت امن تحویل شد.');
    }

    public function cancel(Request $request, DigitalOrder $digitalOrder, DigitalOrderService $service): RedirectResponse
    {
        $this->authorizeOrder($request->user(), $digitalOrder);
        $service->cancel($digitalOrder, $request->user());

        return back()->with('success', 'سفارش لغو شد.');
    }

    private function authorizeOrder(User $actor, DigitalOrder $order): void
    {
        if ($actor->role === 'digital-seller') {
            abort_unless($order->seller_id === $actor->id, 404);
        }
    }

    private function scopeSeller($query, User $actor): void
    {
        if ($actor->role === 'digital-seller') {
            $query->where('seller_id', $actor->id);
        }
    }

    private function payload(DigitalOrder $order): array
    {
        return [
            ...$order->only([
                'id', 'number', 'sale_price', 'supplier_cost', 'order_status',
                'payment_status', 'delivery_status', 'reservation_expires_at',
                'paid_at', 'delivered_at', 'completed_at', 'created_at',
            ]),
            'customer' => $order->customer?->only(['id', 'name', 'email', 'phone']),
            'product' => [
                'title' => $order->product->title,
                'platform' => $order->product->platform?->name,
                'cover_url' => MediaStorage::url($order->product->game?->cover),
            ],
            'offer' => $order->offer->only(['id', 'label', 'code']),
            'messages' => $order->messages->map(fn ($message) => $this->message($message))->values(),
            'delivery' => $order->delivery?->only(['login', 'password', 'backup_code', 'instructions', 'delivered_at', 'customer_viewed_at']),
        ];
    }

    private function message(DigitalOrderMessage $message): array
    {
        return [
            ...$message->only(['id', 'type', 'message', 'attachment_name', 'seen_at', 'created_at']),
            'sender' => $message->user?->name,
            'is_admin' => (bool) $message->user?->is_admin,
            'attachment_url' => $message->attachment_path
                ? route('digital-order-files.show', $message)
                : null,
        ];
    }
}
