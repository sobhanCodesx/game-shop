<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'game_id', 'platform_id', 'seller_id', 'title', 'slug',
        'short_description', 'support_days', 'status', 'featured',
    ];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'support_days' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(DigitalOffer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DigitalOrder::class);
    }
}
