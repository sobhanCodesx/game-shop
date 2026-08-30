<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyTicketRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Notifications\TicketActivityNotification;
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
        $tickets = Ticket::query()->with(['user:id,name,email', 'product.coverMedia', 'order:id,number'])->withCount('replies')->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))->latest('last_replied_at')->paginate(20)->withQueryString();
        $tickets->through(fn (Ticket $ticket) => [...$ticket->toArray(), 'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path)]);

        return Inertia::render('Admin/Tickets/Index', ['tickets' => $tickets, 'filters' => $request->only('status'), 'stats' => ['pending' => Ticket::where('status', 'pending')->count(), 'open' => Ticket::where('status', 'open')->count(), 'closed' => Ticket::where('status', 'closed')->count()]]);
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
        $ticket = $service->create($order->user, $request->user(), $item, $request->string('subject')->toString(), $request->validated('message'));

        return redirect()->route('admin.tickets.show', $ticket)->with('success', 'تیکت ثبت شد و کاربر اعلان دریافت کرد.');
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load(['user:id,name,email,phone', 'order:id,number', 'orderItem', 'product.coverMedia', 'replies.user:id,name,is_admin']);

        return Inertia::render('Admin/Tickets/Show', ['ticket' => [...$ticket->toArray(), 'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path)]]);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket, TicketService $service): RedirectResponse
    {
        abort_if($ticket->status === 'closed', 422, 'این تیکت بسته شده است. ابتدا آن را دوباره باز کنید.');
        $service->reply($ticket, $request->user(), $request->validated('message'));

        return back()->with('success', 'پاسخ ارسال و کاربر مطلع شد.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'closed'])]], ['status.in' => 'وضعیت انتخاب‌شده معتبر نیست.']);
        $ticket->update($data);
        $ticket->user->notify(new TicketActivityNotification($ticket, 'وضعیت تیکت تغییر کرد', 'وضعیت تیکت '.$ticket->number.' به‌روزرسانی شد.'));

        return back()->with('success', 'وضعیت تیکت تغییر کرد.');
    }
}
