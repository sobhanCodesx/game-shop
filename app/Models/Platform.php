<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Platform extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'manufacturer', 'icon', 'status', 'sort_order', 'is_dual_platform',
    ];

    protected function casts(): array
    {
        return [
            'is_dual_platform' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PlatformVariant::class)->orderBy('sort_order')->orderBy('id');
    }
}
