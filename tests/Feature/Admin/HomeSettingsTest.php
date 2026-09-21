<?php

namespace Tests\Feature\Admin;

use App\Models\HomeSection;
use App\Models\HomeSetting;
use App\Models\HomeSlide;
use App\Models\Category;
use App\Models\Game;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
                'alt' => 'نسل جدید بازی',
                'link_type' => 'url',
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
                'layout' => 'carousel',
                'items_limit' => 10,
                'is_active' => true,
            ]],
        ])->assertRedirect();

        $slide = HomeSlide::firstOrFail();
        Storage::disk('public')->assertExists($slide->desktop_image);
        $this->assertSame('خانه گیمرها', HomeSetting::firstOrFail()->content['featured_products_title']);
        $this->assertDatabaseHas('home_sections', ['title' => 'محصولات محبوب', 'query_type' => 'popular']);
    }

    public function test_admin_can_save_three_new_slides_together(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $slides = collect(range(1, 3))->map(fn (int $index) => [
            'desktop_image_file' => UploadedFile::fake()->image("hero-{$index}.jpg", 1920, 720),
            'alt' => "بنر شماره {$index}",
            'link_type' => 'url',
            'button_url' => '/products',
            'text_position' => 'right',
            'overlay' => 'dark',
            'is_active' => true,
        ])->all();

        $this->actingAs($admin)
            ->post('/admin/home', [
                'settings' => $this->settings(),
                'slides' => $slides,
                'sections' => [],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('home_slides', 3);
        HomeSlide::all()->each(
            fn (HomeSlide $slide) => Storage::disk('public')->assertExists($slide->desktop_image),
        );
    }

    public function test_replacing_and_deleting_banner_removes_obsolete_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::disk('public')->put('home/slides/old-desktop.jpg', 'old desktop');
        Storage::disk('public')->put('home/slides/mobile/old-mobile.jpg', 'old mobile');
        $slide = HomeSlide::query()->create([
            'title' => 'بنر قبلی',
            'desktop_image' => 'home/slides/old-desktop.jpg',
            'mobile_image' => 'home/slides/mobile/old-mobile.jpg',
            'alt' => 'بنر قبلی',
            'link_type' => 'url',
            'button_url' => '/products',
            'text_position' => 'right',
            'overlay' => 'dark',
            'is_active' => true,
        ]);
        $replacement = UploadedFile::fake()->image('replacement.webp', 1920, 720);
        $uploadToken = (string) Str::uuid();
        $uploadDirectory = storage_path("app/private/uploads/{$admin->id}/{$uploadToken}");
        File::ensureDirectoryExists($uploadDirectory);
        File::copy($replacement->getPathname(), $uploadDirectory.'/assembled');
        File::put($uploadDirectory.'/metadata.json', json_encode([
            'name' => $replacement->getClientOriginalName(),
            'mime' => $replacement->getMimeType(),
            'size' => $replacement->getSize(),
        ], JSON_THROW_ON_ERROR));

        $this->actingAs($admin)->post('/admin/home', [
            'settings' => $this->settings(),
            'slides' => [[
                ...$slide->only(['id', 'title', 'desktop_image', 'mobile_image', 'alt', 'link_type', 'button_url', 'text_position', 'overlay', 'is_active']),
                'desktop_upload_token' => $uploadToken,
            ]],
            'sections' => [],
        ])->assertSessionHasNoErrors();

        $newDesktopPath = $slide->fresh()->desktop_image;
        $this->assertNotSame('home/slides/old-desktop.jpg', $newDesktopPath);
        Storage::disk('public')->assertMissing('home/slides/old-desktop.jpg');
        Storage::disk('public')->assertExists($newDesktopPath);
        Storage::disk('public')->assertExists('home/slides/mobile/old-mobile.jpg');
        $this->assertDirectoryDoesNotExist($uploadDirectory);

        $this->actingAs($admin)->post('/admin/home', [
            'settings' => $this->settings(),
            'slides' => [],
            'sections' => [],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('home_slides', ['id' => $slide->id]);
        Storage::disk('public')->assertMissing($newDesktopPath);
        Storage::disk('public')->assertMissing('home/slides/mobile/old-mobile.jpg');
    }

    public function test_home_settings_returns_persian_validation_errors_for_invalid_slides(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->from('/admin/home')
            ->post('/admin/home', [
                'settings' => $this->settings(),
                'slides' => [[
                    'alt' => '',
                    'link_type' => 'url',
                    'button_url' => '',
                    'text_position' => 'right',
                    'overlay' => 'dark',
                    'is_active' => true,
                ]],
                'sections' => [],
            ])
            ->assertRedirect('/admin/home')
            ->assertSessionHasErrors([
                'slides.0.desktop_image',
                'slides.0.alt',
                'slides.0.button_url',
            ]);
    }

    public function test_admin_home_exposes_template_registry_and_keeps_default_active(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/home')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Home/Edit')
                ->where('settings.home_template', 'default')
                ->where('homeTemplates.0.key', 'default')
                ->where('homeTemplates.0.available', true)
                ->where('homeTemplates.1.key', 'dual_spotlight')
                ->where('homeTemplates.1.available', true)
                ->where('homeTemplates.2.key', 'storefront')
                ->where('homeTemplates.2.available', true)
                ->has('homeTemplates', 6));
    }

    public function test_admin_can_activate_dual_spotlight_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = $this->settings();
        $settings['home_template'] = 'dual_spotlight';

        $this->actingAs($admin)
            ->post('/admin/home', [
                'settings' => $settings,
                'slides' => [],
                'sections' => [],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(
            'dual_spotlight',
            HomeSetting::query()->firstOrFail()->content['home_template'],
        );
    }

    public function test_admin_can_activate_storefront_template(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $settings = $this->settings();
        $settings['home_template'] = 'storefront';

        $this->actingAs($admin)
            ->post('/admin/home', [
                'settings' => $settings,
                'slides' => [],
                'sections' => [],
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame(
            'storefront',
            HomeSetting::query()->firstOrFail()->content['home_template'],
        );
    }

    public function test_partial_home_save_cannot_delete_existing_slides_or_sections(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $slide = HomeSlide::create($this->slide([
            'title' => 'بنر موجود',
            'desktop_image' => 'home/slides/existing.jpg',
        ]));
        $section = HomeSection::create([
            'title' => 'سکشن موجود',
            'content_type' => 'products',
            'query_type' => 'latest',
            'layout' => 'carousel',
            'items_limit' => 8,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from('/admin/home')
            ->post('/admin/home', [
                'settings' => $this->settings(),
            ])
            ->assertRedirect('/admin/home')
            ->assertSessionHasErrors(['slides', 'sections']);

        $this->assertDatabaseHas('home_slides', ['id' => $slide->id]);
        $this->assertDatabaseHas('home_sections', ['id' => $section->id]);
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

    public function test_home_returns_active_category_section_instead_of_filtering_it_out(): void
    {
        $category = Category::create([
            'name' => 'کنسول‌ها',
            'slug' => 'consoles',
            'status' => 'active',
        ]);
        HomeSection::create([
            'title' => 'دسته‌بندی‌های محبوب',
            'content_type' => 'categories',
            'query_type' => 'manual',
            'layout' => 'grid',
            'item_ids' => [$category->id],
            'items_limit' => 8,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contentSections', 1)
                ->where('contentSections.0.title', 'دسته‌بندی‌های محبوب')
                ->where('contentSections.0.layout', 'grid')
                ->where('contentSections.0.items.0.title', 'کنسول‌ها')
                ->where('contentSections.0.items.0.url', '/categories/consoles'));
    }

    public function test_published_game_can_be_selected_and_rendered_in_manual_section(): void
    {
        $game = Game::create([
            'name' => 'Grand Theft Auto VI',
            'slug' => 'grand-theft-auto-vi',
            'status' => 'published',
        ]);
        HomeSection::create([
            'title' => 'خفن‌ترین‌ها',
            'content_type' => 'games',
            'query_type' => 'manual',
            'layout' => 'carousel',
            'item_ids' => [$game->id],
            'items_limit' => 10,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contentSections', 1)
                ->where('contentSections.0.items.0.title', 'Grand Theft Auto VI'));
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
            'home_template' => 'default',
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
