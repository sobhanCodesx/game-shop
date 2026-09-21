<?php

namespace Tests\Feature\Admin;

use App\Models\HomeSetting;
use App\Models\User;
use App\Services\HomeExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_commerce_update_preserves_home_settings_and_invalidates_home_cache(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        HomeSetting::query()->create([
            'id' => 1,
            'content' => [
                'home_template' => 'storefront',
                'featured_products_title' => 'ویژه‌های تست',
                'delivery_fee' => 10_000,
                'pickup_address' => 'آدرس قبلی',
                'cashback_percent' => 2,
            ],
        ]);

        $homeExperience = app(HomeExperienceService::class);
        $this->assertSame(10_000, $homeExperience->settings()['delivery_fee']);

        $this->actingAs($admin)
            ->put('/admin/settings', [
                'delivery_fee' => 75_000,
                'pickup_address' => 'تهران، فروشگاه PlayNexus',
                'cashback_percent' => 4,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $content = HomeSetting::query()->findOrFail(1)->content;
        $this->assertSame('storefront', $content['home_template']);
        $this->assertSame('ویژه‌های تست', $content['featured_products_title']);
        $this->assertSame(75_000, $content['delivery_fee']);
        $this->assertSame('تهران، فروشگاه PlayNexus', $content['pickup_address']);
        $this->assertSame(4, (int) $content['cashback_percent']);

        $freshCachedSettings = $homeExperience->settings();
        $this->assertSame(75_000, $freshCachedSettings['delivery_fee']);
        $this->assertSame('storefront', $freshCachedSettings['home_template']);
    }

    public function test_home_and_commerce_services_read_the_singleton_settings_row(): void
    {
        HomeSetting::query()->create([
            'id' => 2,
            'content' => [
                'home_template' => 'dual_spotlight',
                'delivery_fee' => 999_999,
            ],
        ]);
        HomeSetting::query()->create([
            'id' => 1,
            'content' => [
                'home_template' => 'storefront',
                'delivery_fee' => 42_000,
            ],
        ]);

        $this->assertSame(
            'storefront',
            app(HomeExperienceService::class)->settings()['home_template'],
        );
        $this->assertSame(
            42_000,
            app(\App\Services\CommerceSettings::class)->all()['delivery_fee'],
        );
    }
}
