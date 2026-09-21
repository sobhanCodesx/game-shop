<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyTicketRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Ticket;
use App\Notifications\TicketActivityNotification;
use App\Services\ExchangeService;
use App\Services\MediaStorage;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->string('type')->toString();
        abort_unless(in_array($type, ['', 'support', 'exchange'], true), 404);
        $tickets = Ticket::query()->with(['user:id,name,email', 'product.coverMedia', 'order:id,number'])->withCount('replies')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('last_replied_at')->paginate(20)->withQueryString();
        $tickets->through(fn (Ticket $ticket) => [...$ticket->toArray(), 'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path)]);

        $statsQuery = fn () => Ticket::query()->when($type, fn ($query) => $query->where('type', $type));

        return Inertia::render('Admin/Tickets/Index', ['tickets' => $tickets, 'filters' => ['status' => $request->string('status')->toString(), 'type' => $type], 'stats' => ['pending' => $statsQuery()->where('status', 'pending')->count(), 'open' => $statsQuery()->where('status', 'open')->count(), 'closed' => $statsQuery()->where('status', 'closed')->count()]]);
    }

    public function createForOrder(Order $order): Response
    {
        $order->load(['user:id,name,email', 'items.product.coverMedia']);

        return Inertia::render('Admin/Tickets/Create', ['order' => [...$order->toArray(), 'items' => $order->items->map(fn ($item) => [...$item->toArray(), 'cover_url' => MediaStorage::url($item->product?->coverMedia?->path)])]]);
    }

    public function storeForOrder(ReplyTicketRequest $request, Order $order, TicketService $service): RedirectResponse
    {
        $request->validate(['order_item_id' => ['nullable', Rule::exists('order_items', 'id')->where('order_id', $order->id)], 'subject' => ['required_without:order_item_id', 'nullable', 'string', 'max:180']], ['order_item_id.exists' => 'محصول انتخاب‌شده متعلق به این سفارش نیست.', 'subject.required_without' => 'موضوع تیکت را وارد کنید.']);
        $item = $request->filled('order_item_id') ? OrderItem::findOrFail($request->integer('order_item_id')) : null;
        $ticket = $service->create($order->user, $request->user(), $item, $request->string('subject')->toString(), $request->validated('message'), attachments: $request->file('attachments', []));

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'تیکت ثبت شد و کاربر اعلان دریافت کرد.');
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load(['user:id,name,email,phone,wallet_balance', 'order:id,number', 'orderItem', 'product.coverMedia', 'targetProduct:id,title', 'exchangeOrder:id,number', 'replies.user:id,name,is_admin', 'replies.attachments']);
        $ticket->replies->each(fn ($reply) => $reply->attachments->each(fn ($attachment) => $attachment->setAttribute('url', MediaStorage::url($attachment->path))));

        return Inertia::render('Admin/Tickets/Show', [
            'ticket' => [
                ...$ticket->toArray(),
                'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path),
            ],
        ]);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket, TicketService $service): RedirectResponse
    {
        abort_if($ticket->status === 'closed', 422, 'این تیکت بسته شده است. ابتدا آن را دوباره باز کنید.');
        $service->reply($ticket, $request->user(), $request->validated('message'), $request->file('attachments', []));

        return back()->with('success', 'پاسخ ارسال و کاربر مطلع شد.');
    }

    public function offer(Request $request, Ticket $ticket, ExchangeService $service): RedirectResponse
    {
        $data = $request->validate([
            'exchange_offer_amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ]);
        $targetProduct = Product::query()
            ->whereKey($ticket->product_id)
            ->where('trade_enabled', true)
            ->firstOrFail();
        $ticket = $service->offer($ticket, $targetProduct, (int) $data['exchange_offer_amount']);
        $ticket->user->notify(new TicketActivityNotification($ticket, 'پیشنهاد معاوضه آماده است', 'مبلغ پیشنهادی معاوضه ثبت شد؛ برای مشاهده و تأیید وارد درخواست شوید.'));

        return back()->with('success', 'پیشنهاد معاوضه ثبت شد.');
    }

    public function cancelExchange(Ticket $ticket, ExchangeService $service): RedirectResponse
    {
        $ticket = $service->cancel($ticket);
        $ticket->user->notify(new TicketActivityNotification($ticket, 'درخواست معاوضه لغو شد', 'درخواست معاوضه '.$ticket->number.' توسط پشتیبانی لغو شد.'));

        return back()->with('success', 'درخواست معاوضه لغو شد.');
    }

    public function completeExchange(Ticket $ticket, ExchangeService $service): RedirectResponse
    {
        if ($ticket->exchange_status === 'attached_to_order') {
            $ticket = $service->markReceived($ticket);
        }
        $service->complete($ticket);

        return back()->with('success', 'معاوضه تکمیل و اعتبار کیف پول ثبت شد.');
    }

    public function destroyAttachments(Ticket $ticket, TicketService $service): RedirectResponse
    {
        $count = $service->purgeAttachments($ticket);

        return back()->with('success', $count ? "{$count} فایل تیکت از فضای ذخیره‌سازی حذف شد؛ متن تیکت محفوظ است." : 'این تیکت فایل پیوستی ندارد.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]], ['status.in' => 'وضعیت انتخاب‌شده معتبر نیست.']);
        $ticket->update($data);
        $ticket->user->notify(new TicketActivityNotification($ticket, 'وضعیت تیکت تغییر کرد', 'وضعیت تیکت '.$ticket->number.' به‌روزرسانی شد.'));

        return back()->with('success', 'وضعیت تیکت تغییر کرد.');
    }
}
