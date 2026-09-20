<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReplyTicketRequest;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Ticket;
use App\Services\ExchangeService;
use App\Services\MediaStorage;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = $request->user()
            ->tickets()
            ->with(['product.coverMedia', 'order:id,number'])
            ->withCount('replies')
            ->latest('last_replied_at')
            ->paginate(max(1, min(30, $request->integer('per_page', 12))))
            ->withQueryString()
            ->through(fn (Ticket $ticket) => [
                ...$ticket->toArray(),
                'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path),
            ]);

        return response()->json([
            'tickets' => $tickets,
            'stats' => [
                'pending' => $request->user()->tickets()->where('status', 'pending')->count(),
                'open' => $request->user()->tickets()->where('status', 'open')->count(),
                'closed' => $request->user()->tickets()->where('status', 'closed')->count(),
            ],
        ]);
    }

    public function createContext(Request $request): JsonResponse
    {
        $purchases = OrderItem::query()
            ->whereHas(
                'order',
                fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->whereNotIn('status', ['rejected', 'cancelled']),
            )
            ->with(['order:id,number,created_at', 'product.coverMedia'])
            ->latest()
            ->paginate(max(1, min(30, $request->integer('per_page', 12))))
            ->withQueryString();

        $purchases->through(fn (OrderItem $item) => [
            ...$item->toArray(),
            'cover_url' => MediaStorage::url($item->product?->coverMedia?->path),
        ]);

        $exchangeProduct = null;
        if ($request->integer('exchange_product')) {
            $product = Product::query()
                ->publiclyVisible()
                ->where('trade_enabled', true)
                ->with('coverMedia')
                ->findOrFail($request->integer('exchange_product'));

            $exchangeProduct = [
                ...$product->only(['id', 'title', 'slug']),
                'cover_url' => MediaStorage::url($product->coverMedia?->path),
            ];
        }

        return response()->json([
            'purchases' => $purchases,
            'exchange_product' => $exchangeProduct,
        ]);
    }

    public function store(Request $request, TicketService $service): JsonResponse
    {
        $data = validator($request->all(), [
            'order_item_id' => [
                'nullable',
                'integer',
                Rule::exists('order_items', 'id')->where(
                    fn ($query) => $query->whereIn(
                        'order_id',
                        $request->user()->orders()->select('id'),
                    ),
                ),
            ],
            'type' => ['nullable', Rule::in(['support', 'exchange'])],
            'product_id' => [
                'required_if:type,exchange',
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('trade_enabled', true),
            ],
            'trade_item_title' => [
                'required_if:type,exchange',
                'nullable',
                'string',
                'min:2',
                'max:180',
            ],
            'subject' => [
                'required_without_all:order_item_id,product_id',
                'nullable',
                'string',
                'max:180',
            ],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'attachments' => ['required_if:type,exchange', 'nullable', 'array', 'max:5'],
            'attachments.*' => [
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime',
                'max:51200',
            ],
        ])->validate();

        $item = isset($data['order_item_id'])
            ? OrderItem::findOrFail($data['order_item_id'])
            : null;

        $product = ($data['type'] ?? 'support') === 'exchange'
            ? Product::query()
                ->publiclyVisible()
                ->where('trade_enabled', true)
                ->findOrFail($data['product_id'])
            : null;

        $ticket = $service->create(
            $request->user(),
            $request->user(),
            $item,
            $data['subject'] ?? null,
            $data['message'],
            $product,
            $data['type'] ?? 'support',
            $request->file('attachments', []),
            $data['trade_item_title'] ?? null,
        );

        return response()->json([
            'message' => 'تیکت شما ثبت شد و پشتیبانی مطلع شد.',
            'ticket' => $this->ticketPayload($ticket->fresh()),
        ], 201);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        $ticket->load([
            'order:id,number',
            'orderItem',
            'product.coverMedia',
            'targetProduct:id,title,slug',
            'exchangeOrder:id,number',
            'replies.user:id,name,is_admin',
            'replies.attachments',
        ]);

        $ticket->replies->each(fn ($reply) => $reply->attachments->each(
            fn ($attachment) => $attachment->setAttribute(
                'url',
                MediaStorage::url($attachment->path),
            ),
        ));

        return response()->json([
            'ticket' => $this->ticketPayload($ticket),
        ]);
    }

    public function reply(
        ReplyTicketRequest $request,
        Ticket $ticket,
        TicketService $service,
    ): JsonResponse {
        abort_unless($ticket->user_id === $request->user()->id, 404);
        abort_if($ticket->status === 'closed', 422, 'این تیکت بسته شده است.');

        $service->reply(
            $ticket,
            $request->user(),
            $request->validated('message'),
            $request->file('attachments', []),
        );

        return response()->json([
            'message' => 'پاسخ شما ثبت شد.',
            'ticket' => $this->ticketPayload($ticket->fresh(['replies.user', 'replies.attachments'])),
        ]);
    }

    public function exchangeDecision(
        Request $request,
        Ticket $ticket,
        ExchangeService $service,
    ): JsonResponse {
        abort_unless($ticket->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
        ]);

        $service->respond($ticket, $request->user(), $data['decision']);

        return response()->json([
            'message' => $data['decision'] === 'accepted'
                ? 'پیشنهاد معاوضه پذیرفته شد.'
                : 'پیشنهاد معاوضه رد شد.',
            'ticket' => $this->ticketPayload($ticket->fresh()),
        ]);
    }

    private function ticketPayload(Ticket $ticket): array
    {
        return [
            ...$ticket->toArray(),
            'cover_url' => MediaStorage::url($ticket->product?->coverMedia?->path),
        ];
    }
}
