<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReplyTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Ticket;
use App\Services\MediaStorage;
use App\Services\TicketService;
use App\Services\ExchangeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $challenge = $request->session()->get('ticket_anti_bot');
        $isValidChallenge = is_array($challenge)
            && filled($challenge['code'] ?? null)
            && (int) ($challenge['created_at'] ?? 0) >= now()->subMinutes(20)->timestamp;
        if (! $isValidChallenge) {
            $challenge = ['code' => Str::upper(Str::random(5)), 'created_at' => now()->timestamp];
            $request->session()->put('ticket_anti_bot', $challenge);
        }
        $antiBotCode = (string) $challenge['code'];
        $purchases = OrderItem::query()->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id)->whereNotIn('status', ['rejected', 'cancelled']))->with(['order:id,number,created_at', 'product.coverMedia'])->latest()->paginate(12)->withQueryString();
        $purchases->through(fn (OrderItem $item) => [...$item->toArray(), 'cover_url' => MediaStorage::url($item->product?->coverMedia?->path)]);
        $exchangeProduct = null;
        if ($request->integer('exchange_product')) {
            $product = Product::query()->publiclyVisible()->where('trade_enabled', true)->with('coverMedia')->findOrFail($request->integer('exchange_product'));
            $exchangeProduct = [...$product->only(['id', 'title', 'slug']), 'cover_url' => MediaStorage::url($product->coverMedia?->path)];
        }

        return Inertia::render('Account/Tickets/Create', ['purchases' => $purchases, 'selectedOrderItem' => $request->integer('order_item') ?: null, 'exchangeProduct' => $exchangeProduct, 'antiBotCode' => $antiBotCode]);
    }

    public function store(StoreTicketRequest $request, TicketService $service): RedirectResponse
    {
        $data = $request->validated();
        $item = isset($data['order_item_id']) ? OrderItem::findOrFail($data['order_item_id']) : null;
        $product = ($data['type'] ?? 'support') === 'exchange' ? Product::query()->publiclyVisible()->where('trade_enabled', true)->findOrFail($data['product_id']) : null;
        $ticket = $service->create($request->user(), $request->user(), $item, $data['subject'] ?? null, $data['message'], $product, $data['type'] ?? 'support', $request->file('attachments', []));
        $request->session()->forget('ticket_anti_bot');

        return redirect()->route('account.tickets.show', $ticket)->with('success', 'تیکت شما ثبت شد و پشتیبانی مطلع شد.');
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $ticket->load(['order:id,number', 'orderItem', 'product.coverMedia', 'replies.user:id,name,is_admin', 'replies.attachments']);
        $ticket->replies->each(fn ($reply) => $reply->attachments->each(fn ($attachment) => $attachment->setAttribute('url', MediaStorage::url($attachment->path))));

        return Inertia::render('Account/Tickets/Show', ['ticket' => [...$ticket->toArray(), 'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path)]]);
    }

    public function reply(ReplyTicketRequest $request, Ticket $ticket, TicketService $service): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        abort_if($ticket->status === 'closed', 422, 'این تیکت بسته شده است.');
        $service->reply($ticket, $request->user(), $request->validated('message'), $request->file('attachments', []));

        return back()->with('success', 'پاسخ شما ثبت شد.');
    }

    public function respondToExchange(Request $request, Ticket $ticket, ExchangeService $service): RedirectResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        $data = $request->validate(['decision' => ['required', Rule::in(['accepted', 'rejected'])]]);
        $service->respond($ticket, $request->user(), $data['decision']);
        return back()->with('success', $data['decision'] === 'accepted' ? 'پیشنهاد معاوضه پذیرفته شد.' : 'پیشنهاد معاوضه رد شد.');
    }
}
