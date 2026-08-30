<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'brand_id', 'game_id', 'title', 'slug', 'sku', 'internal_code',
        'short_description', 'description', 'product_type', 'product_type_id', 'price',
        'discount_price', 'compare_price', 'partner_price', 'cost_price', 'stock',
        'reserved_stock', 'sold_stock', 'low_stock_threshold', 'availability', 'release_date', 'status',
        'featured', 'visibility', 'weight', 'condition', 'length', 'width', 'height',
        'barcode', 'requires_shipping', 'shipping_class', 'delivery_method', 'purchase_notes',
        'delivery_notes', 'return_policy', 'warranty', 'tags', 'minimum_quantity',
        'maximum_quantity', 'badge', 'trade_enabled', 'allow_reviews', 'allow_comments',
        'allow_questions', 'show_stock', 'seo_title', 'seo_description', 'seo_keywords',
        'canonical', 'published_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'trade_enabled' => 'boolean',
            'requires_shipping' => 'boolean',
            'allow_reviews' => 'boolean',
            'allow_comments' => 'boolean',
            'allow_questions' => 'boolean',
            'show_stock' => 'boolean',
            'tags' => 'array',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'release_date' => 'date',
        ];
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn (Builder $query, string $search) => $query->where(
            fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%"),
        ));
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where('visibility', 'public')
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    public function coverMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)
            ->where('type', 'image')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
