<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSettingsRequest;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Game;
use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use App\Models\Platform;
use App\Models\Product;
use App\Models\SocialContent;
use App\Services\TemporaryUploadService;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeSettingsController extends Controller
{
    public const DEFAULTS = [
        'announcement_enabled' => true,
        'announcement_text' => 'ارسال رایگان سفارش‌های منتخب و تضمین اصالت کالا',
        'announcement_url' => '/products',
        'featured_categories_enabled' => true,
        'featured_categories_title' => 'دنیای بازی را کشف کن',
        'featured_products_enabled' => true,
        'featured_products_title' => 'پیشنهادهای ویژه گیمرها',
        'latest_products_enabled' => true,
        'latest_products_title' => 'تازه‌رسیده‌ها',
        'products_limit' => 8,
        'newsletter_enabled' => true,
        'newsletter_title' => 'از تخفیف‌های گیمینگ جا نمون',
        'newsletter_description' => 'خبرهای مهم، محصولات جدید و پیشنهادهای اختصاصی را دریافت کن.',
        'seo_title' => 'NEXUS PLAY | فروشگاه تخصصی گیمینگ',
        'seo_description' => 'خرید بازی، کنسول و تجهیزات گیمینگ با تضمین اصالت و پشتیبانی تخصصی.',
    ];

    public function edit(): Response
    {
        return Inertia::render('Admin/Home/Edit', [
            'settings' => [...self::DEFAULTS, ...(HomeSetting::query()->first()?->content ?? [])],
            'slides' => HomeSlide::query()->orderBy('sort_order')->get()->map(fn (HomeSlide $slide) => [
                ...$slide->toArray(),
                'alt' => $slide->alt ?: $slide->title,
                'link_type' => $slide->link_type ?: ($slide->product_id ? 'product' : 'url'),
                'desktop_image_url' => MediaStorage::url($slide->desktop_image),
                'mobile_image_url' => MediaStorage::url($slide->mobile_image),
                'starts_at' => $slide->starts_at?->format('Y-m-d'),
                'ends_at' => $slide->ends_at?->format('Y-m-d'),
            ]),
            'sections' => HomeSection::query()->orderBy('sort_order')->get()->map(fn (HomeSection $section) => [
                ...$section->toArray(),
                'subtitle' => $section->subtitle ?? '',
                'layout' => $section->layout ?: 'carousel',
                'item_ids' => $section->item_ids ?? [],
                'items_limit' => $section->items_limit ?: 10,
            ]),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'sectionSources' => [
                'products' => Product::query()->publiclyVisible()->latest()->limit(200)->get(['id', 'title'])->map(fn (Product $item) => ['id' => $item->id, 'label' => $item->title]),
                'categories' => Category::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name'])->map(fn (Category $item) => ['id' => $item->id, 'label' => $item->name]),
                'games' => Game::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name'])->map(fn (Game $item) => ['id' => $item->id, 'label' => $item->name]),
                'brands' => Brand::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name'])->map(fn (Brand $item) => ['id' => $item->id, 'label' => $item->name]),
                'platforms' => Platform::query()->whereIn('status', ['active', 'published'])->orderBy('sort_order')->get(['id', 'name'])->map(fn (Platform $item) => ['id' => $item->id, 'label' => $item->name]),
                'posts' => $this->socialSource('post'),
                'videos' => $this->socialSource('video'),
                'shorts' => $this->socialSource('short'),
            ],
            'products' => Product::query()->publiclyVisible()->latest()->limit(300)->get(['id', 'title', 'slug']),
        ]);
    }

    public function update(HomeSettingsRequest $request, TemporaryUploadService $uploads): RedirectResponse
    {
        $validated = $request->validated();
        $keptIds = [];
        $keptSectionIds = [];

        $claimedTokens = [];
        try {
            DB::transaction(function () use ($request, $validated, $uploads, &$claimedTokens, &$keptIds, &$keptSectionIds): void {
            HomeSetting::query()->updateOrCreate(['id' => 1], ['content' => $validated['settings']]);

            foreach ($validated['slides'] ?? [] as $index => $data) {
                $slide = isset($data['id']) ? HomeSlide::query()->findOrFail($data['id']) : new HomeSlide;
                $desktopFile = $request->file("slides.{$index}.desktop_image_file");
                $mobileFile = $request->file("slides.{$index}.mobile_image_file");

                if ($token = ($data['desktop_upload_token'] ?? null)) {
                    $desktopFile = $uploads->claim($request->user()->id, $token);
                    $claimedTokens[] = $token;
                }
                if ($token = ($data['mobile_upload_token'] ?? null)) {
                    $mobileFile = $uploads->claim($request->user()->id, $token);
                    $claimedTokens[] = $token;
                }

                if ($desktopFile) {
                    $this->replaceImage($slide->desktop_image, $data['desktop_image'] = $desktopFile->store('home/slides', (string) config('media.disk')));
                }
                if ($mobileFile) {
                    $this->replaceImage($slide->mobile_image, $data['mobile_image'] = $mobileFile->store('home/slides/mobile', (string) config('media.disk')));
                }

                abort_if(blank($data['desktop_image'] ?? null), 422, 'تصویر دسکتاپ هر اسلاید الزامی است.');

                $data['title'] = $data['alt'];
                $data['button_url'] = $data['link_type'] === 'product'
                    ? route('products.show', Product::query()->findOrFail($data['product_id'])->slug, false)
                    : $data['button_url'];
                $slide->fill(Arr::except($data, ['id', 'desktop_image_file', 'mobile_image_file', 'desktop_upload_token', 'mobile_upload_token', 'desktop_image_url', 'mobile_image_url']));
                $slide->sort_order = $index;
                $slide->save();
                $keptIds[] = $slide->id;
            }

            HomeSlide::query()->whereNotIn('id', $keptIds)->get()->each(function (HomeSlide $slide): void {
                MediaStorage::disk()->delete(array_filter([$slide->desktop_image, $slide->mobile_image]));
                $slide->delete();
            });

            foreach ($validated['sections'] ?? [] as $index => $data) {
                $section = isset($data['id']) ? HomeSection::query()->findOrFail($data['id']) : new HomeSection;
                $section->fill(Arr::except($data, ['id']));
                $section->sort_order = $index;
                $section->save();
                $keptSectionIds[] = $section->id;
            }

            HomeSection::query()->whereNotIn('id', $keptSectionIds)->delete();
            });
        } finally {
            foreach ($claimedTokens as $token) {
                $uploads->forget($request->user()->id, $token);
            }
        }

        return back()->with('success', 'تنظیمات صفحه اصلی با موفقیت ذخیره شد.');
    }

    private function replaceImage(?string $oldPath, string $newPath): void
    {
        if ($oldPath && $oldPath !== $newPath) {
            MediaStorage::disk()->delete($oldPath);
        }
    }

    private function socialSource(string $type)
    {
        return SocialContent::query()->where('type', $type)->latest()->limit(200)->get(['id', 'title'])
            ->map(fn (SocialContent $item) => ['id' => $item->id, 'label' => $item->title]);
    }
}
