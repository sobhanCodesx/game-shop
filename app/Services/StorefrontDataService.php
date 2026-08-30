<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;

class StorefrontDataService
{
    public function __construct(private readonly ProductPriceService $prices) {}

    public function navigation(): array
    {
        $categories = Category::query()->where('status', 'active')
            ->withCount(['products' => fn ($query) => $query->publiclyVisible()])
            ->orderBy('sort_order')->orderBy('id')->get();
        $grouped = $categories->groupBy(fn (Category $category) => $category->parent_id ?? 0);

        $build = function (Category $category) use (&$build, $grouped): array {
            $children = $grouped->get($category->id, collect())->map(fn (Category $child) => $build($child))->values();

            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image_url' => MediaStorage::url($category->image),
                'products_count' => (int) $category->products_count + $children->sum('products_count'),
                'children' => $children->all(),
            ];
        };

        return $grouped->get(0, collect())->map(fn (Category $category) => $build($category))->values()->all();
    }

    public function category(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => MediaStorage::url($category->image),
            'products_count' => $category->products_count ?? 0,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn (Category $child) => $this->category($child))->values()->all()
                : [],
        ];
    }

    public function product(Product $product, ?User $user): array
    {
        $cover = $product->relationLoaded('coverMedia') ? $product->coverMedia : null;

        return [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'url' => route('products.show', $product, false),
            'category' => $product->category?->name,
            'badge' => $product->badge,
            'product_type' => $product->type?->title ?? $product->product_type,
            'availability' => $product->availability,
            'stock' => $product->show_stock ? max(0, $product->stock - $product->reserved_stock) : null,
            'trade_enabled' => $product->trade_enabled,
            'cover_url' => MediaStorage::url($cover?->path),
            'cover_alt' => $cover?->alt ?: $product->title,
            'pricing' => $this->prices->forUser($product, $user),
            'variants_count' => $product->relationLoaded('variants')
                ? $product->variants->where('status', 'active')->count()
                : null,
        ];
    }

    public function content(SocialContent $content): array
    {
        $plural = match ($content->type) {
            'video' => 'videos', 'short' => 'shorts', default => 'posts'
        };

        return [
            'id' => $content->id,
            'type' => $content->type,
            'title' => $content->title,
            'slug' => $content->slug,
            'url' => route('content.show', [$plural, $content], false),
            'excerpt' => $content->excerpt,
            'thumbnail_url' => MediaStorage::url($content->thumbnail),
            'video_url' => MediaStorage::url($content->video_path),
            'duration' => $content->duration,
            'views' => $content->views,
            'published_at' => $content->published_at?->toISOString(),
        ];
    }
}
