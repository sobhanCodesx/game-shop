<?php

namespace App\Http\Controllers;

use App\Models\DigitalOrder;
use App\Models\DigitalOrderMessage;
use App\Notifications\DigitalOrderNotification;
use App\Services\DigitalOrderService;
use App\Services\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DigitalOrderController extends Controller
{
    public function index(Request $request, DigitalOrderService $orders): Response
    {
        $orders->releaseExpiredReservations();

        $items = DigitalOrder::query()
            ->where('user_id', $request->user()->id)
            ->with(['product.game:id,name,cover', 'product.platform:id,name', 'offer:id,label'])
            ->latest('id')
            ->paginate(20)
            ->through(fn (DigitalOrder $order) => $this->orderCard($order));

        return Inertia::render('Digital/Orders/Index', ['orders' => $items]);
    }

    public function show(Request $request, DigitalOrder $digitalOrder): Response
    {
        $this->authorizeCustomer($request, $digitalOrder);

        $digitalOrder->load([
            'product.game:id,name,cover,background',
            'product.platform:id,name',
            'offer:id,label,code',
            'messages.user:id,name,is_admin',
            'delivery',
        ]);

        $digitalOrder->messages()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $request->user()->id))
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        if ($digitalOrder->delivery && ! $digitalOrder->delivery->customer_viewed_at) {
            $digitalOrder->delivery->update(['customer_viewed_at' => now()]);
        }

        return Inertia::render('Digital/Orders/Show', [
            'order' => $this->orderPayload($digitalOrder->fresh([
                'product.game:id,name,cover,background',
                'product.platform:id,name',
                'offer:id,label,code',
                'messages.user:id,name,is_admin',
                'delivery',
            ])),
        ]);
    }

    public function messages(Request $request, DigitalOrder $digitalOrder): JsonResponse
    {
        $this->authorizeCustomer($request, $digitalOrder);
        $digitalOrder->load(['messages.user:id,name,is_admin']);

        $digitalOrder->messages()
            ->where(fn ($query) => $query->whereNull('user_id')->orWhere('user_id', '!=', $request->user()->id))
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return response()->json([
            'messages' => $digitalOrder->fresh('messages.user')->messages->map(fn ($message) => $this->messagePayload($message))->values(),
            'order_status' => $digitalOrder->order_status,
            'payment_status' => $digitalOrder->payment_status,
            'delivery_status' => $digitalOrder->delivery_status,
        ]);
    }

    public function reply(Request $request, DigitalOrder $digitalOrder): RedirectResponse
    {
        $this->authorizeCustomer($request, $digitalOrder);
        abort_if(in_array($digitalOrder->order_status, ['cancelled', 'expired'], true), 422);

        $data = $request->validate(['message' => ['required', 'string', 'max:3000']]);
        $digitalOrder->messages()->create([
            'user_id' => $request->user()->id,
            'type' => 'message',
            'message' => trim($data['message']),
        ]);

        $digitalOrder->seller?->notify(new DigitalOrderNotification(
            'پیام جدید مشتری',
            "پیام جدید در سفارش {$digitalOrder->number}",
            route('admin.digital-orders.show', $digitalOrder),
        ));

        return back();
    }

    public function receipt(Request $request, DigitalOrder $digitalOrder): RedirectResponse
    {
        $this->authorizeCustomer($request, $digitalOrder);
        abort_if(in_array($digitalOrder->order_status, ['cancelled', 'expired', 'completed'], true), 422);

        $data = $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $file = $data['receipt'];
        $extension = $file->guessExtension() ?: 'jpg';
        $path = 'digital-orders/'.$digitalOrder->id.'/receipts/'.Str::uuid().'.'.$extension;
        MediaStorage::disk()->put($path, fopen($file->getRealPath(), 'rb'));

        $digitalOrder->messages()->create([
            'user_id' => $request->user()->id,
            'type' => 'payment_receipt',
            'message' => trim((string) ($data['message'] ?? 'رسید پرداخت ارسال شد.')),
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
        ]);
        $digitalOrder->update(['payment_status' => 'receipt_sent']);

        $digitalOrder->seller?->notify(new DigitalOrderNotification(
            'رسید پرداخت جدید',
            "مشتری برای سفارش {$digitalOrder->number} رسید ارسال کرد.",
            route('admin.digital-orders.show', $digitalOrder),
        ));

        return back()->with('success', 'رسید پرداخت ارسال شد و فروشنده مطلع شد.');
    }

    public function confirm(Request $request, DigitalOrder $digitalOrder, DigitalOrderService $orders): RedirectResponse
    {
        $this->authorizeCustomer($request, $digitalOrder);
        $orders->complete($digitalOrder);

        return back()->with('success', 'دریافت موفق ثبت شد. سفارش تکمیل شد.');
    }

    public function problem(Request $request, DigitalOrder $digitalOrder): RedirectResponse
    {
        $this->authorizeCustomer($request, $digitalOrder);
        abort_unless(in_array($digitalOrder->delivery_status, ['delivered', 'problem'], true), 422);

        $digitalOrder->update(['delivery_status' => 'problem']);
        $digitalOrder->messages()->create([
            'user_id' => $request->user()->id,
            'type' => 'system',
            'message' => 'مشتری برای اطلاعات تحویل‌شده مشکل گزارش کرد.',
        ]);

        return back()->with('success', 'مشکل ثبت شد؛ گفتگو برای پیگیری باز می‌ماند.');
    }

    public function cancel(Request $request, DigitalOrder $digitalOrder, DigitalOrderService $orders): RedirectResponse
    {
        $this->authorizeCustomer($request, $digitalOrder);
        abort_unless($digitalOrder->payment_status === 'unpaid' && $digitalOrder->order_status === 'new', 422);
        $orders->cancel($digitalOrder, $request->user());

        return back()->with('success', 'سفارش لغو شد.');
    }

    private function authorizeCustomer(Request $request, DigitalOrder $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }

    private function orderCard(DigitalOrder $order): array
    {
        return [
            ...$order->only(['id', 'number', 'sale_price', 'order_status', 'payment_status', 'delivery_status', 'created_at']),
            'product_title' => $order->product->title,
            'offer_label' => $order->offer->label,
            'platform' => $order->product->platform?->name,
            'cover_url' => MediaStorage::url($order->product->game?->cover),
        ];
    }

    private function orderPayload(DigitalOrder $order): array
    {
        return [
            ...$order->only([
                'id', 'number', 'sale_price', 'order_status', 'payment_status',
                'delivery_status', 'reservation_expires_at', 'paid_at',
                'delivered_at', 'completed_at', 'created_at',
            ]),
            'product' => [
                'title' => $order->product->title,
                'platform' => $order->product->platform?->name,
                'cover_url' => MediaStorage::url($order->product->game?->cover),
                'background_url' => MediaStorage::url($order->product->game?->background),
            ],
            'offer' => $order->offer->only(['id', 'label', 'code']),
            'messages' => $order->messages->map(fn ($message) => $this->messagePayload($message))->values(),
            'delivery' => $order->delivery_status === 'delivered' || in_array($order->order_status, ['completed'], true)
                ? $order->delivery?->only(['login', 'password', 'backup_code', 'instructions', 'delivered_at', 'customer_viewed_at'])
                : null,
        ];
    }

    private function messagePayload(DigitalOrderMessage $message): array
    {
        return [
            ...$message->only(['id', 'type', 'message', 'attachment_name', 'seen_at', 'created_at']),
            'sender' => $message->user?->name,
            'is_admin' => (bool) $message->user?->is_admin,
            'attachment_url' => MediaStorage::url($message->attachment_path),
        ];
    }
}
