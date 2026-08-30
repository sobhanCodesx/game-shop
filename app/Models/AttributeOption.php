<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeOption extends Model
{
    protected $fillable = ['attribute_id', 'title', 'value', 'status', 'sort_order'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
