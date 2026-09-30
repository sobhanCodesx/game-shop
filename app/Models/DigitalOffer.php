<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class DigitalOffer extends Model
{
    protected $fillable = [
        'digital_product_id', 'code', 'label', 'supplier_cost',
        'price', 'stock', 'reserved_stock', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supplier_cost' => 'integer',
            'price' => 'integer',
            'stock' => 'integer',
            'reserved_stock' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DigitalProduct::class, 'digital_product_id');
    }

    public function availableStock(): int
    {
        return max(0, (int) $this->stock - (int) $this->reserved_stock);
    }

    public function ensureAvailable(): void
    {
        if ($this->status !== 'active' || $this->availableStock() < 1) {
            throw ValidationException::withMessages([
                'offer_id' => 'این ظرفیت در حال حاضر موجود نیست.',
            ]);
        }
    }
}
