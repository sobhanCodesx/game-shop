<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DigitalProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'game_id', 'platform_id', 'seller_id', 'title', 'slug',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function media(): HasMany
    {
        return $this->hasMany(DigitalProductMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function coverMedia(): HasOne
    {
        return $this->hasOne(DigitalProductMedia::class)
            ->where('type', 'image')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function features(): HasMany
    {
        return $this->hasMany(DigitalProductFeature::class)->orderBy('sort_order')->orderBy('id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(DigitalProductAttributeValue::class)
            ->orderBy('attribute_id')
            ->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DigitalOrder::class);
    }

    public function socialContents(): HasMany
    {
        return $this->hasMany(SocialContent::class, 'related_digital_product_id');
    }
}
