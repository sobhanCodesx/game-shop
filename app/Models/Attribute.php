<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'input_type', 'is_required', 'is_filterable',
        'is_searchable', 'is_visible_on_product', 'is_usable_for_variant',
        'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'is_searchable' => 'boolean',
            'is_visible_on_product' => 'boolean',
            'is_usable_for_variant' => 'boolean',
        ];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class)->orderBy('sort_order');
    }

    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class, 'product_type_attributes')
            ->withPivot(['is_required', 'sort_order']);
    }
}
