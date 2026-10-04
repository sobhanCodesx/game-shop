<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalOfferPrice extends Model
{
    protected $fillable = [
        'digital_offer_id', 'platform_variant_id', 'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(DigitalOffer::class, 'digital_offer_id');
    }

    public function platformVariant(): BelongsTo
    {
        return $this->belongsTo(PlatformVariant::class);
    }
}
