<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    public const EXCHANGE_STATUSES = ['pending_review', 'offered', 'accepted', 'rejected', 'attached_to_order', 'received', 'completed', 'cancelled', 'expired'];

    protected $fillable = ['number', 'user_id', 'order_id', 'order_item_id', 'product_id', 'target_product_id', 'trade_item_title', 'trade_item_description', 'trade_item_images', 'trade_item_metadata', 'subject', 'type', 'status', 'exchange_status', 'exchange_offer_amount', 'exchange_order_id', 'exchange_credit_applied', 'exchange_credit_expires_at', 'exchange_offer_responded_at', 'exchange_received_at', 'exchange_completed_at', 'exchange_credited_at', 'exchange_cancelled_at', 'exchange_expired_at', 'priority', 'last_replied_at', 'created_by'];

    protected function casts(): array
    {
        return ['trade_item_images' => 'array', 'trade_item_metadata' => 'array', 'last_replied_at' => 'datetime', 'exchange_offer_amount' => 'integer', 'exchange_credit_applied' => 'integer', 'exchange_credit_expires_at' => 'datetime', 'exchange_offer_responded_at' => 'datetime', 'exchange_received_at' => 'datetime', 'exchange_completed_at' => 'datetime', 'exchange_credited_at' => 'datetime', 'exchange_cancelled_at' => 'datetime', 'exchange_expired_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function targetProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'target_product_id');
    }

    public function exchangeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'exchange_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }
}
