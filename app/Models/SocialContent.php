<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialContent extends Model
{
    protected $fillable = ['user_id', 'game_id', 'type', 'media_type', 'title', 'slug', 'excerpt', 'body', 'seo_title', 'seo_description', 'link_url', 'link_label', 'thumbnail', 'video_path', 'video_mime', 'duration', 'views', 'allow_comments', 'featured', 'sort_order', 'status', 'published_at'];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'allow_comments' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(VideoPlaylist::class, 'playlist_video')
            ->withPivot('position')
            ->withTimestamps();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(SocialContentReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SocialComment::class);
    }
}
