<?php

namespace Tests\Feature\Admin;

use App\Models\SmsProviderSetting;
use App\Models\User;
use App\Services\Sms\SmsProviderSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SmsProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_environment_fallback_for_payamak_panel(): void
    {
        config()->set('services.payamak_panel.username', 'env-user');
        config()->set('services.payamak_panel.api_key', 'env-key');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/sms-providers')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/SmsProviders/Index')
                ->where('activeProvider', 'payamak_panel')
                ->where('providers.0.source', 'environment')
                ->where('providers.0.settings.username', 'env-user')
                ->where('providers.0.settings.api_key', 'env-key'));
    }

    public function test_database_override_can_be_activated_and_read_back(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/sms-providers/sms_ir', [
            'activate' => true,
            'settings' => [
                'base_url' => 'https://api.sms.ir/v1',
                'api_key' => 'secret-api-key',
                'line_number' => '3000123456',
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $setting = SmsProviderSetting::query()->where('provider', 'sms_ir')->firstOrFail();
        $this->assertTrue($setting->is_active);
        $this->assertSame('secret-api-key', $setting->settings['api_key']);

        $service = app(SmsProviderSettings::class);
        $this->assertSame('sms_ir', $service->activeProvider());
        $this->assertSame('secret-api-key', $service->resolved('sms_ir')['api_key']);
    }

    public function test_reset_returns_provider_to_environment_fallback(): void
    {
        config()->set('services.payamak_panel.username', 'env-user');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put('/admin/sms-providers/payamak_panel', [
            'activate' => true,
            'settings' => [
                'base_url' => 'https://rest.payamak-panel.com/api/SmartSMS',
                'pattern_endpoint' => 'https://rest.payamak-panel.com/api/SmartSMS/SendByBaseNumber',
                'username' => 'db-user',
                'api_key' => 'db-key',
                'from' => '',
                'from_support_one' => '',
                'from_support_two' => '',
            ],
        ])->assertRedirect();

        $this->actingAs($admin)->delete('/admin/sms-providers/payamak_panel')
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseMissing('sms_provider_settings', ['provider' => 'payamak_panel']);
        $this->assertSame('env-user', app(SmsProviderSettings::class)->resolved('payamak_panel')['username']);
    }
}
