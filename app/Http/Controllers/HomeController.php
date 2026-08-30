<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\HomeSettingsController;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Game;
use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\Platform;
use App\Models\SocialContent;
use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaStorage;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(Request $request, ProductPriceService $prices): Response
    {
        $settings = [...HomeSettingsController::DEFAULTS, ...(HomeSetting::query()->first()?->content ?? [])];
        $limit = (int) $settings['products_limit'];
        $productMap = fn (Product $product) => [
            'title' => $product->title,
            'slug' => $product->slug,
            'category' => $product->category?->name,
            'badge' => $product->badge,
            'cover_url' => MediaStorage::url($product->coverMedia?->path),
            'pricing' => $prices->forUser($product, $request->user()),
        ];

        return Inertia::render('Home', [
            'settings' => $settings,
            'slides' => HomeSlide::query()->visible()->orderBy('sort_order')->get()->map(fn (HomeSlide $slide) => [
                ...$slide->only(['id', 'title', 'alt', 'link_type', 'product_id', 'button_url']),
                'desktop_image_url' => MediaStorage::url($slide->desktop_image),
                'mobile_image_url' => MediaStorage::url($slide->mobile_image),
                'target_url' => $slide->button_url ?: '#',
            ]),
            'categories' => Category::query()
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->withCount('products')
                ->with([
                    'children' => fn ($query) => $query
                        ->where('status', 'active')
                        ->with(['children' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')])
                        ->orderBy('sort_order'),
                ])
                ->orderBy('sort_order')
                ->limit(8)
                ->get(['id', 'parent_id', 'name', 'slug', 'image'])
                ->map(fn (Category $category) => $this->navigationCategory($category)),
            'featuredProducts' => Product::query()->with(['category:id,name', 'coverMedia'])->publiclyVisible()->where('featured', true)->latest()->limit($limit)->get()->map($productMap),
            'latestProducts' => Product::query()->with(['category:id,name', 'coverMedia'])->publiclyVisible()->latest()->limit($limit)->get()->map($productMap),
            'contentSections' => HomeSection::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (HomeSection $section) use ($request, $prices) {
                if ($section->content_type === 'products') {
                    $query = Product::query()->with(['category:id,name', 'coverMedia'])->publiclyVisible()
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('sold_stock'))
                        ->when($section->query_type === 'category', fn ($query) => $query->where('category_id', $section->category_id))
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []));

                    if (! in_array($section->query_type, ['popular'], true)) {
                        $query->latest();
                    }

                    $items = $query->limit($section->items_limit)->get()->map(fn (Product $product) => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'url' => route('products.show', $product->slug, false),
                        'eyebrow' => $product->category?->name,
                        'badge' => $product->badge,
                        'image_url' => MediaStorage::url($product->coverMedia?->path),
                        'pricing' => $prices->forUser($product, $request->user()),
                    ]);
                } elseif (in_array($section->content_type, ['categories', 'games', 'brands', 'platforms'], true)) {
                    $model = match ($section->content_type) {
                        'categories' => Category::class,
                        'games' => Game::class,
                        'brands' => Brand::class,
                        'platforms' => Platform::class,
                    };
                    $imageField = match ($section->content_type) {
                        'categories' => 'image',
                        'games' => 'cover',
                        'brands' => 'logo',
                        'platforms' => 'icon',
                    };
                    $query = $model::query()->whereIn('status', ['active', 'published'])
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []))
                        ->when($section->query_type !== 'manual', fn ($query) => $query->latest());

                    $items = $query->limit($section->items_limit)->get()->map(fn ($item) => [
                        'id' => $item->id,
                        'title' => $item->name,
                        'url' => $section->content_type === 'categories'
                            ? "/categories/{$item->slug}"
                            : '/search?q='.urlencode($item->name),
                        'eyebrow' => match ($section->content_type) {
                            'categories' => 'دسته‌بندی',
                            'games' => 'بازی',
                            'brands' => 'برند',
                            'platforms' => 'پلتفرم',
                        },
                        'excerpt' => $item->description ?? $item->manufacturer ?? null,
                        'image_url' => MediaStorage::url($item->{$imageField}),
                    ]);
                } else {
                    $query = SocialContent::query()->published()->where('type', rtrim($section->content_type, 's'))
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('views'))
                        ->when($section->query_type === 'manual', fn ($query) => $query->whereIn('id', $section->item_ids ?? []));

                    if ($section->query_type !== 'popular') {
                        $query->latest('published_at');
                    }

                    $items = $query->limit($section->items_limit)->get()->map(fn (SocialContent $content) => [
                        'id' => $content->id,
                        'title' => $content->title,
                        'url' => "/{$section->content_type}/{$content->slug}",
                        'eyebrow' => match ($content->type) {
                            'video' => 'ویدیو', 'short' => 'ویدیوی کوتاه', default => 'پست'
                        },
                        'excerpt' => $content->excerpt,
                        'image_url' => MediaStorage::url($content->thumbnail),
                        'duration' => $content->duration,
                        'views' => $content->views,
                    ]);
                }

                return [
                    ...$section->only(['id', 'title', 'subtitle', 'content_type', 'layout']),
                    'items' => $items,
                ];
            })->filter(fn (array $section) => $section['items']->isNotEmpty())->values(),
        ]);
    }

    private function navigationCategory(Category $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image_url' => MediaStorage::url($category->image),
            'products_count' => $category->products_count ?? 0,
            'children' => $category->relationLoaded('children')
                ? $category->children->map(fn (Category $child) => $this->navigationCategory($child))->values()
                : [],
        ];
    }
}
