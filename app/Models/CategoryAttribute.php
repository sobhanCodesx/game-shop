<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryAttribute extends Model
{
    protected $fillable = ['category_id', 'attribute_id', 'name', 'slug', 'type', 'options', 'is_required', 'is_filterable', 'sort_order'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'is_filterable' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
