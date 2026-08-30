<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'icon', 'inventory_type', 'supports_variants',
        'supports_shipping', 'supports_exchange', 'supports_digital_delivery',
        'supports_digital_inventory', 'requires_cover', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'supports_variants' => 'boolean',
            'supports_shipping' => 'boolean',
            'supports_exchange' => 'boolean',
            'supports_digital_delivery' => 'boolean',
            'supports_digital_inventory' => 'boolean',
            'requires_cover' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_type_attributes')
            ->withPivot(['is_required', 'sort_order'])
            ->orderByPivot('sort_order');
    }
}
