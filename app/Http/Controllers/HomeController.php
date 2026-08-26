<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\HomeSettingsController;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use App\Models\Product;
use App\Models\SocialContent;
use App\Services\ProductPriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'pricing' => $prices->forUser($product, $request->user()),
        ];

        return Inertia::render('Home', [
            'settings' => $settings,
            'slides' => HomeSlide::query()->visible()->orderBy('sort_order')->get()->map(fn (HomeSlide $slide) => [
                ...$slide->only(['id', 'title', 'eyebrow', 'description', 'button_label', 'button_url', 'secondary_button_label', 'secondary_button_url', 'text_position', 'overlay']),
                'desktop_image_url' => Storage::url($slide->desktop_image),
                'mobile_image_url' => $slide->mobile_image ? Storage::url($slide->mobile_image) : null,
            ]),
            'categories' => Category::query()->whereNull('parent_id')->where('status', 'active')->withCount('products')->orderBy('sort_order')->limit(8)->get(['id', 'name', 'slug', 'image']),
            'featuredProducts' => Product::query()->with('category:id,name')->where('status', 'published')->where('visibility', 'public')->where('featured', true)->latest()->limit($limit)->get()->map($productMap),
            'latestProducts' => Product::query()->with('category:id,name')->where('status', 'published')->where('visibility', 'public')->latest()->limit($limit)->get()->map($productMap),
            'contentSections' => HomeSection::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (HomeSection $section) use ($request, $prices) {
                if ($section->content_type === 'products') {
                    $query = Product::query()->with('category:id,name')->where('status', 'published')->where('visibility', 'public')
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('sold_stock'))
                        ->when($section->query_type === 'category', fn ($query) => $query->where('category_id', $section->category_id));

                    if (! in_array($section->query_type, ['popular'], true)) {
                        $query->latest();
                    }

                    $items = $query->limit($section->items_limit)->get()->map(fn (Product $product) => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'url' => route('products.show', $product->slug, false),
                        'eyebrow' => $product->category?->name,
                        'badge' => $product->badge,
                        'image_url' => null,
                        'pricing' => $prices->forUser($product, $request->user()),
                    ]);
                } else {
                    $query = SocialContent::query()->published()->where('type', rtrim($section->content_type, 's'))
                        ->when($section->query_type === 'featured', fn ($query) => $query->where('featured', true))
                        ->when($section->query_type === 'popular', fn ($query) => $query->orderByDesc('views'));

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
                        'image_url' => $content->thumbnail ? Storage::url($content->thumbnail) : null,
                        'duration' => $content->duration,
                        'views' => $content->views,
                    ]);
                }

                return [
                    ...$section->only(['id', 'title', 'subtitle', 'content_type']),
                    'items' => $items,
                ];
            })->filter(fn (array $section) => $section['items']->isNotEmpty())->values(),
        ]);
    }
}
