<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeSection extends Model
{
    protected $fillable = ['title', 'subtitle', 'content_type', 'query_type', 'layout', 'category_id', 'item_ids', 'items_limit', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'item_ids' => 'array'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
