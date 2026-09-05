<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Game extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'cover', 'background', 'release_date',
        'developer', 'publisher', 'age_rating', 'status',
    ];

    protected function casts(): array
    {
        return ['release_date' => 'date'];
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(SocialContent::class)->where('type', 'video');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(VideoPlaylist::class)->orderBy('sort_order');
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'game_subscriptions')->withTimestamps();
    }
}
