<?php

namespace Tests\Feature\Admin;

use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_home_settings_and_slider(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/home', [
            'settings' => $this->settings(),
            'slides' => [[
                'title' => 'نسل جدید بازی',
                'eyebrow' => 'پیشنهاد ویژه',
                'description' => 'جدیدترین بازی‌ها و تجهیزات حرفه‌ای',
                'desktop_image_file' => UploadedFile::fake()->image('hero.jpg', 1920, 720),
                'button_label' => 'خرید کنید',
                'button_url' => '/products',
                'text_position' => 'right',
                'overlay' => 'dark',
                'is_active' => true,
            ]],
            'sections' => [[
                'title' => 'محصولات محبوب',
                'content_type' => 'products',
                'query_type' => 'popular',
                'items_limit' => 10,
                'is_active' => true,
            ]],
        ])->assertRedirect();

        $slide = HomeSlide::firstOrFail();
        Storage::disk('public')->assertExists($slide->desktop_image);
        $this->assertSame('خانه گیمرها', HomeSetting::firstOrFail()->content['featured_products_title']);
        $this->assertDatabaseHas('home_sections', ['title' => 'محصولات محبوب', 'query_type' => 'popular']);
    }

    public function test_home_returns_configured_social_content_rail(): void
    {
        HomeSection::create(['title' => 'ویدیوهای محبوب', 'content_type' => 'videos', 'query_type' => 'popular', 'items_limit' => 8, 'is_active' => true]);
        SocialContent::create(['type' => 'video', 'title' => 'بررسی بازی', 'slug' => 'game-review', 'views' => 500, 'status' => 'published', 'published_at' => now()]);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('contentSections', 1)
                ->where('contentSections.0.title', 'ویدیوهای محبوب')
                ->where('contentSections.0.items.0.title', 'بررسی بازی'));

        $this->get('/videos/game-review')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Content/Show'));
    }

    public function test_home_only_returns_current_active_slides(): void
    {
        HomeSlide::create($this->slide(['title' => 'فعال']));
        HomeSlide::create($this->slide(['title' => 'آینده', 'starts_at' => now()->addDay()]));
        HomeSlide::create($this->slide(['title' => 'غیرفعال', 'is_active' => false]));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('slides', 1)
                ->where('slides.0.title', 'فعال'));
    }

    private function settings(): array
    {
        return [
            'announcement_enabled' => true,
            'announcement_text' => 'ارسال رایگان',
            'announcement_url' => '/products',
            'featured_categories_enabled' => true,
            'featured_categories_title' => 'دسته‌ها',
            'featured_products_enabled' => true,
            'featured_products_title' => 'خانه گیمرها',
            'latest_products_enabled' => true,
            'latest_products_title' => 'جدیدها',
            'products_limit' => 8,
            'newsletter_enabled' => true,
            'newsletter_title' => 'خبرنامه',
            'newsletter_description' => 'عضو شوید',
            'seo_title' => 'فروشگاه تخصصی گیمینگ',
            'seo_description' => 'خرید محصولات تخصصی گیمینگ با تضمین اصالت و ارسال سریع.',
        ];
    }

    private function slide(array $overrides = []): array
    {
        return [...[
            'title' => 'بنر',
            'desktop_image' => 'home/slides/banner.jpg',
            'text_position' => 'right',
            'overlay' => 'dark',
            'sort_order' => 0,
            'is_active' => true,
        ], ...$overrides];
    }
}
