<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HomeSlide extends Model
{
    protected $fillable = [
        'title', 'eyebrow', 'description', 'desktop_image', 'mobile_image',
        'button_label', 'button_url', 'secondary_button_label', 'secondary_button_url',
        'text_position', 'overlay', 'sort_order', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
