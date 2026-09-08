<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Studio extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['name', 'slug', 'logo', 'background', 'description', 'website', 'status'];

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(VideoPlaylist::class);
    }
}
