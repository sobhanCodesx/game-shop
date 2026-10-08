<?php

namespace App\Models;

use App\Services\MediaStorage;
use App\Support\StoryRichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GameStory extends Model
{
    public const KINDS = ['story', 'world', 'character', 'lore', 'quest', 'ending', 'theory', 'rumor', 'other'];

    protected $fillable = [
        'game_id', 'user_id', 'title', 'slug', 'kind', 'subtitle', 'summary', 'body',
        'cover_path', 'source_url', 'contains_spoilers', 'seo_title', 'seo_description',
        'status', 'published_at',
    ];

    protected function casts(): array
    {
        return ['contains_spoilers' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now())
            ->whereHas('game', fn (Builder $games) => $games->whereIn('status', ['active', 'published']));
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getReadingMinutesAttribute(): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags((string) $this->body))) ?: [];
        return max(1, (int) ceil(count($words) / 190));
    }

    public function getCoverUrlAttribute(): ?string
    {
        return MediaStorage::url($this->cover_path ?: $this->game?->background ?: $this->game?->cover);
    }

    public function getPublicUrlAttribute(): string
    {
        return route('game-stories.show', $this->slug, false);
    }

    public function card(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'summary' => $this->summary,
            'kind' => $this->kind,
            'contains_spoilers' => $this->contains_spoilers,
            'image_url' => $this->cover_url,
            'url' => $this->public_url,
            'game' => $this->game?->only(['id', 'name', 'slug']),
            'reading_minutes' => $this->reading_minutes,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
