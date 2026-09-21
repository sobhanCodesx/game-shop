<?php

namespace Tests\Feature;

use App\Models\HomeSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\HomeExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_settings_are_cached_until_explicitly_invalidated(): void
    {
        HomeSetting::query()->create([
            'id' => 1,
            'content' => [
                'home_template' => 'default',
                'featured_products_title' => 'نسخه اول',
            ],
        ]);

        $service = app(HomeExperienceService::class);

        $this->assertSame('نسخه اول', $service->settings()['featured_products_title']);

        $setting = HomeSetting::query()->firstOrFail();
        $setting->update([
            'content' => [
                ...$setting->content,
                'featured_products_title' => 'نسخه دوم',
            ],
        ]);

        $this->assertSame('نسخه اول', $service->settings()['featured_products_title']);

        $service->invalidate();

        $this->assertSame('نسخه دوم', $service->settings()['featured_products_title']);
    }

    public function test_current_home_remains_the_default_template(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('homeExperience.system_template', 'default')
                ->where('homeExperience.effective_template', 'default')
                ->where('homeExperience.source', 'system'));
    }

    public function test_dual_spotlight_is_an_available_system_template(): void
    {
        HomeSetting::query()->create([
            'id' => 1,
            'content' => ['home_template' => 'dual_spotlight'],
        ]);
        app(HomeExperienceService::class)->invalidate();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('homeExperience.system_template', 'dual_spotlight')
                ->where('homeExperience.effective_template', 'dual_spotlight')
                ->where('homeExperience.focus', 'balanced')
                ->where('homeExperience.source', 'system'));
    }

    public function test_admin_can_preview_an_available_template_without_persisting_it(): void
    {
        HomeSetting::query()->create([
            'id' => 1,
            'content' => ['home_template' => 'default'],
        ]);
        app(HomeExperienceService::class)->invalidate();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/?preview_home_template=storefront')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homeExperience.system_template', 'default')
                ->where('homeExperience.effective_template', 'storefront')
                ->where('homeExperience.source', 'preview'));

        $this->assertSame(
            'default',
            HomeSetting::query()->findOrFail(1)->content['home_template'],
        );
    }

    public function test_guest_cannot_override_home_template_with_preview_query(): void
    {
        HomeSetting::query()->create([
            'id' => 1,
            'content' => ['home_template' => 'default'],
        ]);
        app(HomeExperienceService::class)->invalidate();

        $this->get('/?preview_home_template=storefront')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('homeExperience.effective_template', 'default')
                ->where('homeExperience.source', 'system'));
    }

    public function test_storefront_loads_more_products_than_the_default_home_limit(): void
    {
        HomeSetting::query()->create([
            'id' => 1,
            'content' => [
                'home_template' => 'storefront',
                'products_limit' => 8,
            ],
        ]);
        Product::factory()->count(13)->create();
        app(HomeExperienceService::class)->invalidate();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('latestProducts', 12)
                ->where('homeExperience.effective_template', 'storefront'));
    }

    public function test_user_preference_wins_when_its_template_is_available(): void
    {
        $user = User::factory()->create([
            'home_focus_preference' => 'products',
        ]);

        $state = app(HomeExperienceService::class)->resolve([
            'home_template' => 'default',
        ], $user);

        $this->assertSame('default', $state['system_template']);
        $this->assertSame('storefront', $state['effective_template']);
        $this->assertSame('products', $state['focus']);
        $this->assertSame('user', $state['source']);
    }

    public function test_user_preference_falls_back_to_system_until_template_is_ready(): void
    {
        $user = User::factory()->create([
            'home_focus_preference' => 'content',
        ]);

        $state = app(HomeExperienceService::class)->resolve([
            'home_template' => 'default',
        ], $user);

        $this->assertSame('default', $state['effective_template']);
        $this->assertSame('system', $state['source']);
    }

    public function test_storefront_is_an_available_system_template(): void
    {
        HomeSetting::query()->create([
            'id' => 1,
            'content' => ['home_template' => 'storefront'],
        ]);
        app(HomeExperienceService::class)->invalidate();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->where('homeExperience.system_template', 'storefront')
                ->where('homeExperience.effective_template', 'storefront')
                ->where('homeExperience.focus', 'products')
                ->where('homeExperience.source', 'system'));
    }
}
