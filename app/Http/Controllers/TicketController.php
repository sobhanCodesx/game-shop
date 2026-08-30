<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Services\MediaStorage;
use App\Services\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $tickets = $request->user()->tickets()->with(['product.coverMedia', 'order:id,number'])->withCount('replies')->latest('last_replied_at')->paginate(12)->withQueryString();
        $tickets->through(fn (Ticket $ticket) => [...$ticket->toArray(), 'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path)]);

        return Inertia::render('Account/Tickets/Index', ['tickets' => $tickets, 'stats' => ['pending' => $request->user()->tickets()->where('status', 'pending')->count(), 'open' => $request->user()->tickets()->where('status', 'open')->count(), 'closed' => $request->user()->tickets()->where('status', 'closed')->count()]]);
    }

    public function create(Request $request): Response
    {
        $antiBotCode = Str::upper(Str::random(5));
        $request->session()->put('ticket_anti_bot', ['code' => $antiBotCode, 'created_at' => now()->timestamp]);
        $purchases = OrderItem::query()->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id)->whereNotIn('status', ['rejected', 'cancelled']))->with(['order:id,number,created_at', 'product.coverMedia'])->latest()->paginate(12)->withQueryString();
        $purchases->through(fn (OrderItem $item) => [...$item->toArray(), 'cover_url' => MediaStorage::url($item->product?->coverMedia?->path)]);

        return Inertia::render('Account/Tickets/Create', ['purchases' => $purchases, 'selectedOrderItem' => $request->integer('order_item') ?: null, 'antiBotCode' => $antiBotCode]);
    }

    public function store(StoreTicketRequest $request, TicketService $service): RedirectResponse
    {
        $data = $request->validated();
        $item = isset($data['order_item_id']) ? OrderItem::findOrFail($data['order_item_id']) : null;
        $ticket = $service->create($request->user(), $request->user(), $item, $data['subject'] ?? null, $data['message']);
        $request->session()->forget('ticket_anti_bot');

        return redirect()->route('account.tickets.show', $ticket)->with('success', 'تیکت شما ثبت شد و پشتیبانی مطلع شد.');
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $ticket->load(['order:id,number', 'orderItem', 'product.coverMedia', 'replies.user:id,name,is_admin']);

        return Inertia::render('Account/Tickets/Show', ['ticket' => [...$ticket->toArray(), 'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path)]]);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket, TicketService $service): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        abort_if($ticket->status === 'closed', 422, 'این تیکت بسته شده است.');
        $service->reply($ticket, $request->user(), $request->validated('message'));

        return back()->with('success', 'پاسخ شما ثبت شد.');
    }
}
