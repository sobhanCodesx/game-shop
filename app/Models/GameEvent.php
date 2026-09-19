<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEvent extends Model
{
    protected $fillable = [
        'game_id',
        'source_content_id',
        'product_id',
        'type',
        'title',
        'summary',
        'source_type',
        'source_name',
        'source_url',
        'external_id',
        'dedupe_key',
        'importance_score',
        'confidence',
        'old_value',
        'new_value',
        'metadata',
        'detected_at',
        'effective_at',
        'expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'importance_score' => 'integer',
            'confidence' => 'float',
            'old_value' => 'array',
            'new_value' => 'array',
            'metadata' => 'array',
            'detected_at' => 'datetime',
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function sourceContent(): BelongsTo
    {
        return $this->belongsTo(SocialContent::class, 'source_content_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
