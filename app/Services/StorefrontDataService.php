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
        $pricing = $this->prices->forUser($product, $user);

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
            'pricing' => $pricing,
            'meta_badges' => $this->productMetaBadges($product, $pricing),
            'variants_count' => $product->relationLoaded('variants')
                ? $product->variants->where('status', 'active')->count()
                : null,
        ];
    }

    private function productMetaBadges(Product $product, array $pricing): array
    {
        $badges = collect();
        $stock = $product->show_stock ? max(0, $product->stock - $product->reserved_stock) : null;
        $availability = match ($product->availability) {
            'preorder' => ['پیش‌فروش', 'warning'],
            'coming_soon' => ['به‌زودی', 'accent'],
            'discontinued' => ['توقف فروش', 'danger'],
            default => $stock !== null ? [$stock > 0 ? 'موجود' : 'ناموجود', $stock > 0 ? 'success' : 'danger'] : null,
        };
        if ($availability) {
            $badges->push(['key' => 'availability', 'label' => 'وضعیت', 'value' => $availability[0], 'tone' => $availability[1], 'priority' => 10]);
        }
        if (($pricing['discount_amount'] ?? 0) > 0 && ($pricing['regular_price'] ?? 0) > 0) {
            $percent = (int) round($pricing['discount_amount'] * 100 / $pricing['regular_price']);
            $badges->push(['key' => 'discount', 'label' => 'تخفیف', 'value' => $percent.'٪', 'tone' => 'danger', 'priority' => 20]);
        }
        if ($product->category?->name) {
            $badges->push(['key' => 'category', 'label' => 'دسته', 'value' => $product->category->name, 'tone' => 'accent', 'priority' => 30]);
        }
        if ($product->relationLoaded('platforms') && $product->platforms->isNotEmpty()) {
            $badges->push(['key' => 'platform', 'label' => 'پلتفرم', 'value' => $product->platforms->take(2)->pluck('name')->join('، '), 'tone' => 'info', 'priority' => 40]);
        }

        $attributes = $product->relationLoaded('attributeValues')
            ? $product->attributeValues->filter(fn ($item) => filled($item->value) && $item->attribute)->keyBy(fn ($item) => mb_strtolower($item->attribute->slug ?: $item->attribute->name))
            : collect();
        foreach ([
            ['keys' => ['edition', 'نسخه'], 'key' => 'edition', 'label' => 'نسخه', 'tone' => 'warning', 'priority' => 50],
            ['keys' => ['region', 'ریجن', 'منطقه'], 'key' => 'region', 'label' => 'ریجن', 'tone' => 'success', 'priority' => 55],
        ] as $meta) {
            $match = $attributes->first(fn ($item, $key) => in_array($key, $meta['keys'], true));
            if ($match) {
                $badges->push(['key' => $meta['key'], 'label' => $meta['label'], 'value' => (string) $match->value, 'tone' => $meta['tone'], 'priority' => $meta['priority']]);
            }
        }
        if ($product->game?->developer) {
            $badges->push(['key' => 'developer', 'label' => 'استودیو', 'value' => $product->game->developer, 'tone' => 'neutral', 'priority' => 60]);
        }
        if ($product->game?->publisher) {
            $badges->push(['key' => 'publisher', 'label' => 'ناشر', 'value' => $product->game->publisher, 'tone' => 'neutral', 'priority' => 65]);
        }
        $genre = $attributes->first(fn ($item, $key) => in_array($key, ['genre', 'ژانر'], true));
        if ($genre) {
            $badges->push(['key' => 'genre', 'label' => 'ژانر', 'value' => (string) $genre->value, 'tone' => 'neutral', 'priority' => 70]);
        }
        $type = $product->type?->title ?? $product->product_type;
        if ($type) {
            $badges->push(['key' => 'type', 'label' => 'نوع', 'value' => $type, 'tone' => 'neutral', 'priority' => 80]);
        }

        return $badges->filter(fn ($badge) => filled($badge['value']))->sortBy('priority')->take(5)
            ->map(fn ($badge) => collect($badge)->except('priority')->all())->values()->all();
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
            'url' => $content->type === 'post'
                ? route('feed.show', $content->slug, false)
                : route('content.show', [$plural, $content], false),
            'excerpt' => $content->excerpt,
            'thumbnail_url' => MediaStorage::url($content->thumbnail),
            'video_url' => MediaStorage::url($content->video_path),
            'duration' => $content->duration,
            'views' => $content->views,
            'published_at' => $content->published_at?->toISOString(),
            'channel' => $content->relationLoaded('game') && $content->game ? [
                'id' => $content->game->id,
                'name' => $content->game->name,
                'url' => route('channels.show', $content->game->slug, false),
                'avatar_url' => MediaStorage::url(
                    $content->game->cover ?: ($content->game->relationLoaded('playlists') ? $content->game->playlists->first()?->logo : null)
                ),
            ] : null,
        ];
    }
}
