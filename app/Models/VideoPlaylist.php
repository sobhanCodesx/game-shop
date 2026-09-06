<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VideoPlaylist extends Model
{
    protected $fillable = ['game_id', 'title', 'slug', 'logo', 'description', 'visibility', 'sort_order'];

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(SocialContent::class, 'playlist_video')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('playlist_video.position');
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }
}
