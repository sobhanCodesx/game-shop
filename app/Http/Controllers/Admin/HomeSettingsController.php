<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomeSettingsRequest;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'desktop_image_url' => Storage::url($slide->desktop_image),
                'mobile_image_url' => $slide->mobile_image ? Storage::url($slide->mobile_image) : null,
                'starts_at' => $slide->starts_at?->format('Y-m-d'),
                'ends_at' => $slide->ends_at?->format('Y-m-d'),
            ]),
            'sections' => HomeSection::query()->orderBy('sort_order')->get(),
            'categories' => Category::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(HomeSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $keptIds = [];
        $keptSectionIds = [];

        DB::transaction(function () use ($request, $validated, &$keptIds, &$keptSectionIds): void {
            HomeSetting::query()->updateOrCreate(['id' => 1], ['content' => $validated['settings']]);

            foreach ($validated['slides'] ?? [] as $index => $data) {
                $slide = isset($data['id']) ? HomeSlide::query()->findOrFail($data['id']) : new HomeSlide;
                $desktopFile = $request->file("slides.{$index}.desktop_image_file");
                $mobileFile = $request->file("slides.{$index}.mobile_image_file");

                if ($desktopFile) {
                    $this->replaceImage($slide->desktop_image, $data['desktop_image'] = $desktopFile->store('home/slides', 'public'));
                }
                if ($mobileFile) {
                    $this->replaceImage($slide->mobile_image, $data['mobile_image'] = $mobileFile->store('home/slides/mobile', 'public'));
                }

                abort_if(blank($data['desktop_image'] ?? null), 422, 'تصویر دسکتاپ هر اسلاید الزامی است.');

                $slide->fill(Arr::except($data, ['id', 'desktop_image_file', 'mobile_image_file', 'desktop_image_url', 'mobile_image_url']));
                $slide->sort_order = $index;
                $slide->save();
                $keptIds[] = $slide->id;
            }

            HomeSlide::query()->whereNotIn('id', $keptIds)->get()->each(function (HomeSlide $slide): void {
                Storage::disk('public')->delete(array_filter([$slide->desktop_image, $slide->mobile_image]));
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

        return back()->with('success', 'تنظیمات صفحه اصلی با موفقیت ذخیره شد.');
    }

    private function replaceImage(?string $oldPath, string $newPath): void
    {
        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }
}
