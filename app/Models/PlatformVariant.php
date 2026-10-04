<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformVariant extends Model
{
    protected $fillable = [
        'platform_id', 'name', 'key', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function offerPrices(): HasMany
    {
        return $this->hasMany(DigitalOfferPrice::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DigitalOrder::class);
    }
}
