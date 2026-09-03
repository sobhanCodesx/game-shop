<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const INVOICEABLE_STATUSES = ['delivered'];

    protected $fillable = ['number', 'user_id', 'coupon_id', 'coupon_code', 'exchange_request_id', 'trade_user_id', 'approved_product_id', 'approved_trade_value', 'trade_item_title', 'trade_item_description', 'trade_item_images', 'trade_item_metadata', 'exchange_credit_used', 'shipping_address', 'status', 'regular_subtotal', 'product_discount', 'subtotal', 'coupon_discount', 'delivery_fee', 'grand_total', 'wallet_used', 'payable_amount', 'cashback_percent', 'cashback_eligible_amount', 'cashback_amount', 'reviewed_by', 'reviewed_at', 'admin_note'];

    protected function casts(): array
    {
        return ['shipping_address' => 'array', 'trade_item_images' => 'array', 'trade_item_metadata' => 'array', 'cashback_percent' => 'decimal:2', 'cashback_credited_at' => 'datetime', 'cashback_reversed_at' => 'datetime', 'wallet_refunded_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function exchangeRequest(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'exchange_request_id');
    }

    public function isInvoiceable(): bool
    {
        return in_array($this->status, self::INVOICEABLE_STATUSES, true);
    }
}
