<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DigitalOrder extends Model
{
    protected $fillable = [
        'number', 'user_id', 'seller_id', 'digital_product_id', 'digital_offer_id',
        'platform_variant_id', 'platform_variant_name',
        'sale_price', 'order_status', 'payment_status',
        'delivery_status', 'reservation_expires_at', 'paid_at',
        'delivered_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'integer',
            'reservation_expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class, 'digital_product_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(DigitalOffer::class, 'digital_offer_id');
    }

    public function platformVariant(): BelongsTo
    {
        return $this->belongsTo(PlatformVariant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DigitalOrderMessage::class)->orderBy('id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(DigitalDelivery::class);
    }
}
