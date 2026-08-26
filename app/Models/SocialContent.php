<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialContent extends Model
{
    protected $fillable = ['user_id', 'game_id', 'type', 'title', 'slug', 'excerpt', 'thumbnail', 'duration', 'views', 'featured', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
