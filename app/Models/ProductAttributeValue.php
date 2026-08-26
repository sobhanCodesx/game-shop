<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAttributeValue extends Model
{
    protected $fillable = ['product_id', 'category_attribute_id', 'value'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'category_attribute_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
